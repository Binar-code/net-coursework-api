<?php

require_once __DIR__ . '/Database.php';

class ArticleRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connect();
    }

    /**
     * Get articles with filters, sorting, and pagination.
     *
     * Supported query params:
     *   -- Pagination --
     *   page           int    (default 1)
     *   per_page       int    (default 20, max 100)
     *
     *   -- Sorting --
     *   sort_by        string (published_at|fetched_at|description_len|title)  default published_at
     *   sort_order     string (asc|desc)  default desc
     *
     *   -- Filters --
     *   date_from      string  ISO date  (published_at >= ...)
     *   date_to        string  ISO date  (published_at <= ...)
     *   desc_len_min   int     minimum description length
     *   desc_len_max   int     maximum description length
     *
     *   -- Keyword filter (up to 3 keywords with logic) --
     *   keywords       string  comma-separated, max 3 words
     *   keyword_logic  string  AND | OR | NOT   (default AND)
     *       AND  — article must contain ALL keywords
     *       OR   — article must contain at least ONE keyword
     *       NOT  — article must contain NONE of the keywords
     */
    public function list(array $params): array
    {
        // ── Pagination ──────────────────────────────────────
        $page    = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;

        // ── Sorting ─────────────────────────────────────────
        $allowedSort  = ['published_at', 'fetched_at', 'description_len', 'title', 'id'];
        $sortBy       = in_array($params['sort_by'] ?? '', $allowedSort, true)
            ? $params['sort_by']
            : 'published_at';
        $sortOrder    = strtoupper($params['sort_order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        // ── Build WHERE conditions ──────────────────────────
        $where  = [];
        $binds  = [];

        // Date filter
        if (!empty($params['date_from'])) {
            $where[]             = 'published_at >= :date_from';
            $binds[':date_from'] = $params['date_from'];
        }
        if (!empty($params['date_to'])) {
            $where[]           = 'published_at <= :date_to';
            $binds[':date_to'] = $params['date_to'];
        }

        // Description length filter
        if (isset($params['desc_len_min']) && $params['desc_len_min'] !== '') {
            $where[]                = 'description_len >= :desc_len_min';
            $binds[':desc_len_min'] = (int) $params['desc_len_min'];
        }
        if (isset($params['desc_len_max']) && $params['desc_len_max'] !== '') {
            $where[]                = 'description_len <= :desc_len_max';
            $binds[':desc_len_max'] = (int) $params['desc_len_max'];
        }

        // Keyword filter (max 3 keywords)
        $this->applyKeywordFilter($params, $where, $binds);

        // ── Assemble SQL ────────────────────────────────────
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total matching rows
        $countSql = "SELECT COUNT(*) AS total FROM articles $whereClause";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($binds);
        $total = (int) $countStmt->fetchColumn();

        // Fetch page
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
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        $articles = $stmt->fetchAll();

        return [
            'data'       => $articles,
            'pagination' => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_pages'=> (int) ceil($total / $perPage),
            ],
            'sort' => [
                'sort_by'    => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ];
    }

    /**
     * Get single article by ID.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, title, link, description, published_at, fetched_at, description_len
            FROM articles WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get DB stats.
     */
    public function stats(): array
    {
        $row = $this->pdo->query("
            SELECT
                COUNT(*)                          AS total_articles,
                MIN(published_at)                 AS earliest,
                MAX(published_at)                 AS latest,
                ROUND(AVG(description_len))       AS avg_desc_len,
                MAX(fetched_at)                   AS last_fetched
            FROM articles
        ")->fetch();

        return $row ?: [];
    }

    // ─── Private helpers ─────────────────────────────────────

    private function applyKeywordFilter(array $params, array &$where, array &$binds): void
    {
        if (empty($params['keywords'])) {
            return;
        }

        // Parse keywords — comma-separated, max 3
        $raw = array_map('trim', explode(',', $params['keywords']));
        $raw = array_filter($raw, fn($k) => $k !== '');
        $keywords = array_slice($raw, 0, 3);

        if (empty($keywords)) {
            return;
        }

        $logic = strtoupper($params['keyword_logic'] ?? 'AND');
        if (!in_array($logic, ['AND', 'OR', 'NOT'], true)) {
            $logic = 'AND';
        }

        // Build LIKE conditions for each keyword (search in title + description)
        $conditions = [];
        foreach ($keywords as $i => $kw) {
            $placeholder = ":kw_$i";
            $binds[$placeholder] = '%' . $this->escapeLike($kw) . '%';
            $conditions[] = "(title LIKE $placeholder OR description LIKE $placeholder)";
        }

        switch ($logic) {
            case 'AND':
                // All keywords must be present
                $where[] = '(' . implode(' AND ', $conditions) . ')';
                break;

            case 'OR':
                // At least one keyword present
                $where[] = '(' . implode(' OR ', $conditions) . ')';
                break;

            case 'NOT':
                // None of the keywords should be present
                $negated = array_map(fn($c) => "NOT $c", $conditions);
                $where[] = '(' . implode(' AND ', $negated) . ')';
                break;
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
    }
}
