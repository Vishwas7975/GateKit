<?php

require_once __DIR__ . '/config/env.php';

// ─── CORS Headers ────────────────────────────────────────────────────────────
$frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');

header("Access-Control-Allow-Origin: {$frontendUrl}");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight (OPTIONS) request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ─── Autoload Composer packages (firebase/php-jwt, phpmailer) ───────────────
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Dependencies not installed. Run "composer install" first.']);
    exit();
}

// ─── Load Route Dispatcher ───────────────────────────────────────────────────
require_once __DIR__ . '/routes/api.php';
