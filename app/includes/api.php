<?php

/**
 * Call a backend endpoint under JOBNEST_API_BASE_URL, including the user's cookie.
 *
 * Example: api_request('GET', '/jobs?page=1'). Only pass paths defined in
 * docs/api-contract.md; never build this path from unvalidated user input.
 * Returns ['ok' => bool, 'status' => int, 'data' => mixed] on success or
 * ['ok' => false, 'status' => int, 'error' => string] on failure.
 * The API runs separately; this file does not implement API endpoints.
 */
function api_request(string $method, string $path, ?array $body = null, ?string $csrf = null): array {
    $base = rtrim((string) getenv('JOBNEST_API_BASE_URL'), '/');
    if ($base === '') {
        return ['ok' => false, 'status' => 0, 'error' => 'Backend belum dikonfigurasi.'];
    }
    $url = $base . $path;
    $curl = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    if ($csrf !== null) {
        $headers[] = 'X-CSRF-Token: ' . $csrf;
    }
    $cookies = $_SERVER['HTTP_COOKIE'] ?? '';
    if ($cookies !== '') {
        curl_setopt($curl, CURLOPT_COOKIE, $cookies);
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$cookies): int {
            if (stripos($line, 'Set-Cookie: ') === 0) {
                $cookie = trim(substr($line, 12));
                $pair = explode(';', $cookie, 2)[0];
                if (preg_match('/^[A-Za-z0-9_\-]+=[^;\r\n]*$/', $pair)) {
                    $name = explode('=', $pair, 2)[0];
                    $cookies = preg_replace('/(?:^|;\s*)' . preg_quote($name, '/') . '=[^;]*/', '', $cookies);
                    $cookies = trim($cookies, '; ');
                    $cookies .= ($cookies === '' ? '' : '; ') . $pair;
                    $_SERVER['HTTP_COOKIE'] = $cookies;
                    // The API may run on another host; issue its session cookie for this UI host.
                    $cleared = preg_match('/(?:^|;)\s*(?:Max-Age=0|Expires=Thu, 01 Jan 1970)/i', $cookie);
                    header('Set-Cookie: ' . $pair . '; Path=/; HttpOnly; SameSite=Lax'
                        . (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? '; Secure' : '')
                        . ($cleared ? '; Max-Age=0' : ''), false);
                }
            }
            return strlen($line);
        },
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    }
    $raw = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($raw === false) {
        return ['ok' => false, 'status' => 0, 'error' => 'Backend tidak dapat dihubungi.'];
    }
    if ($status === 204) {
        return ['ok' => true, 'status' => 204, 'data' => null];
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'status' => $status, 'error' => 'Respons backend tidak valid.'];
    }
    return $status >= 200 && $status < 300 && ($json['success'] ?? false) === true
        ? ['ok' => true, 'status' => $status, 'data' => $json['data'] ?? null]
        : ['ok' => false, 'status' => $status, 'error' => is_string($json['error']['message'] ?? null) ? $json['error']['message'] : 'Permintaan gagal.'];
}

/**
 * Fetch the backend CSRF token, then POST JSON using the same backend session.
 * Example: api_post_with_csrf('/auth/login', ['email' => $email, 'password' => $password]).
 * The HTML form's form_token is checked separately in public/auth-action.php.
 */
function api_post_with_csrf(string $path, ?array $body = null): array {
    $token = api_request('GET', '/auth/csrf');
    if (!$token['ok'] || !is_string($token['data']['csrf_token'] ?? null)) {
        return ['ok' => false, 'status' => $token['status'], 'error' => 'Token keamanan tidak tersedia.'];
    }
    return api_request('POST', $path, $body, $token['data']['csrf_token']);
}
