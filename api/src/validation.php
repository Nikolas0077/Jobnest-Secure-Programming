<?php
/**
 * Server-side validation helpers (Slot C). Each helper reads one field, records an
 * error message in $errors when invalid, and returns the clean value (or null).
 * Only fields read through these helpers are used, so extra fields such as
 * role, user_id, or company_id in a request body are ignored (no mass assignment).
 *
 *   $errors = [];
 *   $title = v_string($body, 'title', $errors, 3, 150);
 *   if ($errors) validation_error($errors);
 */
declare(strict_types=1);

/** Remove control characters except tab/newline; text stays plain and is escaped on output. */
function clean_text(string $value, bool $multiline): string {
    $pattern = $multiline ? '/[^\P{C}\t\n\r]/u' : '/\p{C}/u';
    $value = preg_replace($pattern, '', $value) ?? '';
    return trim($value);
}

function v_string(array $data, string $key, array &$errors, int $min, int $max, bool $required = true, bool $multiline = false): ?string {
    $value = $data[$key] ?? null;
    if ($value === null || $value === '') {
        if ($required) {
            $errors[$key] = 'Wajib diisi.';
        }
        return null;
    }
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        $errors[$key] = 'Format tidak valid.';
        return null;
    }
    $value = clean_text($value, $multiline);
    $length = mb_strlen($value);
    if ($value === '' && !$required) {
        return null;
    }
    if ($length < $min || $length > $max) {
        $errors[$key] = "Panjang harus {$min}-{$max} karakter.";
        return null;
    }
    return $value;
}

function v_email(array $data, string $key, array &$errors): ?string {
    $value = $data[$key] ?? null;
    if (!is_string($value) || strlen($value) > 255 || !filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
        $errors[$key] = 'Email tidak valid.';
        return null;
    }
    return mb_strtolower(trim($value));
}

/** bcrypt only uses the first 72 bytes, so longer passwords are rejected instead of cut. */
function v_password(array $data, string $key, array &$errors): ?string {
    $value = $data[$key] ?? null;
    if (!is_string($value) || strlen($value) < 8 || strlen($value) > 72) {
        $errors[$key] = 'Password harus 8-72 karakter.';
        return null;
    }
    if (!preg_match('/[A-Za-z]/', $value) || !preg_match('/\d/', $value)) {
        $errors[$key] = 'Password harus berisi huruf dan angka.';
        return null;
    }
    return $value;
}

function v_int(array $data, string $key, array &$errors, int $min, int $max, bool $required = true): ?int {
    $value = $data[$key] ?? null;
    if ($value === null || $value === '') {
        if ($required) {
            $errors[$key] = 'Wajib diisi.';
        }
        return null;
    }
    if (is_string($value) && preg_match('/^\d{1,10}$/', $value)) {
        $value = (int) $value;
    }
    if (!is_int($value) || $value < $min || $value > $max) {
        $errors[$key] = "Harus angka {$min}-{$max}.";
        return null;
    }
    return $value;
}

function v_enum(array $data, string $key, array &$errors, array $allowed, bool $required = true): ?string {
    $value = $data[$key] ?? null;
    if ($value === null && !$required) {
        return null;
    }
    if (!is_string($value) || !in_array($value, $allowed, true)) {
        $errors[$key] = 'Pilihan tidak valid.';
        return null;
    }
    return $value;
}

function v_bool(array $data, string $key, array &$errors): ?bool {
    $value = $data[$key] ?? null;
    if (!is_bool($value)) {
        $errors[$key] = 'Harus true atau false.';
        return null;
    }
    return $value;
}

/** Date in YYYY-MM-DD that is a real calendar date. */
function v_date(array $data, string $key, array &$errors, bool $required = true): ?string {
    $value = $data[$key] ?? null;
    if ($value === null || $value === '') {
        if ($required) {
            $errors[$key] = 'Wajib diisi.';
        }
        return null;
    }
    $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
    if ($date === false || $date->format('Y-m-d') !== $value) {
        $errors[$key] = 'Tanggal harus format YYYY-MM-DD.';
        return null;
    }
    return $value;
}

/** Only http/https URLs, so javascript: or data: links can never be saved. */
function v_url(array $data, string $key, array &$errors, bool $required = false): ?string {
    $value = $data[$key] ?? null;
    if ($value === null || $value === '') {
        if ($required) {
            $errors[$key] = 'Wajib diisi.';
        }
        return null;
    }
    if (!is_string($value) || strlen($value) > 255 || !filter_var($value, FILTER_VALIDATE_URL)
        || !in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)) {
        $errors[$key] = 'URL harus diawali http:// atau https://';
        return null;
    }
    return $value;
}

function v_phone(array $data, string $key, array &$errors): ?string {
    $value = $data[$key] ?? null;
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_string($value) || !preg_match('/^\+?[0-9][0-9 \-]{6,18}$/', $value)) {
        $errors[$key] = 'Nomor telepon tidak valid.';
        return null;
    }
    return $value;
}
