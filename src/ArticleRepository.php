<?php

require_once __DIR__ . '/Database.php';

class ArticleRepository
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::connect();
    }

    public function list($params)
    {
        $page = isset($params['page']) ? (int) $params['page'] : 1;
        if ($page < 1) { $page = 1; }

        $perPage = isset($params['per_page']) ? (int) $params['per_page'] : 20;
        if ($perPage < 1)   { $perPage = 1; }
        if ($perPage > 100) { $perPage = 100; }

        $offset = ($page - 1) * $perPage;

        $allowedSort = ['published_at', 'fetched_at', 'description_len', 'title', 'id'];
        $sortByInput = $params['sort_by'] ?? '';
        if (in_array($sortByInput, $allowedSort, true)) {
            $sortBy = $sortByInput;
        } else {
            $sortBy = 'published_at';
        }

        $sortOrderInput = isset($params['sort_order']) ? strtoupper($params['sort_order']) : 'DESC';
        if ($sortOrderInput === 'ASC') {
            $sortOrder = 'ASC';
        } else {
            $sortOrder = 'DESC';
        }

        $where = [];
        $binds = [];

        if (!empty($params['filter'])) {
            $filterCondition = $this->parseFilter($params['filter'], $binds);
            if ($filterCondition !== '') {
                $where[] = $filterCondition;
            }
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) AS total FROM articles $whereClause";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($binds as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $sql = "
            SELECT id, title, link, description, published_at, fetched_at, description_len
            FROM articles
            $whereClause
            ORDER BY `$sortBy` $sortOrder
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->pdo->prepare($sql);
        foreach ($binds as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $articles = $stmt->fetchAll();

        foreach ($articles as &$article) {
            $article['tags'] = $this->getArticleTags($article['id']);
        }

        return [
            'data' => $articles,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
            'sort' => [
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ];
    }

    public function getById($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT id, title, link, description, published_at, fetched_at, description_len
            FROM articles WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $row['tags'] = $this->getArticleTags($id);

        return $row;
    }

    public function stats()
    {
        $row = $this->pdo->query("
            SELECT
                COUNT(*) AS total_articles,
                MIN(published_at) AS earliest,
                MAX(published_at) AS latest,
                ROUND(AVG(description_len)) AS avg_desc_len,
                MAX(fetched_at) AS last_fetched
            FROM articles
        ")->fetch();

        if ($row === false) {
            return [];
        }

        return $row;
    }

    private function parseFilter($filter, &$binds)
    {
        $parts = preg_split('/(_AND_|_OR_|_NOT_)/', $filter, -1, PREG_SPLIT_DELIM_CAPTURE);

        $segments = [];
        $operators = [];

        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed === '') continue;
            if (in_array($trimmed, ['_AND_', '_OR_', '_NOT_'], true)) {
                $operators[] = trim($trimmed, '_');
            } else {
                $segments[] = $trimmed;
            }
        }

        $segments = array_slice($segments, 0, 3);

        $conditions = [];
        foreach ($segments as $i => $seg) {
            $cond = $this->buildFilterCondition($seg, $i, $binds);
            if ($cond !== '') {
                $conditions[] = $cond;
            }
        }

        if (empty($conditions)) return '';
        if (count($conditions) === 1) return $conditions[0];

        $result = $conditions[0];
        for ($i = 1; $i < count($conditions); $i++) {
            $op = isset($operators[$i - 1]) ? $operators[$i - 1] : 'AND';
            if ($op === 'NOT') {
                $result = "($result AND NOT {$conditions[$i]})";
            } else {
                $result = "($result $op {$conditions[$i]})";
            }
        }

        return $result;
    }

    private function buildFilterCondition($segment, $index, &$binds)
    {
        $pos = strpos($segment, '=');
        if ($pos === false) return '';

        $type = strtolower(substr($segment, 0, $pos));
        $value = substr($segment, $pos + 1);

        if ($value === '' || $value === false) return '';

        switch ($type) {
            case 'date':
                return $this->buildDateCondition($value, $index, $binds);
            case 'desc_len':
                return $this->buildDescLenCondition($value, $index, $binds);
            case 'keywords':
                return $this->buildKeywordCondition($value, $index, $binds);
            default:
                return '';
        }
    }

    private function buildDateCondition($value, $index, &$binds)
    {
        if (strpos($value, '..') !== false) {
            [$from, $to] = explode('..', $value, 2);
            $binds[":df$index"] = $from;
            if (strlen($to) === 10) {
                $to .= ' 23:59:59';
            }
            $binds[":dt$index"] = $to;
            return "(fetched_at >= :df$index AND fetched_at <= :dt$index)";
        }
        $binds[":df$index"] = $value;
        return "(fetched_at >= :df$index)";
    }

    private function buildDescLenCondition($value, $index, &$binds)
    {
        if (strpos($value, '..') !== false) {
            [$min, $max] = explode('..', $value, 2);
            $binds[":dlmin$index"] = (int) $min;
            $binds[":dlmax$index"] = (int) $max;
            return "(description_len >= :dlmin$index AND description_len <= :dlmax$index)";
        }
        $binds[":dl$index"] = (int) $value;
        return "(description_len >= :dl$index)";
    }

    private function buildKeywordCondition($value, $index, &$binds)
    {
        $escaped = '%' . $this->escapeLike($value) . '%';
        $binds[":kwt$index"] = $escaped;
        $binds[":kwd$index"] = $escaped;
        return "(title LIKE :kwt$index OR description LIKE :kwd$index)";
    }

    private function escapeLike($value)
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
    }

    private function getArticleTags($articleId)
    {
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.name
            FROM tags t
            JOIN article_tags at ON t.id = at.tag_id
            WHERE at.article_id = :article_id
            ORDER BY t.name
        ");
        $stmt->execute([':article_id' => $articleId]);
        return $stmt->fetchAll();
    }
}
