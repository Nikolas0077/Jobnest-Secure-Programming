<?php
/**
 * PDO connection (Slot C). Always use prepared statements with bound values:
 *   $stmt = db()->prepare('SELECT * FROM users WHERE id = ?'); $stmt->execute([$id]);
 * Never concatenate request data into SQL. Column/ORDER BY names come from whitelists only.
 */
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        config('DB_HOST', '127.0.0.1'),
        (int) config('DB_PORT', '3306'),
        config('DB_NAME', 'jobnest')
    );
    $pdo = new PDO($dsn, (string) config('DB_USER', 'jobnest_app'), (string) config('DB_PASS', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    return $pdo;
}

/** Run a prepared statement and return it. */
function db_query(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_one(string $sql, array $params = []): ?array {
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array {
    return db_query($sql, $params)->fetchAll();
}

function is_duplicate_key(PDOException $e): bool {
    return ($e->errorInfo[1] ?? null) === 1062;
}
