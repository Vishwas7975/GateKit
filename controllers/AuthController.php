<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/JWTHelper.php';
require_once __DIR__ . '/../middleware/auth.php';

class AuthController
{
    // ── POST /api/auth/register ─────────────────────────────────────────────
    public static function register(): void
    {
        $data = self::getBody();

        foreach (['name', 'email', 'password'] as $field) {
            if (empty($data[$field])) {
                self::error(400, "Field '{$field}' is required.");
            }
        }

        $name     = trim($data['name']);
        $email    = strtolower(trim($data['email']));
        $password = $data['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::error(400, 'Invalid email format.');
        }

        if (strlen($password) < 6) {
            self::error(400, 'Password must be at least 6 characters.');
        }

        $db = getDB();

        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            self::error(409, 'An account with this email already exists.');
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $db->prepare('
            INSERT INTO users (name, email, password, is_verified)
            VALUES (?, ?, ?, 0)
        ');
        $stmt->execute([$name, $email, $hashed]);
        $userId = (int) $db->lastInsertId();

        $accessToken  = JWTHelper::generateAccessToken($userId);
        $refreshToken = JWTHelper::generateRefreshToken($userId);

        self::setRefreshCookie($refreshToken);

        self::success(201, 'Account created successfully.', [
            'access_token' => $accessToken,
            'user' => [
                'id'    => $userId,
                'name'  => $name,
                'email' => $email,
            ],
        ]);
    }

    // ── POST /api/auth/login ────────────────────────────────────────────────
    public static function login(): void
    {
        $data  = self::getBody();
        $email = strtolower(trim($data['email'] ?? ''));
        $pass  = $data['password'] ?? '';

        if (!$email || !$pass) {
            self::error(400, 'Email and password are required.');
        }

        $db   = getDB();
        $stmt = $db->prepare('SELECT id, name, email, password FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !$user['password'] || !password_verify($pass, $user['password'])) {
            self::error(401, 'Invalid email or password.');
        }

        $accessToken  = JWTHelper::generateAccessToken($user['id']);
        $refreshToken = JWTHelper::generateRefreshToken($user['id']);

        self::setRefreshCookie($refreshToken);

        self::success(200, 'Login successful.', [
            'access_token' => $accessToken,
            'user' => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
            ],
        ]);
    }

    // ── GET /api/auth/me ────────────────────────────────────────────────────
    public static function me(): void
    {
        $payload = requireAuth();
        $db      = getDB();

        $stmt = $db->prepare('SELECT id, name, email, is_verified, created_at FROM users WHERE id = ?');
        $stmt->execute([$payload['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            self::error(404, 'User not found.');
        }

        self::success(200, 'User profile.', ['user' => $user]);
    }

    // ── POST /api/auth/logout ───────────────────────────────────────────────
    public static function logout(): void
    {
        setcookie('refresh_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        self::success(200, 'Logged out successfully.');
    }

    // ── POST /api/auth/refresh ──────────────────────────────────────────────
    public static function refresh(): void
    {
        $refreshToken = $_COOKIE['refresh_token'] ?? null;

        if (!$refreshToken) {
            self::error(401, 'No refresh token found.');
        }

        $payload = JWTHelper::decode($refreshToken);

        if (!$payload || ($payload['type'] ?? '') !== 'refresh') {
            self::error(401, 'Invalid or expired refresh token. Please log in again.');
        }

        $db   = getDB();
        $stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([$payload['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            self::error(401, 'User not found.');
        }

        $newAccess  = JWTHelper::generateAccessToken($user['id']);
        $newRefresh = JWTHelper::generateRefreshToken($user['id']);
        self::setRefreshCookie($newRefresh);

        self::success(200, 'Token refreshed.', ['access_token' => $newAccess]);
    }

    // ── GET /api/auth/google ────────────────────────────────────────────────
    public static function googleRedirect(): void
    {
        require_once __DIR__ . '/../helpers/GoogleOAuth.php';
        $url = GoogleOAuth::getAuthUrl();
        self::success(200, 'Google auth URL generated.', ['url' => $url]);
    }

    // ── GET /api/auth/google/callback ───────────────────────────────────────
    public static function googleCallback(): void
    {
        require_once __DIR__ . '/../helpers/GoogleOAuth.php';

        $code = $_GET['code'] ?? null;
        if (!$code) {
            self::error(400, 'Authorization code missing.');
        }

        $accessToken = GoogleOAuth::getAccessToken($code);
        if (!$accessToken) {
            self::error(500, 'Failed to get access token from Google.');
        }

        $googleUser = GoogleOAuth::getUserInfo($accessToken);
        if (!$googleUser || empty($googleUser['email'])) {
            self::error(500, 'Failed to get user info from Google.');
        }

        $db    = getDB();
        $email = strtolower($googleUser['email']);

        $stmt = $db->prepare('SELECT id, name, email FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $stmt = $db->prepare('
                INSERT INTO users (name, email, google_id, is_verified)
                VALUES (?, ?, ?, 1)
            ');
            $stmt->execute([
                $googleUser['name'] ?? $email,
                $email,
                $googleUser['id'],
            ]);
            $userId = (int) $db->lastInsertId();
        } else {
            $userId = $user['id'];

            // Backfill google_id for an existing email/password account that
            // signs in with Google for the first time.
            $db->prepare('UPDATE users SET google_id = ? WHERE id = ? AND google_id IS NULL')
               ->execute([$googleUser['id'], $userId]);
        }

        $jwtAccess  = JWTHelper::generateAccessToken($userId);
        $jwtRefresh = JWTHelper::generateRefreshToken($userId);
        self::setRefreshCookie($jwtRefresh);

        header('Location: ' . FRONTEND_URL . '/oauth/callback?token=' . urlencode($jwtAccess));
        exit();
    }

    // ── POST /api/auth/forgot-password ──────────────────────────────────────
    //
    //  Body: { "email": "user@example.com" }
    //
    //  • Checks the email exists in the users table
    //  • Deletes any previous unused OTP for that email
    //  • Generates a 6-digit OTP, stores it with a 10-minute expiry
    //  • Sends the OTP via PHPMailer / SMTP
    //
    public static function forgotPassword(): void
    {
        $data  = self::getBody();
        $email = strtolower(trim($data['email'] ?? ''));

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::error(400, 'A valid email address is required.');
        }

        $db = getDB();

        // Always return the same generic message below, whether or not the
        // email is registered, to avoid leaking which emails have accounts.
        $stmt = $db->prepare('SELECT id, name FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $db->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);

            $otp       = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date('Y-m-d H:i:s', time() + 600); // +10 minutes

            $stmt = $db->prepare('INSERT INTO password_resets (email, otp, expires_at) VALUES (?, ?, ?)');
            $stmt->execute([$email, $otp, $expiresAt]);

            require_once __DIR__ . '/../helpers/Mailer.php';
            $sent = Mailer::sendOtp($email, $user['name'], $otp);

            if (!$sent) {
                $db->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);
                self::error(500, 'Failed to send reset email. Please try again.');
            }
        }

        self::success(200, 'If that email is registered, a reset code has been sent.');
    }

    // ── POST /api/auth/reset-password ───────────────────────────────────────
    //
    //  Body: { "email": "user@example.com", "otp": "123456", "new_password": "secret" }
    //
    public static function resetPassword(): void
    {
        $data        = self::getBody();
        $email       = strtolower(trim($data['email'] ?? ''));
        $otp         = trim($data['otp'] ?? '');
        $newPassword = $data['new_password'] ?? '';

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::error(400, 'A valid email address is required.');
        }
        if (!preg_match('/^\d{6}$/', $otp)) {
            self::error(400, 'OTP must be a 6-digit number.');
        }
        if (strlen($newPassword) < 6) {
            self::error(400, 'New password must be at least 6 characters.');
        }

        $db = getDB();

        $stmt = $db->prepare('SELECT otp, expires_at FROM password_resets WHERE email = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$email]);
        $record = $stmt->fetch();

        if (!$record) {
            self::error(400, 'No reset request found for this email. Please request a new code.');
        }

        if (new DateTime() > new DateTime($record['expires_at'])) {
            $db->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);
            self::error(400, 'Your reset code has expired. Please request a new one.');
        }

        // Constant-time comparison to prevent timing attacks
        if (!hash_equals($record['otp'], $otp)) {
            self::error(400, 'Invalid OTP code. Please check your email and try again.');
        }

        $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt   = $db->prepare('UPDATE users SET password = ? WHERE email = ?');
        $stmt->execute([$hashed, $email]);

        $db->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);

        self::success(200, 'Password reset successfully. You can now log in with your new password.');
    }

    // ─── Private Helpers ────────────────────────────────────────────────────

    private static function getBody(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    private static function setRefreshCookie(string $token): void
    {
        setcookie('refresh_token', $token, [
            'expires'  => time() + JWT_REFRESH_EXPIRY,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            // 'secure' => true, // enable once served over HTTPS in production
        ]);
    }

    private static function success(int $code, string $message, array $data = []): void
    {
        http_response_code($code);
        echo json_encode(array_merge(['message' => $message], $data));
        exit();
    }

    private static function error(int $code, string $message): void
    {
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit();
    }
}
