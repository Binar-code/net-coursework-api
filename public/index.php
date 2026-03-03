<?php

require_once __DIR__ . '/../src/Router.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/RssFetcher.php';
require_once __DIR__ . '/../src/ArticleRepository.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

$router = new Router();

$router->get('', function () {
    Router::json([
        'name' => 'API Ленты RSS Habr',
        'version' => '1.0.0',
        'endpoints' => [
            [
                'method' => 'POST',
                'path' => '/api/fetch',
                'description' => 'Загрузить RSS ленту Habr и сохранить новые статьи в БД',
            ],
            [
                'method' => 'GET',
                'path' => '/api/articles',
                'description' => 'Список статей с фильтрами, сортировкой, постраничной выдачей и тегами',
                'params' => [
                    'page' => 'int — номер страницы (по умолчанию 1)',
                    'per_page' => 'int — статей на странице (по умолчанию 20, максимум 100)',
                    'sort_by' => 'string — published_at | fetched_at | description_len | title (по умолчанию published_at)',
                    'sort_order' => 'string — asc | desc (по умолчанию desc)',
                    'date_from' => 'string — ISO дата, например 2025-01-01',
                    'date_to' => 'string — ISO дата, например 2025-12-31',
                    'desc_len_min' => 'int — минимальная длина описания в символах',
                    'desc_len_max' => 'int — максимальная длина описания в символах',
                    'keywords' => 'string — слова через запятую, максимум 3 слова',
                    'keyword_logic' => 'string — AND | OR | NOT (по умолчанию AND)',
                ],
                'fields' => [
                    'id' => 'int — уникальный ID статьи',
                    'title' => 'string — название статьи',
                    'link' => 'string — ссылка на статью',
                    'description' => 'string — описание статьи',
                    'published_at' => 'string — дата публикации (ISO)',
                    'fetched_at' => 'string — дата добавления в БД (ISO)',
                    'description_len' => 'int — длина описания в символах',
                    'tags' => 'array — массив тегов статьи ({id, name})',
                ],
            ],
            [
                'method' => 'GET',
                'path' => '/api/articles/{id}',
                'description' => 'Получить одну статью по ID со всеми её тегами',
            ],
            [
                'method' => 'GET',
                'path' => '/api/stats',
                'description' => 'Статистика базы данных',
            ],
        ],
    ]);
});

$router->post('/api/fetch', function () {
    try {
        $fetcher = new RssFetcher();
        $result = $fetcher->fetch();
        Router::json($result);
    } catch (Throwable $e) {
        Router::json(['error' => $e->getMessage()], 500);
    }
});

$router->get('/api/articles', function () {
    try {
        $repo = new ArticleRepository();
        $result = $repo->list($_GET);
        Router::json($result);
    } catch (Throwable $e) {
        Router::json(['error' => $e->getMessage()], 500);
    }
});

$router->get('/api/articles/{id}', function (array $params) {
    try {
        $repo = new ArticleRepository();
        $article = $repo->getById((int) $params['id']);

        if ($article === null) {
            Router::json(['error' => 'Статья не найдена'], 404);
        }

        Router::json(['data' => $article]);
    } catch (Throwable $e) {
        Router::json(['error' => $e->getMessage()], 500);
    }
});

$router->get('/api/stats', function () {
    try {
        $repo = new ArticleRepository();
        Router::json(['data' => $repo->stats()]);
    } catch (Throwable $e) {
        Router::json(['error' => $e->getMessage()], 500);
    }
});

$router->dispatch();
