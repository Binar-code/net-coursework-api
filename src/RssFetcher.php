<?php

require_once __DIR__ . '/Database.php';

class RssFetcher
{
    private PDO $pdo;
    private string $feedUrl;

    public function __construct()
    {
        $this->pdo     = Database::connect();
        $this->feedUrl = getenv('RSS_URL') ?: 'https://habr.com/ru/rss/articles/';
    }

    /**
     * Fetch RSS feed, parse items, insert new articles.
     * Returns count of newly inserted articles.
     */
    public function fetch(): array
    {
        $xml = $this->loadFeed();
        if ($xml === false) {
            return ['success' => false, 'error' => 'Failed to load RSS feed', 'inserted' => 0];
        }

        $inserted = 0;
        $skipped  = 0;

        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO articles (title, link, description, published_at, description_len)
            VALUES (:title, :link, :description, :published_at, :description_len)
        ");

        foreach ($xml->channel->item as $item) {
            $title       = $this->cleanText((string) $item->title);
            $link        = trim((string) $item->link);
            $description = $this->cleanText((string) $item->description);
            $pubDate     = (string) $item->pubDate;

            // Parse publication date to MySQL datetime
            $publishedAt = date('Y-m-d H:i:s', strtotime($pubDate));

            $stmt->execute([
                ':title'           => $title,
                ':link'            => $link,
                ':description'     => $description,
                ':published_at'    => $publishedAt,
                ':description_len' => mb_strlen($description, 'UTF-8'),
            ]);

            if ($stmt->rowCount() > 0) {
                $inserted++;
            } else {
                $skipped++;
            }
        }

        return [
            'success'  => true,
            'inserted' => $inserted,
            'skipped'  => $skipped,
            'total'    => $inserted + $skipped,
        ];
    }

    private function loadFeed(): SimpleXMLElement|false
    {
        // Use cURL for better control over headers (Habr may block default PHP UA)
        $ch = curl_init($this->feedUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'RSSReader/1.0 (PHP)',
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

        return $xml ?: false;
    }

    private function cleanText(string $text): string
    {
        // Strip HTML tags, decode entities, trim whitespace
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim($text);
    }
}
