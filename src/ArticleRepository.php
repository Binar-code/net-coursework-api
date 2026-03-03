<?php

require_once __DIR__ . '/Database.php';

class ArticleRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connect();
    }

    public function list(array $params): array
    {
        $page = isset($params['page']) ? (int) $params['page'] : 1;
        if ($page < 1) { $page = 1; }

        $perPage = isset($params['per_page']) ? (int) $params['per_page'] : 20;
        if ($perPage < 1)   { $perPage = 1; }
        if ($perPage > 100) { $perPage = 100; }

        $offset = ($page - 1) * $perPage;

        $allowedSort = ['published_at', 'fetched_at', 'description_len', 'title', 'id'];
        $sortByInput = isset($params['sort_by']) ? $params['sort_by'] : '';
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

        if (!empty($params['date_from'])) {
            $where[] = 'published_at >= :date_from';
            $binds[':date_from'] = $params['date_from'];
        }
        if (!empty($params['date_to'])) {
            $where[] = 'published_at <= :date_to';
            $binds[':date_to'] = $params['date_to'];
        }

        if (isset($params['desc_len_min']) && $params['desc_len_min'] !== '') {
            $where[] = 'description_len >= :desc_len_min';
            $binds[':desc_len_min'] = (int) $params['desc_len_min'];
        }
        if (isset($params['desc_len_max']) && $params['desc_len_max'] !== '') {
            $where[] = 'description_len <= :desc_len_max';
            $binds[':desc_len_max'] = (int) $params['desc_len_max'];
        }

        $this->applyKeywordFilter($params, $where, $binds);

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) AS total FROM articles $whereClause";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($binds);
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

    public function getById(int $id)
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

        return $row;
    }

    public function stats(): array
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

    private function applyKeywordFilter(array $params, array &$where, array &$binds): void
    {
        if (empty($params['keywords'])) {
            return;
        }

        $raw = array_map('trim', explode(',', $params['keywords']));
        $filtered = [];
        foreach ($raw as $k) {
            if ($k !== '') {
                $filtered[] = $k;
            }
        }
        $keywords = array_slice($filtered, 0, 3);

        if (empty($keywords)) {
            return;
        }

        $logic = isset($params['keyword_logic']) ? strtoupper($params['keyword_logic']) : 'AND';
        if (!in_array($logic, ['AND', 'OR', 'NOT'], true)) {
            $logic = 'AND';
        }

        $conditions = [];
        foreach ($keywords as $i => $kw) {
            $placeholder = ":kw_$i";
            $binds[$placeholder] = '%' . $this->escapeLike($kw) . '%';
            $conditions[] = "(title LIKE $placeholder OR description LIKE $placeholder)";
        }

        if ($logic === 'AND') {
            $where[] = '(' . implode(' AND ', $conditions) . ')';
        } elseif ($logic === 'OR') {
            $where[] = '(' . implode(' OR ', $conditions) . ')';
        } elseif ($logic === 'NOT') {
            $negated = [];
            foreach ($conditions as $condition) {
                $negated[] = 'NOT ' . $condition;
            }
            $where[] = '(' . implode(' AND ', $negated) . ')';
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
    }
}
