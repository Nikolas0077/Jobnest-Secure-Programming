<?php
session_start();
header('Content-Type: application/json');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/api/auth/csrf') {
    $_SESSION['csrf'] ??= bin2hex(random_bytes(8));
    echo json_encode(['success' => true, 'data' => ['csrf_token' => $_SESSION['csrf']]]);
    return;
}
if ($path === '/api/auth/me') {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => ['message' => 'Unauthenticated']]);
        return;
    }
    echo json_encode(['success' => true, 'data' => ['user' => $_SESSION['user']]]);
    return;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_starts_with($path, '/api/auth/')) {
    if (($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') !== ($_SESSION['csrf'] ?? null)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => ['message' => 'CSRF rejected']]);
        return;
    }
    if ($path === '/api/auth/logout') {
        unset($_SESSION['user']);
        http_response_code(204);
        return;
    }
    $body = json_decode(file_get_contents('php://input'), true);
    if ($path === '/api/auth/register') {
        if (!is_array($body) || !isset($body['name'], $body['email'], $body['password'], $body['role']) || isset($body['password_confirmation'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => ['message' => 'Invalid registration body']]);
            return;
        }
        http_response_code(201);
        echo json_encode(['success' => true, 'data' => ['user' => ['id' => 1, 'role' => $body['role']]]]);
        return;
    }
    if ($path === '/api/auth/login') {
        if (!is_array($body) || array_keys($body) !== ['email', 'password']) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => ['message' => 'Invalid login body']]);
            return;
        }
        $_SESSION['user'] = ['id' => 1, 'name' => 'Test User', 'email' => $body['email'], 'role' => 'applicant'];
        echo json_encode(['success' => true, 'data' => ['user' => $_SESSION['user']]]);
        return;
    }
}
if ($path === '/api/jobs') {
    echo json_encode(['success' => true, 'data' => ['items' => [[
        'id' => 1, 'title' => '<script>alert(1)</script>', 'company_name' => 'ACME',
        'location' => 'Jakarta', 'category' => 'IT', 'salary_min' => 1000000,
        'salary_max' => 2000000, 'deadline' => '2026-12-31',
    ]], 'pagination' => ['page' => 1, 'per_page' => 10, 'total' => 1, 'total_pages' => 1]]]);
    return;
}
if ($path === '/api/jobs/1') {
    echo json_encode(['success' => true, 'data' => [
        'id' => 1, 'title' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(1)>',
        'company' => ['id' => 1, 'name' => 'ACME'], 'location' => 'Jakarta', 'category' => 'IT',
    ]]);
    return;
}
http_response_code(404);
echo json_encode(['success' => false, 'error' => ['message' => 'Not found']]);
