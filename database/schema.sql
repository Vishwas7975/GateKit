-- ============================================================
--  Gatekit — Database Schema
--  Run this once against your MySQL server before first use.
--  Example: mysql -u root -p < database/schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS gatekit
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gatekit;

-- ── Users ────────────────────────────────────────────────────────────────
-- Minimal user model: only what register / login / Google OAuth /
-- password reset actually need.
CREATE TABLE IF NOT EXISTS users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100)  NOT NULL,
  email        VARCHAR(150)  UNIQUE NOT NULL,
  password     VARCHAR(255)  NULL,          -- NULL for Google-only accounts
  google_id    VARCHAR(255)  NULL,
  is_verified  TINYINT(1)    DEFAULT 0,     -- auto-true for Google sign-ins
  created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- ── Password Resets ─────────────────────────────────────────────────────────
-- Short-lived OTP codes used by the forgot-password / reset-password flow.
CREATE TABLE IF NOT EXISTS password_resets (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  email       VARCHAR(150) NOT NULL,
  otp         VARCHAR(6)   NOT NULL,
  expires_at  DATETIME     NOT NULL,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_password_resets_email (email)
);
