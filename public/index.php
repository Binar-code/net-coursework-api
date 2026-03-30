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

$router->post('/api/fetch', function () {
    try {
        $fetcher = new RssFetcher();
        $result = $fetcher->fetch();
        Router::json($result);
    } catch (Throwable $e) {
        Router::json(['error' => $e->getMessage()], 500);
    }
});

$router->get('/api/fetch', function () {
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

$router->get('/api/articles/{id}', function ($params) {
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
