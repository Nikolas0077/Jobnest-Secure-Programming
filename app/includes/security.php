<?php
/**
 * UI security defaults (Slot C). layout.php loads this file automatically.
 * Sends security headers on every page and shows a generic 500 page instead of
 * PHP errors, stack traces, or server paths.
 */

if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

if (!headers_sent() && PHP_SAPI !== 'cli') {
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000');
    }
    header_remove('X-Powered-By');
}

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $e): void {
        error_log('[jobnest-ui] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Terjadi Kesalahan | JobNest</title>'
            . '<link rel="stylesheet" href="/assets/css/public.css"></head><body><main class="container error-page">'
            . '<h1 class="page-title">500 - Terjadi Kesalahan</h1><p class="page-subtitle">Maaf, terjadi kesalahan pada server. Silakan coba lagi nanti.</p>'
            . '<p><a class="btn btn-primary" href="/index.php">Kembali ke Beranda</a></p></main></body></html>';
    });
}
