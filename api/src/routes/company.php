<?php
/**
 * Company endpoints (Slot D, profile helped by Slot E). Ownership is always resolved
 * through companies.user_id = session user; a job or application from another company
 * returns 404 (same as "does not exist", so IDs of other companies cannot be probed).
 */
declare(strict_types=1);

const JOB_STATUSES = ['open', 'closed'];
const APPLICATION_REVIEW_STATUSES = ['reviewed', 'accepted', 'rejected'];

function company_public(array $c): array {
    return [
        'id' => (int) $c['id'],
        'company_name' => $c['company_name'],
        'description' => $c['description'],
        'website' => $c['website'],
        'is_verified' => $c['verification_status'] === 'approved',
        'verification_status' => $c['verification_status'],
    ];
}

/** Job owned by this company (not deleted), or 404. */
function owned_job(array $company, int $jobId): array {
    $job = db_one('SELECT * FROM job_posts WHERE id = ? AND company_id = ? AND deleted_at IS NULL', [$jobId, $company['id']]);
    if ($job === null) {
        json_error(404, 'NOT_FOUND', 'Lowongan tidak ditemukan.');
    }
    return $job;
}

/** Application for a job owned by this company, or 404. */
function owned_application(array $company, int $applicationId): array {
    $app = db_one(
        'SELECT a.*, u.name AS applicant_name FROM applications a
         JOIN job_posts j ON j.id = a.job_post_id
         JOIN users u ON u.id = a.applicant_id
         WHERE a.id = ? AND j.company_id = ? AND j.deleted_at IS NULL',
        [$applicationId, $company['id']]
    );
    if ($app === null) {
        json_error(404, 'NOT_FOUND', 'Lamaran tidak ditemukan.');
    }
    return $app;
}

/** Validate job fields. $partial = true for PATCH (only sent fields are checked). */
function validate_job_fields(array $body, bool $partial): array {
    $errors = [];
    $fields = [];
    $has = static fn (string $key): bool => !$partial || array_key_exists($key, $body);
    if ($has('title')) $fields['title'] = v_string($body, 'title', $errors, 3, 150);
    if ($has('description')) $fields['description'] = v_string($body, 'description', $errors, 10, 10000, true, true);
    if ($has('location')) $fields['location'] = v_string($body, 'location', $errors, 2, 100);
    if ($has('category')) $fields['category'] = v_string($body, 'category', $errors, 2, 100);
    if ($has('salary_min')) $fields['salary_min'] = v_int($body, 'salary_min', $errors, 0, 2000000000, false);
    if ($has('salary_max')) $fields['salary_max'] = v_int($body, 'salary_max', $errors, 0, 2000000000, false);
    if ($has('deadline')) $fields['deadline'] = v_date($body, 'deadline', $errors, false);
    if (array_key_exists('deadline', $fields) && $fields['deadline'] !== null && $fields['deadline'] < date('Y-m-d')) {
        $errors['deadline'] = 'Batas waktu tidak boleh di masa lalu.';
    }
    return [$fields, $errors];
}

route('GET', '/company/profile', function (): void {
    json_success(company_public(require_company()));
});

route('PATCH', '/company/profile', function (): void {
    $company = require_company();
    $body = read_json_body();
    $errors = [];
    $name = v_string($body, 'company_name', $errors, 2, 255);
    $description = v_string($body, 'description', $errors, 0, 5000, false, true);
    $website = v_url($body, 'website', $errors);
    if ($errors) {
        validation_error($errors);
    }
    // Renaming a verified company sends it back to admin review, so an approved
    // account cannot later impersonate another company name.
    $status = $name !== $company['company_name'] ? 'pending' : $company['verification_status'];
    db_query(
        'UPDATE companies SET company_name = ?, description = ?, website = ?, verification_status = ? WHERE id = ?',
        [$name, $description, $website, $status, $company['id']]
    );
    log_activity('company.profile.update', (int) $company['user_id'], 'company', (int) $company['id']);
    json_success(company_public(db_one('SELECT * FROM companies WHERE id = ?', [$company['id']])), 200, 'Profil diperbarui.');
});

route('GET', '/company/jobs', function (): void {
    $company = require_company();
    $rows = db_all(
        'SELECT j.id, j.title, j.status, j.deadline, j.created_at,
                (SELECT COUNT(*) FROM applications a WHERE a.job_post_id = j.id) AS applications_count
         FROM job_posts j WHERE j.company_id = ? AND j.deleted_at IS NULL ORDER BY j.created_at DESC, j.id DESC LIMIT 200',
        [$company['id']]
    );
    json_success(['items' => array_map(static fn (array $r): array => [
        'id' => (int) $r['id'],
        'title' => $r['title'],
        'status' => $r['status'],
        'deadline' => $r['deadline'],
        'created_at' => $r['created_at'],
        'applications_count' => (int) $r['applications_count'],
    ], $rows)]);
});

// Extra (not in the first contract): one owned job, used to fill the edit form.
route('GET', '/company/jobs/{id}', function (array $params): void {
    $job = owned_job(require_company(), $params['id']);
    json_success([
        'id' => (int) $job['id'],
        'title' => $job['title'],
        'description' => $job['description'],
        'location' => $job['location'],
        'category' => $job['category'],
        'salary_min' => $job['salary_min'] !== null ? (int) $job['salary_min'] : null,
        'salary_max' => $job['salary_max'] !== null ? (int) $job['salary_max'] : null,
        'deadline' => $job['deadline'],
        'status' => $job['status'],
    ]);
});

route('POST', '/company/jobs', function (): void {
    $company = require_company();
    if ($company['verification_status'] !== 'approved') {
        json_error(403, 'COMPANY_NOT_VERIFIED', 'Perusahaan harus diverifikasi admin sebelum memasang lowongan.');
    }
    [$f, $errors] = validate_job_fields(read_json_body(), false);
    if (!$errors && $f['salary_min'] !== null && $f['salary_max'] !== null && $f['salary_min'] > $f['salary_max']) {
        $errors['salary_max'] = 'Gaji maksimum harus lebih besar dari gaji minimum.';
    }
    if ($errors) {
        validation_error($errors);
    }
    db_query(
        'INSERT INTO job_posts (company_id, title, description, location, category, salary_min, salary_max, deadline, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$company['id'], $f['title'], $f['description'], $f['location'], $f['category'], $f['salary_min'], $f['salary_max'], $f['deadline'], 'open']
    );
    $id = (int) db()->lastInsertId();
    log_activity('company.job.create', (int) $company['user_id'], 'job', $id);
    json_success(['id' => $id, 'status' => 'open'], 201, 'Lowongan dibuat.');
});

route('PATCH', '/company/jobs/{id}', function (array $params): void {
    $company = require_company();
    $job = owned_job($company, $params['id']);
    $body = read_json_body();
    [$f, $errors] = validate_job_fields($body, true);
    if (array_key_exists('status', $body)) {
        $f['status'] = v_enum($body, 'status', $errors, JOB_STATUSES);
    }
    if (($f['status'] ?? null) === 'open' && $company['verification_status'] !== 'approved') {
        json_error(403, 'COMPANY_NOT_VERIFIED', 'Perusahaan harus diverifikasi admin untuk membuka lowongan.');
    }
    $min = array_key_exists('salary_min', $f) ? $f['salary_min'] : $job['salary_min'];
    $max = array_key_exists('salary_max', $f) ? $f['salary_max'] : $job['salary_max'];
    if (!$errors && $min !== null && $max !== null && (int) $min > (int) $max) {
        $errors['salary_max'] = 'Gaji maksimum harus lebih besar dari gaji minimum.';
    }
    if ($errors) {
        validation_error($errors);
    }
    if ($f === []) {
        validation_error(['body' => 'Tidak ada data yang diubah.']);
    }
    // Column names come from the fixed keys above, never from the request.
    $set = implode(', ', array_map(static fn (string $col): string => "`{$col}` = ?", array_keys($f)));
    db_query("UPDATE job_posts SET {$set} WHERE id = ? AND company_id = ?", [...array_values($f), $job['id'], $company['id']]);
    log_activity('company.job.update', (int) $company['user_id'], 'job', (int) $job['id']);
    json_success(['id' => (int) $job['id']], 200, 'Lowongan diperbarui.');
});

route('DELETE', '/company/jobs/{id}', function (array $params): void {
    $company = require_company();
    $job = owned_job($company, $params['id']);
    db_query("UPDATE job_posts SET deleted_at = NOW(), status = 'closed' WHERE id = ? AND company_id = ?", [$job['id'], $company['id']]);
    log_activity('company.job.delete', (int) $company['user_id'], 'job', (int) $job['id']);
    json_success(null, 204);
});

route('GET', '/company/jobs/{id}/applications', function (array $params): void {
    $company = require_company();
    $job = owned_job($company, $params['id']);
    $rows = db_all(
        'SELECT a.id, a.status, a.created_at, a.cover_letter, u.name AS applicant_name, u.email AS applicant_email,
                p.phone, p.education, p.skills
         FROM applications a
         JOIN users u ON u.id = a.applicant_id
         LEFT JOIN applicant_profiles p ON p.user_id = a.applicant_id
         WHERE a.job_post_id = ? ORDER BY a.created_at DESC, a.id DESC LIMIT 500',
        [$job['id']]
    );
    json_success([
        'job' => ['id' => (int) $job['id'], 'title' => $job['title'], 'status' => $job['status']],
        'items' => array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'applicant_name' => $r['applicant_name'],
            'applicant_email' => $r['applicant_email'],
            'phone' => $r['phone'],
            'education' => $r['education'],
            'skills' => $r['skills'],
            'cover_letter' => $r['cover_letter'],
            'status' => $r['status'],
            'created_at' => $r['created_at'],
        ], $rows),
    ]);
});

route('PATCH', '/company/applications/{id}/status', function (array $params): void {
    $company = require_company();
    $app = owned_application($company, $params['id']);
    $errors = [];
    $status = v_enum(read_json_body(), 'status', $errors, APPLICATION_REVIEW_STATUSES);
    if ($errors) {
        validation_error($errors);
    }
    db_query('UPDATE applications SET status = ? WHERE id = ?', [$status, $app['id']]);
    log_activity('application.status.update', (int) $company['user_id'], 'application', (int) $app['id']);
    json_success(['id' => (int) $app['id'], 'status' => $status], 200, 'Status lamaran diperbarui.');
});

route('GET', '/company/applications/{id}/cv', function (array $params): void {
    $company = require_company();
    $app = owned_application($company, $params['id']);
    // The file comes from the database record, never from a path in the request.
    $path = cv_file_path($app['cv_path']);
    if ($path === null) {
        json_error(404, 'NOT_FOUND', 'File CV tidak ditemukan.');
    }
    log_activity('application.cv.download', (int) $company['user_id'], 'application', (int) $app['id']);
    send_pdf_download($path, 'CV_' . $app['applicant_name'] . '_' . $app['id']);
});
