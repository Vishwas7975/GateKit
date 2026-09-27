<?php

require_once __DIR__ . '/../helpers/JWTHelper.php';

/**
 * requireAuth()
 * Call at the top of any controller method that needs a logged-in user.
 * Returns the decoded access-token payload (user_id, etc.) on success.
 * Sends 401 JSON and exits on failure.
 */
function requireAuth(): array
{
    $token = JWTHelper::getBearerToken();

    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'No token provided. Please log in.']);
        exit();
    }

    $payload = JWTHelper::decode($token);

    if (!$payload || ($payload['type'] ?? '') !== 'access') {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token. Please log in again.']);
        exit();
    }

    return $payload;
}
