<?php
/**
 * Auth endpoints (Slot C): csrf, register, login, me, logout, change password.
 */
declare(strict_types=1);

const REGISTER_MAX_PER_IP_HOUR = 10;
const REGISTER_MAX_SHARED_HOUR = 100;

/** Random throwaway hash, so a login for an unknown email takes as long as a real one. */
function dummy_password_hash(): string {
    static $hash = null;
    return $hash ??= password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
}

route('GET', '/auth/csrf', function (): void {
    json_success(['csrf_token' => csrf_token()]);
});

route('POST', '/auth/register', function (): void {
    require_guest();
    // Per IP when the real client IP is known; otherwise one shared limit for all signups.
    $recent = client_ip_unknown()
        ? db_one("SELECT COUNT(*) AS n FROM activity_logs WHERE action = 'auth.register' AND created_at >= NOW() - INTERVAL 1 HOUR")
        : db_one(
            "SELECT COUNT(*) AS n FROM activity_logs WHERE action = 'auth.register' AND ip_address = ? AND created_at >= NOW() - INTERVAL 1 HOUR",
            [client_ip()]
        );
    if ((int) $recent['n'] >= (client_ip_unknown() ? REGISTER_MAX_SHARED_HOUR : REGISTER_MAX_PER_IP_HOUR)) {
        json_error(429, 'RATE_LIMITED', 'Terlalu banyak pendaftaran. Coba lagi nanti.');
    }
    $body = read_json_body();
    $errors = [];
    $name = v_string($body, 'name', $errors, 2, 255);
    $email = v_email($body, 'email', $errors);
    $password = v_password($body, 'password', $errors);
    // Whitelist: admin accounts can never be created from the public form.
    $role = v_enum($body, 'role', $errors, ['applicant', 'company']);
    if ($errors) {
        validation_error($errors);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_query(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
            [$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]
        );
        $userId = (int) $pdo->lastInsertId();
        if ($role === 'company') {
            db_query('INSERT INTO companies (user_id, company_name) VALUES (?, ?)', [$userId, $name]);
        } else {
            db_query('INSERT INTO applicant_profiles (user_id) VALUES (?)', [$userId]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        if (is_duplicate_key($e)) {
            json_error(409, 'CONFLICT', 'Pendaftaran gagal. Gunakan email lain.');
        }
        throw $e;
    }
    log_activity('auth.register', $userId, 'user', $userId);
    json_success(['user' => ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => $role]], 201, 'Created');
});

route('POST', '/auth/login', function (): void {
    $body = read_json_body();
    $email = is_string($body['email'] ?? null) ? mb_strtolower(trim($body['email'])) : '';
    $password = is_string($body['password'] ?? null) ? $body['password'] : '';
    if ($email === '' || $password === '' || strlen($email) > 255 || strlen($password) > 72) {
        json_error(401, 'INVALID_CREDENTIALS', 'Email atau password salah.');
    }
    $ip = client_ip();
    if (login_is_throttled($email, $ip)) {
        log_activity('auth.login.throttled');
        header('Retry-After: ' . (LOGIN_WINDOW_MINUTES * 60));
        json_error(429, 'RATE_LIMITED', 'Terlalu banyak percobaan. Coba lagi nanti.');
    }

    $row = db_one('SELECT * FROM users WHERE email = ?', [$email]);
    $valid = password_verify($password, $row['password_hash'] ?? dummy_password_hash());
    // Same generic answer for unknown email, wrong password, and disabled account.
    if ($row === null || !$valid || (int) $row['is_active'] !== 1) {
        record_login_attempt($email, $ip, false);
        log_activity('auth.login.failed', $row !== null ? (int) $row['id'] : null);
        json_error(401, 'INVALID_CREDENTIALS', 'Email atau password salah.');
    }

    record_login_attempt($email, $ip, true);
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        db_query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }
    start_api_session();
    rotate_api_session();
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['pw_fp'] = password_fingerprint($row['password_hash']);
    $_SESSION['login_at'] = time();
    log_activity('auth.login.success', (int) $row['id']);
    json_success(['user' => public_user($row)]);
});

route('GET', '/auth/me', function (): void {
    json_success(['user' => public_user(require_login())]);
});

route('POST', '/auth/logout', function (): void {
    $user = current_user();
    if ($user !== null) {
        log_activity('auth.logout', (int) $user['id']);
    }
    destroy_api_session();
    json_success(null, 204);
});

route('PATCH', '/auth/password', function (): void {
    $user = require_login();
    $body = read_json_body();
    $errors = [];
    $current = is_string($body['current_password'] ?? null) ? $body['current_password'] : '';
    $new = v_password($body, 'new_password', $errors);
    if ($errors) {
        validation_error($errors);
    }
    $ip = client_ip();
    if (login_is_throttled($user['email'], $ip)) {
        json_error(429, 'RATE_LIMITED', 'Terlalu banyak percobaan. Coba lagi nanti.');
    }
    $row = db_one('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
    if ($row === null || !password_verify($current, $row['password_hash'])) {
        record_login_attempt($user['email'], $ip, false);
        validation_error(['current_password' => 'Password saat ini salah.']);
    }
    if (password_verify($new, $row['password_hash'])) {
        validation_error(['new_password' => 'Password baru harus berbeda.']);
    }
    $newHash = password_hash($new, PASSWORD_DEFAULT);
    db_query('UPDATE users SET password_hash = ? WHERE id = ?', [$newHash, $user['id']]);
    rotate_api_session();
    // Other sessions of this user hold the old fingerprint and are logged out on their next request.
    $_SESSION['pw_fp'] = password_fingerprint($newHash);
    log_activity('auth.password.change', (int) $user['id']);
    json_success(null, 200, 'Password diperbarui.');
});
