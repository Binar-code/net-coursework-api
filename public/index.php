<?php

require_once __DIR__ . '/../src/Router.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/RssFetcher.php';
require_once __DIR__ . '/../src/ArticleRepository.php';

// ── Handle CORS preflight ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

// ── Init router ─────────────────────────────────────────────
$router = new Router();

// ── GET / — API info ────────────────────────────────────────
$router->get('', function () {
    Router::json([
        'name'    => 'Habr RSS Feed API',
        'version' => '1.0.0',
        'endpoints' => [
            [
                'method'      => 'POST',
                'path'        => '/api/fetch',
                'description' => 'Fetch RSS feed from Habr and store new articles in DB',
            ],
            [
                'method'      => 'GET',
                'path'        => '/api/articles',
                'description' => 'List articles with filters, sorting, pagination',
                'params'      => [
                    'page'          => 'int — page number (default 1)',
                    'per_page'      => 'int — items per page (default 20, max 100)',
                    'sort_by'       => 'string — published_at | fetched_at | description_len | title (default published_at)',
                    'sort_order'    => 'string — asc | desc (default desc)',
                    'date_from'     => 'string — ISO date, e.g. 2025-01-01',
                    'date_to'       => 'string — ISO date, e.g. 2025-12-31',
                    'desc_len_min'  => 'int — minimum description length in chars',
                    'desc_len_max'  => 'int — maximum description length in chars',
                    'keywords'      => 'string — comma-separated, max 3 keywords',
                    'keyword_logic' => 'string — AND | OR | NOT (default AND)',
                ],
            ],
            [
                'method'      => 'GET',
                'path'        => '/api/articles/{id}',
                'description' => 'Get single article by ID',
            ],
            [
                'method'      => 'GET',
                'path'        => '/api/stats',
                'description' => 'Database statistics',
            ],
        ],
    ]);
});

// ── POST /api/fetch — pull RSS feed ─────────────────────────
$router->post('/api/fetch', function () {
    try {
        $fetcher = new RssFetcher();
        $result  = $fetcher->fetch();
        Router::json($result);
    } catch (Throwable $e) {
        Router::json(['error' => $e->getMessage()], 500);
    }
});

// ── GET /api/articles — list with filters ───────────────────
$router->get('/api/articles', function () {
    try {
        $repo   = new ArticleRepository();
        $result = $repo->list($_GET);
        Router::json($result);
    } catch (Throwable $e) {
        Router::json(['error' => $e->getMessage()], 500);
    }
});

// ── GET /api/articles/{id} — single article ─────────────────
$router->get('/api/articles/{id}', function (array $params) {
    try {
        $repo    = new ArticleRepository();
        $article = $repo->getById((int) $params['id']);

        if ($article === null) {
            Router::json(['error' => 'Article not found'], 404);
        }

        Router::json(['data' => $article]);
    } catch (Throwable $e) {
        Router::json(['error' => $e->getMessage()], 500);
    }
});

// ── GET /api/stats — mysql statistics ────────────────────
$router->get('/api/stats', function () {
    try {
        $repo = new ArticleRepository();
        Router::json(['data' => $repo->stats()]);
    } catch (Throwable $e) {
        Router::json(['error' => $e->getMessage()], 500);
    }
});

// ── Run ─────────────────────────────────────────────────────
$router->dispatch();
