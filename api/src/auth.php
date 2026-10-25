<?php
/**
 * Authentication helpers (Slot C).
 *   $user = require_login();                 // 401 if not logged in or account disabled
 *   $user = require_role('company');         // 403 for any other role
 *   $company = require_company();            // company row resolved from the session user
 * The user is reloaded from the database on every request, so a suspended account or a
 * changed role takes effect immediately. Never read role/user_id/company_id from the request.
 */
declare(strict_types=1);

const ROLES = ['applicant', 'company', 'admin'];

function public_user(array $row): array {
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'role' => $row['role'],
        'is_active' => (bool) $row['is_active'],
        'email_verified' => $row['email_verified_at'] !== null,
    ];
}

/** Short fingerprint of the stored hash; changes whenever the password changes. */
function password_fingerprint(string $hash): string {
    return hash('sha256', $hash);
}

function current_user(): ?array {
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    if (!start_api_session(false) || !is_int($_SESSION['user_id'] ?? null)) {
        return $cached = null;
    }
    $row = db_one('SELECT id, name, email, role, is_active, email_verified_at, password_hash FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if ($row === null || (int) $row['is_active'] !== 1
        || !hash_equals(password_fingerprint($row['password_hash']), (string) ($_SESSION['pw_fp'] ?? ''))) {
        destroy_api_session();
        return $cached = null;
    }
    unset($row['password_hash']);
    return $cached = $row;
}

function require_login(): array {
    $user = current_user();
    if ($user === null) {
        json_error(401, 'UNAUTHENTICATED', 'Silakan masuk terlebih dahulu.');
    }
    return $user;
}

function require_role(string ...$roles): array {
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        log_activity('access.denied', (int) $user['id']);
        json_error(403, 'FORBIDDEN', 'Anda tidak memiliki akses ke fitur ini.');
    }
    return $user;
}

function require_guest(): void {
    if (current_user() !== null) {
        json_error(409, 'ALREADY_AUTHENTICATED', 'Anda sudah masuk.');
    }
}

/** The company owned by the logged-in company user (ownership via companies.user_id). */
function require_company(): array {
    $user = require_role('company');
    $company = db_one('SELECT * FROM companies WHERE user_id = ?', [$user['id']]);
    if ($company === null) {
        json_error(403, 'FORBIDDEN', 'Profil perusahaan tidak ditemukan.');
    }
    return $company;
}

/* ---------- Login throttling ---------- */

const LOGIN_WINDOW_MINUTES = 15;
const LOGIN_MAX_PER_EMAIL_IP = 5;
const LOGIN_MAX_PER_IP = 20;

function login_email_hash(string $email): string {
    return hash('sha256', mb_strtolower($email));
}

function login_is_throttled(string $email, string $ip): bool {
    $byPair = db_one(
        'SELECT COUNT(*) AS n FROM login_attempts WHERE email_hash = ? AND ip_address = ? AND success = 0 AND attempted_at >= NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE',
        [login_email_hash($email), $ip]
    );
    if (client_ip_unknown()) {
        return (int) $byPair['n'] >= LOGIN_MAX_PER_EMAIL_IP;
    }
    $byIp = db_one(
        'SELECT COUNT(*) AS n FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempted_at >= NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE',
        [$ip]
    );
    return (int) $byPair['n'] >= LOGIN_MAX_PER_EMAIL_IP || (int) $byIp['n'] >= LOGIN_MAX_PER_IP;
}

function record_login_attempt(string $email, string $ip, bool $success): void {
    db_query('INSERT INTO login_attempts (ip_address, email_hash, success) VALUES (?, ?, ?)', [$ip, login_email_hash($email), $success ? 1 : 0]);
    if ($success) {
        // A successful login clears earlier failures for this email + IP.
        db_query('DELETE FROM login_attempts WHERE email_hash = ? AND ip_address = ? AND success = 0', [login_email_hash($email), $ip]);
    }
}
