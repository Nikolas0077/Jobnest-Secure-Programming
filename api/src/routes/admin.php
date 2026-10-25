<?php
/**
 * Admin endpoints (Slot E): company verification, user suspension, job moderation,
 * activity logs. Every route calls require_role('admin') on the server, so force
 * browsing /api/admin/... as another role returns 403.
 */
declare(strict_types=1);

const VERIFICATION_FILTERS = ['pending', 'approved', 'rejected'];

route('GET', '/admin/summary', function (): void {
    require_role('admin');
    $one = static fn (string $sql): int => (int) db_one($sql)['n'];
    json_success([
        'companies_pending' => $one("SELECT COUNT(*) AS n FROM companies WHERE verification_status = 'pending'"),
        'users_total' => $one('SELECT COUNT(*) AS n FROM users'),
        'users_inactive' => $one('SELECT COUNT(*) AS n FROM users WHERE is_active = 0'),
        'jobs_active' => $one("SELECT COUNT(*) AS n FROM job_posts WHERE deleted_at IS NULL AND status = 'open'"),
        'applications_total' => $one('SELECT COUNT(*) AS n FROM applications'),
    ]);
});

route('GET', '/admin/companies', function (): void {
    require_role('admin');
    $filter = is_string($_GET['verified'] ?? null) && in_array($_GET['verified'], VERIFICATION_FILTERS, true) ? $_GET['verified'] : 'pending';
    $rows = db_all(
        'SELECT c.id, c.company_name, c.website, c.description, c.verification_status, c.created_at,
                u.id AS user_id, u.email, u.is_active
         FROM companies c JOIN users u ON u.id = c.user_id
         WHERE c.verification_status = ? ORDER BY c.created_at ASC LIMIT 200',
        [$filter]
    );
    json_success(['items' => array_map(static fn (array $r): array => [
        'id' => (int) $r['id'],
        'company_name' => $r['company_name'],
        'website' => $r['website'],
        'description' => $r['description'],
        'verification_status' => $r['verification_status'],
        'is_verified' => $r['verification_status'] === 'approved',
        'user_id' => (int) $r['user_id'],
        'email' => $r['email'],
        'is_active' => (bool) $r['is_active'],
        'created_at' => $r['created_at'],
    ], $rows)]);
});

route('PATCH', '/admin/companies/{id}/verification', function (array $params): void {
    $admin = require_role('admin');
    $errors = [];
    $verified = v_bool(read_json_body(), 'is_verified', $errors);
    if ($errors) {
        validation_error($errors);
    }
    $company = db_one('SELECT id FROM companies WHERE id = ?', [$params['id']]);
    if ($company === null) {
        json_error(404, 'NOT_FOUND', 'Perusahaan tidak ditemukan.');
    }
    $status = $verified ? 'approved' : 'rejected';
    db_query(
        'UPDATE companies SET verification_status = ?, verified_at = NOW(), verified_by = ? WHERE id = ?',
        [$status, $admin['id'], $company['id']]
    );
    log_activity($verified ? 'admin.company.approve' : 'admin.company.reject', (int) $admin['id'], 'company', (int) $company['id']);
    json_success(['id' => (int) $company['id'], 'is_verified' => $verified, 'verification_status' => $status]);
});

// Extra (not in the first contract): user list for the suspension page.
route('GET', '/admin/users', function (): void {
    require_role('admin');
    $page = query_int('page', 1, 1, 1000);
    $perPage = 20;
    $where = '1 = 1';
    $params = [];
    $role = $_GET['role'] ?? '';
    if (is_string($role) && in_array($role, ROLES, true)) {
        $where = 'role = ?';
        $params[] = $role;
    }
    $total = (int) db_one("SELECT COUNT(*) AS n FROM users WHERE {$where}", $params)['n'];
    $rows = db_all(
        "SELECT id, name, email, role, is_active, created_at FROM users WHERE {$where}
         ORDER BY id ASC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
        $params
    );
    json_success([
        'items' => array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'email' => $r['email'],
            'role' => $r['role'],
            'is_active' => (bool) $r['is_active'],
            'created_at' => $r['created_at'],
        ], $rows),
        'pagination' => pagination_meta($page, $perPage, $total),
    ]);
});

route('PATCH', '/admin/users/{id}/status', function (array $params): void {
    $admin = require_role('admin');
    $errors = [];
    $active = v_bool(read_json_body(), 'is_active', $errors);
    if ($errors) {
        validation_error($errors);
    }
    if ($params['id'] === (int) $admin['id']) {
        json_error(403, 'FORBIDDEN', 'Admin tidak dapat menonaktifkan akunnya sendiri.');
    }
    $target = db_one('SELECT id, role FROM users WHERE id = ?', [$params['id']]);
    if ($target === null) {
        json_error(404, 'NOT_FOUND', 'Pengguna tidak ditemukan.');
    }
    if ($target['role'] === 'admin') {
        json_error(403, 'FORBIDDEN', 'Status akun admin lain hanya bisa diubah langsung di database.');
    }
    db_query('UPDATE users SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $target['id']]);
    // A disabled user is logged out on the next request because current_user() rechecks is_active.
    log_activity($active ? 'admin.user.activate' : 'admin.user.suspend', (int) $admin['id'], 'user', (int) $target['id']);
    json_success(['id' => (int) $target['id'], 'is_active' => $active]);
});

// Extra (not in the first contract): job list for the moderation page.
route('GET', '/admin/jobs', function (): void {
    require_role('admin');
    $page = query_int('page', 1, 1, 1000);
    $perPage = 20;
    $total = (int) db_one('SELECT COUNT(*) AS n FROM job_posts WHERE deleted_at IS NULL')['n'];
    $rows = db_all(
        'SELECT j.id, j.title, j.status, j.deadline, j.created_at, c.company_name
         FROM job_posts j JOIN companies c ON c.id = j.company_id
         WHERE j.deleted_at IS NULL ORDER BY j.created_at DESC, j.id DESC
         LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
    );
    json_success([
        'items' => array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'title' => $r['title'],
            'company_name' => $r['company_name'],
            'status' => $r['status'],
            'deadline' => $r['deadline'],
            'created_at' => $r['created_at'],
        ], $rows),
        'pagination' => pagination_meta($page, $perPage, $total),
    ]);
});

route('DELETE', '/admin/jobs/{id}', function (array $params): void {
    $admin = require_role('admin');
    $job = db_one('SELECT id FROM job_posts WHERE id = ? AND deleted_at IS NULL', [$params['id']]);
    if ($job === null) {
        json_error(404, 'NOT_FOUND', 'Lowongan tidak ditemukan.');
    }
    // Soft delete keeps applications and audit history intact.
    db_query("UPDATE job_posts SET deleted_at = NOW(), deleted_by_admin = 1, status = 'closed' WHERE id = ?", [$job['id']]);
    log_activity('admin.job.delete', (int) $admin['id'], 'job', (int) $job['id']);
    json_success(null, 204);
});

route('GET', '/admin/logs', function (): void {
    require_role('admin');
    $page = query_int('page', 1, 1, 10000);
    $perPage = 25;
    $total = (int) db_one('SELECT COUNT(*) AS n FROM activity_logs')['n'];
    $rows = db_all(
        'SELECT l.id, l.user_id, u.name AS user_name, l.action, l.target_type, l.target_id, l.ip_address, l.created_at
         FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
         ORDER BY l.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
    );
    json_success([
        'items' => array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'user_id' => $r['user_id'] !== null ? (int) $r['user_id'] : null,
            'user_name' => $r['user_name'],
            'action' => $r['action'],
            'target_type' => $r['target_type'],
            'target_id' => $r['target_id'] !== null ? (int) $r['target_id'] : null,
            'ip_address' => $r['ip_address'],
            'created_at' => $r['created_at'],
        ], $rows),
        'pagination' => pagination_meta($page, $perPage, $total),
    ]);
});
