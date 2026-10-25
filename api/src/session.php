<?php
/**
 * API session cookie (Slot C): HttpOnly, SameSite=Lax, Secure on HTTPS, strict mode
 * (rejects session IDs the server did not create), idle and absolute timeouts.
 */
declare(strict_types=1);

const SESSION_NAME = 'JOBNEST_API';
const SESSION_IDLE_SECONDS = 1800;      // 30 minutes without activity
const SESSION_ABSOLUTE_SECONDS = 28800; // 8 hours after login

/**
 * Start the session. With $create = false, a request without a session cookie
 * stays sessionless (guests browsing jobs do not get a session each time).
 */
function start_api_session(bool $create = true): bool {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }
    if (!$create && !isset($_COOKIE[SESSION_NAME])) {
        return false;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => config('COOKIE_SECURE', '0') === '1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $now = time();
    $last = $_SESSION['last_activity'] ?? $now;
    $started = $_SESSION['login_at'] ?? $now;
    if (isset($_SESSION['user_id']) && ($now - $last > SESSION_IDLE_SECONDS || $now - $started > SESSION_ABSOLUTE_SECONDS)) {
        destroy_api_session();
        session_start();
    }
    $_SESSION['last_activity'] = $now;
    return true;
}

/** New session ID after login or password change, to block session fixation. */
function rotate_api_session(): void {
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function destroy_api_session(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
        session_destroy();
    }
}
