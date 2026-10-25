<?php
/**
 * Global response format, security headers, and safe JSON input (Slot C).
 * Success: {"success":true,"data":{...},"message":"OK"}
 * Error:   {"success":false,"error":{"code":"...","message":"...","fields":{...}}}
 */
declare(strict_types=1);

const MAX_JSON_BYTES = 65536;

function send_security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; sandbox");
    header('Cache-Control: no-store');
    header('Cross-Origin-Resource-Policy: same-origin');
    header_remove('X-Powered-By');
}

function json_success(mixed $data = null, int $status = 200, string $message = 'OK'): never {
    if ($status === 204) {
        http_response_code(204);
        exit;
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $data, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

/** Send an error. Never pass exception messages, SQL, or file paths here. */
function json_error(int $status, string $code, string $message, array $fields = []): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    $error = ['code' => $code, 'message' => $message];
    if ($fields !== []) {
        $error['fields'] = $fields;
    }
    echo json_encode(['success' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

function validation_error(array $fields): never {
    $first = reset($fields);
    json_error(422, 'VALIDATION_ERROR', 'Data tidak valid: ' . key($fields) . ' - ' . (is_string($first) ? $first : 'periksa kembali.'), $fields);
}

/** Decode a JSON object body; rejects other content types, oversize bodies, and arrays. */
function read_json_body(): array {
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($type, 'application/json') !== 0) {
        json_error(415, 'UNSUPPORTED_MEDIA_TYPE', 'Gunakan Content-Type application/json.');
    }
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > MAX_JSON_BYTES) {
        json_error(413, 'PAYLOAD_TOO_LARGE', 'Data terlalu besar.');
    }
    $raw = file_get_contents('php://input', false, null, 0, MAX_JSON_BYTES + 1);
    if ($raw === false || strlen($raw) > MAX_JSON_BYTES) {
        json_error(413, 'PAYLOAD_TOO_LARGE', 'Data terlalu besar.');
    }
    if (trim($raw) === '') {
        return [];
    }
    try {
        $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        json_error(400, 'BAD_REQUEST', 'JSON tidak valid.');
    }
    if (!is_array($data) || ($data !== [] && array_is_list($data))) {
        json_error(400, 'BAD_REQUEST', 'Body harus berupa objek JSON.');
    }
    return $data;
}

/**
 * Client IP for logs and throttling. X-Forwarded-For is only trusted from
 * the PHP UI server listed in TRUSTED_PROXIES, so clients cannot spoof it.
 */
function client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trusted = array_filter(array_map('trim', explode(',', (string) config('TRUSTED_PROXIES', '127.0.0.1,::1'))));
    if (in_array($remote, $trusted, true) && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']));
        $candidate = end($parts);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

/**
 * True when the request comes from the PHP UI server without the real client IP
 * (X-Forwarded-For). Then every user shares that IP, so per-IP limits are skipped
 * to avoid locking out everyone at once; per-account limits still apply.
 */
function client_ip_unknown(): bool {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $trusted = array_filter(array_map('trim', explode(',', (string) config('TRUSTED_PROXIES', '127.0.0.1,::1'))));
    return in_array($remote, $trusted, true) && client_ip() === $remote;
}

/** Positive int from a query string value, clamped to [min, max]; default if missing/invalid. */
function query_int(string $key, int $default, int $min, int $max): int {
    $value = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value === null) {
        return $default;
    }
    return max($min, min($max, $value));
}

function query_string(string $key, int $maxLength): string {
    $value = $_GET[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    return mb_substr($value, 0, $maxLength);
}

function pagination_meta(int $page, int $perPage, int $total): array {
    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => max(1, (int) ceil($total / $perPage)),
    ];
}
