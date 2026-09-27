<?php

require_once __DIR__ . '/../config/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JWTHelper
 * Thin wrapper around firebase/php-jwt for issuing and verifying
 * short-lived access tokens and long-lived refresh tokens.
 */
class JWTHelper
{
    // ── Generate Access Token (short-lived) ──────────────────────────────
    public static function generateAccessToken(int $userId): string
    {
        $payload = [
            'iss'     => APP_URL,
            'iat'     => time(),
            'exp'     => time() + JWT_EXPIRY,
            'user_id' => $userId,
            'type'    => 'access',
        ];

        return JWT::encode($payload, JWT_SECRET, 'HS256');
    }

    // ── Generate Refresh Token (long-lived) ──────────────────────────────
    public static function generateRefreshToken(int $userId): string
    {
        $payload = [
            'iss'     => APP_URL,
            'iat'     => time(),
            'exp'     => time() + JWT_REFRESH_EXPIRY,
            'user_id' => $userId,
            'type'    => 'refresh',
        ];

        return JWT::encode($payload, JWT_SECRET, 'HS256');
    }

    // ── Decode & Verify Token ──────────────────────────────────────────────
    public static function decode(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    // ── Extract Bearer Token from Authorization Header ─────────────────────
    public static function getBearerToken(): ?string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
