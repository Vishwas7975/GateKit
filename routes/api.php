<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// ─── Parse the request ──────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$path = APP_BASE_PATH !== '' ? str_replace(APP_BASE_PATH, '', $uri) : $uri;
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

// ─── Route Table ─────────────────────────────────────────────────────────────
$routes = [
    ['POST', '/api/auth/register',        'AuthController', 'register'],
    ['POST', '/api/auth/login',           'AuthController', 'login'],
    ['POST', '/api/auth/logout',          'AuthController', 'logout'],
    ['POST', '/api/auth/refresh',         'AuthController', 'refresh'],
    ['GET',  '/api/auth/me',              'AuthController', 'me'],
    ['GET',  '/api/auth/google',          'AuthController', 'googleRedirect'],
    ['GET',  '/api/auth/google/callback', 'AuthController', 'googleCallback'],
    ['POST', '/api/auth/forgot-password', 'AuthController', 'forgotPassword'],
    ['POST', '/api/auth/reset-password',  'AuthController', 'resetPassword'],
];

// ─── Dispatcher ──────────────────────────────────────────────────────────────
$matched = false;

foreach ($routes as [$routeMethod, $routePattern, $controllerClass, $controllerMethod]) {

    if ($method !== $routeMethod || $path !== $routePattern) {
        continue;
    }

    $matched = true;

    $file = __DIR__ . "/../controllers/{$controllerClass}.php";
    if (!file_exists($file)) {
        http_response_code(500);
        echo json_encode(['error' => "Controller file not found: {$controllerClass}.php"]);
        exit();
    }
    require_once $file;

    call_user_func([$controllerClass, $controllerMethod]);
    exit();
}

// ─── 404 ───────────────────────────────────────────────────────────────────
if (!$matched) {
    http_response_code(404);
    echo json_encode([
        'error'  => 'Route not found.',
        'method' => $method,
        'path'   => $path,
    ]);
}
