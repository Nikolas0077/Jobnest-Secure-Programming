<?php
/**
 * Minimal router (Slot C). Route params like {id} only match positive integers,
 * so IDs reach handlers as int. Every POST/PATCH/DELETE is CSRF-checked here,
 * before any handler runs, so a new route cannot forget it.
 *
 *   route('GET', '/jobs/{id}', 'jobs_show');   // function jobs_show(array $params)
 */
declare(strict_types=1);

function route(string $method, string $pattern, callable $handler): void {
    $GLOBALS['jobnest_routes'][] = [$method, $pattern, $handler];
}

function dispatch(string $method, string $uri): never {
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || !str_starts_with($path, '/api/')) {
        json_error(404, 'NOT_FOUND', 'Endpoint tidak ditemukan.');
    }
    $path = rtrim(substr($path, 4), '/') ?: '/';
    $allowed = [];
    foreach ($GLOBALS['jobnest_routes'] ?? [] as [$routeMethod, $pattern, $handler]) {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[1-9][0-9]{0,9})', $pattern) . '$#';
        if (!preg_match($regex, $path, $matches)) {
            continue;
        }
        if ($routeMethod !== $method) {
            $allowed[] = $routeMethod;
            continue;
        }
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = (int) $value;
            }
        }
        if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            require_csrf();
        }
        $handler($params);
        json_error(500, 'SERVER_ERROR', 'Handler tidak mengirim respons.');
    }
    if ($allowed !== []) {
        header('Allow: ' . implode(', ', array_unique($allowed)));
        json_error(405, 'METHOD_NOT_ALLOWED', 'Metode tidak diizinkan.');
    }
    json_error(404, 'NOT_FOUND', 'Endpoint tidak ditemukan.');
}
