<?php

require_once __DIR__ . '/env.php';

// ─── App Settings ──────────────────────────────────────────────────────────
define('APP_NAME', env('APP_NAME', 'Gatekit'));
define('APP_URL', rtrim(env('APP_URL', 'http://localhost:8000'), '/'));
define('APP_BASE_PATH', trim(env('APP_BASE_PATH', ''), '/') === '' ? '' : '/' . trim(env('APP_BASE_PATH', ''), '/'));
define('FRONTEND_URL', rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/'));

// ─── JWT Configuration ─────────────────────────────────────────────────────
// JWT_SECRET has no default on purpose — the app refuses to boot without one.
define('JWT_SECRET', env('JWT_SECRET', ''));
define('JWT_EXPIRY', (int) env('JWT_EXPIRY', 3600));            // Access token lifespan (seconds)
define('JWT_REFRESH_EXPIRY', (int) env('JWT_REFRESH_EXPIRY', 604800)); // Refresh token lifespan (seconds)

if (JWT_SECRET === '' || JWT_SECRET === null) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'JWT_SECRET is not configured. Copy .env.example to .env and set a strong random value.',
    ]);
    exit();
}

// ─── Google OAuth ──────────────────────────────────────────────────────────
define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URI', env('GOOGLE_REDIRECT_URI', APP_URL . APP_BASE_PATH . '/api/auth/google/callback'));

// ─── Mail / SMTP ────────────────────────────────────────────────────────────
define('MAIL_HOST', env('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT', (int) env('MAIL_PORT', 465));
define('MAIL_ENCRYPTION', env('MAIL_ENCRYPTION', 'smtps')); // 'smtps' (port 465) or 'tls' (port 587)
define('MAIL_USERNAME', env('MAIL_USERNAME', ''));
define('MAIL_PASSWORD', env('MAIL_PASSWORD', ''));
define('MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', MAIL_USERNAME));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', APP_NAME));
