<?php

require_once __DIR__ . '/Database.php';

class RssFetcher
{
    private $pdo;
    private $feedUrl;

    public function __construct()
    {
        $this->pdo = Database::connect();

        $url = getenv('RSS_URL');
        if ($url === false) { $url = 'https://habr.com/ru/rss/articles/'; }
        $this->feedUrl = $url;
    }

    public function fetch()
    {
        $xml = $this->loadFeed();
        if ($xml === false) {
            return ['success' => false, 'error' => 'Не удалось загрузить RSS ленту', 'inserted' => 0];
        }

        $inserted = 0;
        $skipped = 0;

        $stmtArticle = $this->pdo->prepare("
            INSERT IGNORE INTO articles (title, link, description, published_at, description_len)
            VALUES (:title, :link, :description, :published_at, :description_len)
        ");

        $stmtGetId = $this->pdo->prepare("
            SELECT id FROM articles WHERE link = :link
        ");

        $stmtTag = $this->pdo->prepare("
            INSERT IGNORE INTO tags (name) VALUES (:name)
        ");

        $stmtGetTag = $this->pdo->prepare("
            SELECT id FROM tags WHERE name = :name
        ");

        $stmtArticleTag = $this->pdo->prepare("
            INSERT IGNORE INTO article_tags (article_id, tag_id) VALUES (:article_id, :tag_id)
        ");

        foreach ($xml->channel->item as $item) {
            $title = $this->cleanText((string) $item->title);
            $link = $this->cleanUrl((string) $item->link);
            $description = $this->cleanText((string) $item->description);
            $pubDate = (string) $item->pubDate;
            $publishedAt = date('Y-m-d H:i:s', strtotime($pubDate));

            $stmtArticle->execute([
                ':title' => $title,
                ':link' => $link,
                ':description' => $description,
                ':published_at' => $publishedAt,
                ':description_len' => mb_strlen($description, 'UTF-8'),
            ]);

            $stmtGetId->execute([':link' => $link]);
            $articleRow = $stmtGetId->fetch();
            if ($articleRow === false) {
                continue;
            }
            $articleId = (int) $articleRow['id'];

            foreach ($item->category as $category) {
                $tagName = $this->cleanText((string) $category);
                if ($tagName !== '') {
                    $stmtTag->execute([':name' => $tagName]);

                    $stmtGetTag->execute([':name' => $tagName]);
                    $tagRow = $stmtGetTag->fetch();
                    if ($tagRow !== false) {
                        $tagId = (int) $tagRow['id'];
                        $stmtArticleTag->execute([
                            ':article_id' => $articleId,
                            ':tag_id' => $tagId,
                        ]);
                    }
                }
            }

            if ($stmtArticle->rowCount() > 0) {
                $inserted++;
            } else {
                $skipped++;
            }
        }

        return [
            'success' => true,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'total' => $inserted + $skipped,
        ];
    }

    private function loadFeed()
    {
        $ch = curl_init($this->feedUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'RSSReader/1.0 (PHP)',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code !== 200) {
            return false;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();

        if ($xml === false) {
            return false;
        }

        return $xml;
    }

    private function cleanText($text)
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim($text);
    }

    private function cleanUrl($url)
    {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = str_replace('\\/', '/', $url);
        return trim($url);
    }
}
