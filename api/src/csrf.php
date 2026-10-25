<?php
/**
 * CSRF for every POST/PATCH/DELETE (Slot C). Token comes from GET /api/auth/csrf and
 * must be sent back in the X-CSRF-Token header. Compared with hash_equals().
 */
declare(strict_types=1);

function csrf_token(): string {
    start_api_session();
    if (!is_string($_SESSION['csrf_token'] ?? null)) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf(): void {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $hasSession = start_api_session(false);
    $expected = $hasSession ? ($_SESSION['csrf_token'] ?? null) : null;
    if (!is_string($sent) || $sent === '' || !is_string($expected) || !hash_equals($expected, $sent)) {
        json_error(403, 'CSRF_REJECTED', 'Token keamanan tidak valid. Muat ulang halaman.');
    }
}
