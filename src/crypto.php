<?php
declare(strict_types=1);

// Password and token helpers. Keep these in one place so login and reset stay in sync.

const RESET_TOKEN_TTL_MINUTES = 60;

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

// Rows created before the bcrypt switch hold a bare SHA-256 hex digest.
function is_legacy_hash(string $stored): bool
{
    return preg_match('/^[0-9a-f]{64}$/', $stored) === 1;
}

function verify_password(string $password, string $stored): bool
{
    if (is_legacy_hash($stored)) {
        return hash_equals($stored, hash("sha256", $password));
    }
    return password_verify($password, $stored);
}

function password_needs_upgrade(string $stored): bool
{
    return is_legacy_hash($stored) || password_needs_rehash($stored, PASSWORD_DEFAULT);
}

// Tokens are random and only their digest is stored, so a database read does not yield a usable link.
function issue_reset_token(string $email): string
{
    $token = bin2hex(random_bytes(32));
    $pdo = db();
    $pdo->prepare("DELETE FROM reset_tokens WHERE email = ?")->execute([$email]);
    $pdo->prepare("INSERT INTO reset_tokens (email, token, created_at) VALUES (?, ?, datetime('now'))")
        ->execute([$email, hash("sha256", $token)]);
    return $token;
}

function consume_reset_token(string $email, string $token): bool
{
    $pdo = db();
    $row = $pdo->prepare(
        "SELECT token FROM reset_tokens WHERE email = ? AND created_at > datetime('now', ?)"
    );
    $row->execute([$email, "-" . RESET_TOKEN_TTL_MINUTES . " minutes"]);
    $stored = $row->fetchColumn();
    if (!$stored || !hash_equals((string)$stored, hash("sha256", $token))) {
        return false;
    }
    // Single use.
    $pdo->prepare("DELETE FROM reset_tokens WHERE email = ?")->execute([$email]);
    return true;
}
