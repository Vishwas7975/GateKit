# 🔐 Gatekit

**A minimal, standalone PHP authentication starter kit** — registration, login, Google OAuth and OTP-based password reset, ready to drop into any project.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-PDO-4479A1?logo=mysql&logoColor=white)
![JWT](https://img.shields.io/badge/Auth-JWT-000000?logo=jsonwebtokens&logoColor=white)
![Composer](https://img.shields.io/badge/Composer-ready-885630?logo=composer&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green.svg)
![PRs](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)

Gatekit is a stripped-down, framework-free PHP backend that does exactly one thing well: **authentication**. It was extracted from a larger production project and pared down to only the auth-related code, so it's small enough to read end-to-end in a single sitting and clean enough to build on top of for your next project.

---

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Getting Started](#getting-started)
- [API Reference](#api-reference)
- [Authentication Design](#authentication-design)
- [Skills Demonstrated](#skills-demonstrated)
- [Security & Privacy](#security--privacy)
- [Verification](#verification)
- [Roadmap](#roadmap)
- [Contributing](#contributing)
- [Author](#author)
- [License](#license)

---

## Features

- ✅ **Email/password registration & login** — bcrypt password hashing (cost 12)
- ✅ **JWT authentication** — short-lived access tokens + long-lived HttpOnly-cookie refresh tokens, with a `/refresh` endpoint for silent renewal
- ✅ **Google OAuth 2.0** — one-click sign-in/sign-up, auto-verifies the account, and links to an existing email/password account if one exists
- ✅ **Forgot / reset password** — 6-digit OTP emailed via SMTP, 10-minute expiry, constant-time comparison, and enumeration-safe responses (never reveals whether an email is registered)
- ✅ **Route-guard middleware** — a single `requireAuth()` helper protects any endpoint you add later
- ✅ **Zero framework** — plain PHP, PDO, and two small Composer packages
- ✅ **Env-driven config** — nothing sensitive is hardcoded; everything sensitive lives in `.env`

---

## Tech Stack

| Layer          | Technology                                                                |
|----------------|----------------------------------------------------------------------------|
| Language       | PHP 8.0+                                                                    |
| Database       | MySQL (via PDO, prepared statements throughout)                            |
| Authentication | JWT (`firebase/php-jwt`)                                                   |
| OAuth          | Google OAuth 2.0                                                            |
| Email          | PHPMailer + SMTP                                                            |
| Dependency Mgr | Composer                                                                     |
| Web server     | Apache (`.htaccess` included) — also runs on Nginx or the PHP built-in server |

---

## Project Structure

```
gatekit/
├── config/
│   ├── env.php             ← tiny dependency-free .env loader
│   ├── config.php          ← app, JWT, Google, and mail settings (all from env)
│   └── db.php               ← PDO connection singleton
├── controllers/
│   └── AuthController.php   ← register, login, logout, refresh, me,
│                               Google OAuth, forgot/reset password
├── helpers/
│   ├── JWTHelper.php        ← issue / verify access & refresh tokens
│   ├── GoogleOAuth.php      ← Google OAuth 2.0 flow (3-step)
│   └── Mailer.php            ← PHPMailer SMTP wrapper + OTP email template
├── middleware/
│   └── auth.php               ← requireAuth() — protect any route with a JWT
├── routes/
│   └── api.php                 ← URL → controller dispatcher
├── database/
│   └── schema.sql               ← 2-table schema (users, password_resets)
├── vendor/                       ← Composer packages (regenerate with `composer install`)
├── index.php                      ← entry point — CORS, autoload, router
├── .htaccess                       ← Apache rewrite: all requests → index.php
├── .env.example                     ← copy to .env and fill in
├── composer.json
└── LICENSE
```

---

## Getting Started

### Prerequisites

- PHP 8.0+
- MySQL (or MariaDB)
- [Composer](https://getcomposer.org/)

### 1. Install dependencies

```bash
composer install
```

Installs:
- `firebase/php-jwt` — JWT signing & verification
- `phpmailer/phpmailer` — SMTP email delivery

### 2. Create the database

```bash
mysql -u root -p < database/schema.sql
```

This creates the `gatekit` database with two tables: `users` and `password_resets`.

### 3. Configure environment variables

```bash
cp .env.example .env
```

Then edit `.env`:

| Variable | Description |
|---|---|
| `APP_URL` | Public URL this API is served from |
| `FRONTEND_URL` | URL of the client app (CORS origin + OAuth redirect target) |
| `DB_HOST` / `DB_PORT` / `DB_NAME` / `DB_USER` / `DB_PASS` | MySQL connection |
| `JWT_SECRET` | **Required.** Long random string — generate with `php -r "echo bin2hex(random_bytes(32));"` |
| `JWT_EXPIRY` / `JWT_REFRESH_EXPIRY` | Token lifespans in seconds |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | From Google Cloud Console (see below) |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_ENCRYPTION` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | SMTP settings for the OTP email |

> ⚠️ The app refuses to boot if `JWT_SECRET` is left empty — this is intentional, not a bug.

### 4. Set up Google OAuth (Cloud Console)

1. Go to [console.cloud.google.com](https://console.cloud.google.com/) → create or select a project.
2. **APIs & Services → OAuth consent screen** → configure it (External is fine for testing) and add your own email as a test user.
3. **APIs & Services → Credentials → Create Credentials → OAuth client ID**.
4. Application type: **Web application**.
5. Under **Authorized redirect URIs**, add exactly the same value you put in `GOOGLE_REDIRECT_URI`, e.g. `http://localhost:8000/api/auth/google/callback`.
6. Copy the generated **Client ID** and **Client secret** into `.env` as `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`.

### 5. Run it

With the PHP built-in server:

```bash
php -S localhost:8000
```

Or drop the folder into your Apache/XAMPP `htdocs` and set `APP_BASE_PATH` in `.env` if it's not served from the domain root.

---

## API Reference

Base URL: whatever you set `APP_URL` to (e.g. `http://localhost:8000`).
All responses are JSON. Protected routes require an `Authorization: Bearer <access_token>` header.

| Method | Endpoint | Auth | Description |
|---|---|:---:|---|
| `POST` | `/api/auth/register` | – | Create an account |
| `POST` | `/api/auth/login` | – | Log in with email + password |
| `GET`  | `/api/auth/me` | ✅ | Get the current user's profile |
| `POST` | `/api/auth/logout` | – | Clear the refresh cookie |
| `POST` | `/api/auth/refresh` | cookie | Rotate the refresh token, issue a new access token |
| `GET`  | `/api/auth/google` | – | Get the Google OAuth consent URL |
| `GET`  | `/api/auth/google/callback` | – | Google redirects here after consent |
| `POST` | `/api/auth/forgot-password` | – | Email a 6-digit OTP reset code |
| `POST` | `/api/auth/reset-password` | – | Redeem the OTP and set a new password |

### `POST /api/auth/register`
```json
{ "name": "Jane Doe", "email": "jane@example.com", "password": "secret123" }
```
**201 Created**
```json
{
  "message": "Account created successfully.",
  "access_token": "<jwt>",
  "user": { "id": 1, "name": "Jane Doe", "email": "jane@example.com" }
}
```
Also sets a `refresh_token` HttpOnly cookie. **409** if the email is already registered, **400** for a missing/invalid field.

### `POST /api/auth/login`
```json
{ "email": "jane@example.com", "password": "secret123" }
```
**200 OK** → same shape as register, plus the `refresh_token` cookie. **401** for a wrong password or unknown email — both return the identical generic error, so a client can't distinguish "wrong password" from "no such account."

### `GET /api/auth/me` *(protected)*
No body. **200 OK**
```json
{
  "message": "User profile.",
  "user": { "id": 1, "name": "Jane Doe", "email": "jane@example.com", "is_verified": 0, "created_at": "2026-01-01 12:00:00" }
}
```
**401** if the bearer token is missing, malformed, or expired.

### `POST /api/auth/logout`
No body. Clears the `refresh_token` cookie. **200 OK**.

### `POST /api/auth/refresh`
No body — reads the `refresh_token` cookie. **200 OK** → `{ "message": "...", "access_token": "<new_jwt>" }`, and rotates the refresh cookie. **401** if the cookie is missing, invalid, or not a refresh-type token.

### `GET /api/auth/google`
**200 OK** → `{ "message": "...", "url": "https://accounts.google.com/o/oauth2/auth?..." }` — redirect the browser here to start the flow.

### `GET /api/auth/google/callback?code=<auth_code>`
Called by Google, not by your frontend directly. Exchanges the code, upserts the user (auto-verified, and links to an existing email/password account by email if one exists), issues JWTs, and redirects to `FRONTEND_URL/oauth/callback?token=<access_token>`. **400** if `code` is missing.

### `POST /api/auth/forgot-password`
```json
{ "email": "jane@example.com" }
```
Generates a 6-digit OTP (10-minute expiry) and emails it if the address is registered. **200 OK** → `{ "message": "If that email is registered, a reset code has been sent." }` — always this exact message, whether or not the email exists.

### `POST /api/auth/reset-password`
```json
{ "email": "jane@example.com", "otp": "123456", "new_password": "newSecret123" }
```
Validates the OTP (constant-time comparison) and its expiry, updates the password, and deletes the used OTP so it can't be replayed. **200 OK** → `{ "message": "Password reset successfully. You can now log in with your new password." }`. **400** for a wrong/expired/malformed OTP.

---

## Authentication Design

| Token         | Lifespan | Storage          | Use                                          |
|---------------|----------|-------------------|-----------------------------------------------|
| Access Token  | 1 hour (default) | Client memory (JS) | `Authorization: Bearer` header on protected requests |
| Refresh Token | 7 days (default)  | HttpOnly cookie     | Silent renewal via `POST /api/auth/refresh` |

- Both lifespans are configurable via `JWT_EXPIRY` / `JWT_REFRESH_EXPIRY` in `.env`.
- Passwords are hashed with **bcrypt** (cost 12); Google-only accounts have a `NULL` password column.
- OTPs live in a dedicated `password_resets` table and are deleted immediately after a successful reset or a failed send, so stale codes never linger.

---

## Skills Demonstrated

- Framework-free PHP architecture: routing, controllers, middleware, and config layers built from scratch
- Stateless authentication with JWT access/refresh token rotation
- Secure password handling — bcrypt hashing, constant-time OTP comparison, enumeration-safe responses
- Third-party OAuth 2.0 integration (Google), including account linking by email
- Transactional email delivery via SMTP (PHPMailer)
- Environment-based configuration and secrets hygiene (`.env` / `.gitignore` discipline)
- PDO with prepared statements throughout — no raw string interpolation into SQL, anywhere

---

## Security & Privacy

- **`.env` is gitignored.** All secrets (JWT secret, DB credentials, Google OAuth client ID/secret, SMTP credentials) live only in your local `.env`, never in source control. `.env.example` ships with placeholders only.
- **`/vendor/` is gitignored.** Dependencies are restored with `composer install`, not committed.
- **Logs and cache directories are gitignored** so nothing generated at runtime (which could contain request data or stack traces) ends up in version control.
- **No real user data ships with this kit.** The schema creates empty tables only — there is no seed data and no sample accounts.
- **Database errors are not echoed to the client** — connection failures return a generic message, with details only in the server's own error log.
- **Forgot-password responses are identical whether or not the email is registered**, to prevent account enumeration.
- **OTP comparison uses `hash_equals()`** (constant-time) to avoid timing attacks on the reset code.
- **`JWT_SECRET` has no default and the app refuses to start without one**, so it can't accidentally run with a weak, well-known key.

Before deploying: set a strong `JWT_SECRET`, use your own Google OAuth credentials and SMTP account, enable `secure` on the refresh cookie once you're on HTTPS (see the commented line in `AuthController::setRefreshCookie()`), and point `DB_*` at your own database.

---

## Roadmap

Ideas for extending this kit further:

- [ ] Email verification flow (send a confirmation link on register, gate `/me` on `is_verified`)
- [ ] Rate limiting on login and forgot-password to slow down brute-force / OTP-spam attempts
- [ ] Optional 2FA (TOTP) as a second factor after password login
- [ ] Refresh-token revocation list (for "log out of all devices")
- [ ] Additional OAuth providers (GitHub, Apple) behind the same account-linking pattern used for Google

---

## Contributing

Issues and pull requests are welcome. If you're proposing a change:

1. Fork the repo and create a feature branch.
2. Keep changes scoped — this kit's whole value is staying small and readable.
3. Match the existing style (no framework, PDO + prepared statements, env-driven config).
4. Open a PR describing what changed and why.

---
 
## Author
 
**Vishwas S** Full-Stack Developer | Data Analyst
 
- GitHub: [github.com/Vishwas7975](https://github.com/Vishwas7975)
- LinkedIn: [linkedin.com/in/vishwas-s-48205327a](https://www.linkedin.com/in/vishwas-s-48205327a)
---
 
## License
 
MIT — see [LICENSE](LICENSE).
