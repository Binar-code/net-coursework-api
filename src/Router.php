<?php

class Router
{
    private $routes = [];

    public function get($path, $handler)
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post($path, $handler)
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = rtrim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        if (isset($this->routes[$method][$uri])) {
            $this->routes[$method][$uri]([]);
            return;
        }

        $methodRoutes = isset($this->routes[$method]) ? $this->routes[$method] : [];
        foreach ($methodRoutes as $route => $handler) {
            $pattern = preg_replace('#\{(\w+)}#', '(?P<$1>[^/]+)', $route);
            $pattern = "#^{$pattern}$#";

            if (preg_match($pattern, $uri, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }
                $handler($params);
                return;
            }
        }

        self::json(['error' => 'Не найдено', 'available_endpoints' => [
            'GET|POST /api/fetch' => 'Загрузить RSS ленту и сохранить в БД',
            'GET  /api/articles' => 'Список статей (с фильтрами, сортировкой, постраничной выдачей)',
            'GET  /api/articles/{id}' => 'Получить одну статью',
            'GET  /api/stats' => 'Статистика БД',
        ]], 404);
    }

    public static function json($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
