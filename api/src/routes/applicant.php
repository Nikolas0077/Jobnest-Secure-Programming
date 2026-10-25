<?php
/**
 * Applicant endpoints (Slot D): profile, CV upload, apply, history, saved jobs (P1).
 * Every query is scoped to the session user's ID; no applicant ID is read from the request.
 */
declare(strict_types=1);

function applicant_profile(int $userId): array {
    $profile = db_one('SELECT * FROM applicant_profiles WHERE user_id = ?', [$userId]);
    if ($profile === null) {
        db_query('INSERT INTO applicant_profiles (user_id) VALUES (?)', [$userId]);
        $profile = db_one('SELECT * FROM applicant_profiles WHERE user_id = ?', [$userId]);
    }
    return $profile;
}

route('GET', '/applicant/profile', function (): void {
    $user = require_role('applicant');
    $profile = applicant_profile((int) $user['id']);
    json_success([
        'name' => $user['name'],
        'email' => $user['email'],
        'phone' => $profile['phone'],
        'address' => $profile['address'],
        'education' => $profile['education'],
        'skills' => $profile['skills'],
        'cv_available' => cv_file_path($profile['cv_path']) !== null, // the path itself is never sent
        'photo_url' => null,
    ]);
});

route('PATCH', '/applicant/profile', function (): void {
    $user = require_role('applicant');
    $body = read_json_body();
    $errors = [];
    $phone = v_phone($body, 'phone', $errors);
    $address = v_string($body, 'address', $errors, 0, 500, false, true);
    $education = v_string($body, 'education', $errors, 0, 1000, false, true);
    $skills = v_string($body, 'skills', $errors, 0, 1000, false, true);
    if ($errors) {
        validation_error($errors);
    }
    applicant_profile((int) $user['id']);
    db_query(
        'UPDATE applicant_profiles SET phone = ?, address = ?, education = ?, skills = ? WHERE user_id = ?',
        [$phone, $address, $education, $skills, $user['id']]
    );
    log_activity('applicant.profile.update', (int) $user['id']);
    json_success(null, 200, 'Profil diperbarui.');
});

route('POST', '/applicant/cv', function (): void {
    $user = require_role('applicant');
    $tmp = validate_cv_upload($_FILES['cv'] ?? null);
    $old = applicant_profile((int) $user['id'])['cv_path'];
    $name = store_uploaded_cv($tmp);
    db_query('UPDATE applicant_profiles SET cv_path = ? WHERE user_id = ?', [$name, $user['id']]);
    // Applications keep their own copy, so the old profile CV can be removed safely.
    delete_stored_cv($old);
    log_activity('applicant.cv.upload', (int) $user['id']);
    json_success(['cv_available' => true], 201, 'CV tersimpan.');
});

route('POST', '/jobs/{id}/applications', function (array $params): void {
    $user = require_role('applicant');
    $body = read_json_body();
    $errors = [];
    $coverLetter = v_string($body, 'cover_letter', $errors, 0, 5000, false, true);
    if ($errors) {
        validation_error($errors);
    }
    // Status, deadline, and company verification are re-checked on the server.
    $job = find_public_job($params['id']);
    if ($job === null) {
        $exists = db_one('SELECT id FROM job_posts WHERE id = ? AND deleted_at IS NULL', [$params['id']]);
        $exists
            ? json_error(422, 'JOB_CLOSED', 'Lowongan sudah ditutup atau melewati batas waktu.')
            : json_error(404, 'NOT_FOUND', 'Lowongan tidak ditemukan.');
    }
    $profile = applicant_profile((int) $user['id']);
    if (cv_file_path($profile['cv_path']) === null) {
        json_error(422, 'CV_REQUIRED', 'Unggah CV terlebih dahulu sebelum melamar.');
    }
    $already = db_one('SELECT id FROM applications WHERE job_post_id = ? AND applicant_id = ?', [$job['id'], $user['id']]);
    if ($already !== null) {
        json_error(409, 'DUPLICATE_APPLICATION', 'Anda sudah melamar lowongan ini.');
    }
    $cvCopy = copy_stored_cv($profile['cv_path']);
    try {
        db_query(
            'INSERT INTO applications (job_post_id, applicant_id, cv_path, cover_letter) VALUES (?, ?, ?, ?)',
            [$job['id'], $user['id'], $cvCopy, $coverLetter]
        );
    } catch (PDOException $e) {
        delete_stored_cv($cvCopy);
        // UNIQUE(job_post_id, applicant_id) also stops two requests sent at the same time.
        if (is_duplicate_key($e)) {
            json_error(409, 'DUPLICATE_APPLICATION', 'Anda sudah melamar lowongan ini.');
        }
        throw $e;
    }
    $id = (int) db()->lastInsertId();
    log_activity('application.submit', (int) $user['id'], 'application', $id);
    json_success(['id' => $id, 'job_id' => (int) $job['id'], 'status' => 'submitted'], 201, 'Lamaran terkirim.');
});

route('GET', '/applicant/applications', function (): void {
    $user = require_role('applicant');
    $page = query_int('page', 1, 1, 1000);
    $perPage = 10;
    $total = (int) db_one('SELECT COUNT(*) AS n FROM applications WHERE applicant_id = ?', [$user['id']])['n'];
    $rows = db_all(
        'SELECT a.id, a.job_post_id, a.status, a.created_at, j.title, c.company_name
         FROM applications a
         JOIN job_posts j ON j.id = a.job_post_id
         JOIN companies c ON c.id = j.company_id
         WHERE a.applicant_id = ?
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
        [$user['id']]
    );
    json_success([
        'items' => array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'job_id' => (int) $r['job_post_id'],
            'job_title' => $r['title'],
            'company_name' => $r['company_name'],
            'status' => $r['status'],
            'created_at' => $r['created_at'],
        ], $rows),
        'pagination' => pagination_meta($page, $perPage, $total),
    ]);
});

/* ---------- Saved jobs (P1) ---------- */

route('GET', '/applicant/saved-jobs', function (): void {
    $user = require_role('applicant');
    $rows = db_all(
        'SELECT j.id, j.title, j.location, j.category, j.salary_min, j.salary_max, j.deadline, c.company_name '
        . PUBLIC_JOB_FROM . ' JOIN saved_jobs s ON s.job_post_id = j.id
        WHERE s.user_id = ? AND ' . PUBLIC_JOB_SQL . ' ORDER BY s.created_at DESC LIMIT 100',
        [$user['id']]
    );
    json_success(['items' => array_map('job_summary', $rows)]);
});

route('POST', '/applicant/saved-jobs/{id}', function (array $params): void {
    $user = require_role('applicant');
    if (find_public_job($params['id']) === null) {
        json_error(404, 'NOT_FOUND', 'Lowongan tidak ditemukan.');
    }
    db_query('INSERT IGNORE INTO saved_jobs (user_id, job_post_id) VALUES (?, ?)', [$user['id'], $params['id']]);
    json_success(['job_id' => $params['id'], 'saved' => true], 201, 'Lowongan disimpan.');
});

route('DELETE', '/applicant/saved-jobs/{id}', function (array $params): void {
    $user = require_role('applicant');
    db_query('DELETE FROM saved_jobs WHERE user_id = ? AND job_post_id = ?', [$user['id'], $params['id']]);
    json_success(null, 204);
});
