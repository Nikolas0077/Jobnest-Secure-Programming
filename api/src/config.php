<?php
/**
 * Reads settings from real environment variables, then from api/.env.
 * api/.env is ignored by Git; copy api/.env.example and fill in your own values.
 */
declare(strict_types=1);

function load_env_file(string $path): array {
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }
    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            $values[$key] = trim($value, "\"'");
        }
    }
    return $values;
}

function config(string $key, ?string $default = null): ?string {
    static $file = null;
    $file ??= load_env_file(dirname(__DIR__) . '/.env');
    $env = getenv($key);
    if ($env !== false && $env !== '') {
        return $env;
    }
    return $file[$key] ?? $default;
}

/** Private folder for uploaded CVs. Default: <repo>/storage/private/cv (outside public/). */
function storage_dir(): string {
    $dir = config('JOBNEST_STORAGE_DIR', dirname(__DIR__, 2) . '/storage/private/cv');
    return rtrim($dir, '/\\');
}
