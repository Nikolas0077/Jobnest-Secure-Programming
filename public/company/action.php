<?php
/**
 * JobNest - Aksi form perusahaan (Slot B).
 * POST /company/action.php?intent=profile|job_create|job_update|job_status|job_delete|application_status
 * The API re-checks role, verification, and ownership of every job and application.
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$intents = ['profile', 'job_create', 'job_update', 'job_status', 'job_delete', 'application_status'];
$intent = $_GET['intent'] ?? '';
if (!is_string($intent) || !in_array($intent, $intents, true)) {
    render_error_page(404, 'Aksi tidak ditemukan.');
}
ui_verify_post();
ui_require_role('company');

if ($intent === 'profile') {
    $data = [
        'company_name' => post_text('company_name', 255),
        'description' => post_text('description', 5000),
        'website' => post_text('website', 255),
    ];
    $error = null;
    if (mb_strlen($data['company_name']) < 2) {
        $error = 'Nama perusahaan wajib diisi.';
    } elseif ($data['website'] !== '' && (!filter_var($data['website'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $data['website']))) {
        $error = 'Website harus URL yang diawali http:// atau https://';
    }
    if ($error !== null) {
        ui_keep_old($data);
        ui_flash('danger', $error);
        ui_redirect('/company/profile.php');
    }
    $result = api_send('PATCH', '/company/profile', array_map(static fn (string $v): ?string => $v === '' ? null : $v, $data));
    if (!$result['ok']) {
        ui_keep_old($data);
        ui_fail($result, '/company/profile.php');
    }
    ui_flash('success', 'Profil perusahaan disimpan.');
    ui_redirect('/company/profile.php');
}

if ($intent === 'job_create' || $intent === 'job_update') {
    $jobId = $intent === 'job_update' ? post_id('job_id') : null;
    $back = $jobId ? '/company/job-form.php?id=' . $jobId : '/company/job-form.php';
    if ($intent === 'job_update' && $jobId === null) {
        ui_flash('danger', 'Lowongan tidak valid.');
        ui_redirect('/company/jobs.php');
    }
    $data = [
        'title' => post_text('title', 150),
        'description' => post_text('description', 10000),
        'location' => post_text('location', 100),
        'category' => post_text('category', 100),
        'salary_min' => post_optional_int('salary_min'),
        'salary_max' => post_optional_int('salary_max'),
        'deadline' => post_text('deadline', 10),
    ];
    $error = null;
    if (mb_strlen($data['title']) < 3 || mb_strlen($data['description']) < 10 || mb_strlen($data['location']) < 2 || mb_strlen($data['category']) < 2) {
        $error = 'Lengkapi judul, lokasi, kategori, dan deskripsi (minimal 10 karakter).';
    } elseif ($data['salary_min'] === false || $data['salary_max'] === false) {
        $error = 'Gaji harus berupa angka bulat.';
    } elseif ($data['salary_min'] !== null && $data['salary_max'] !== null && $data['salary_min'] > $data['salary_max']) {
        $error = 'Gaji maksimum harus lebih besar dari gaji minimum.';
    } elseif ($data['deadline'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['deadline'])) {
        $error = 'Format batas lamaran tidak valid.';
    }
    if ($error !== null) {
        $keys = ['title', 'description', 'location', 'category', 'salary_min', 'salary_max', 'deadline'];
        ui_keep_old(array_combine($keys, array_map(static fn (string $key): string => post_text($key, 10000), $keys)));
        ui_flash('danger', $error);
        ui_redirect($back);
    }
    $data['deadline'] = $data['deadline'] === '' ? null : $data['deadline'];
    $result = $jobId
        ? api_send('PATCH', '/company/jobs/' . $jobId, $data)
        : api_send('POST', '/company/jobs', $data);
    if (!$result['ok']) {
        ui_keep_old(array_map(static fn ($v) => $v ?? '', $data));
        ui_fail($result, $back);
    }
    ui_flash('success', $jobId ? 'Lowongan diperbarui.' : 'Lowongan berhasil dipasang.');
    ui_redirect('/company/jobs.php');
}

if ($intent === 'job_status' || $intent === 'job_delete') {
    $jobId = post_id('job_id');
    if ($jobId === null) {
        ui_flash('danger', 'Lowongan tidak valid.');
        ui_redirect('/company/jobs.php');
    }
    if ($intent === 'job_status') {
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['open', 'closed'], true)) {
            ui_flash('danger', 'Status tidak valid.');
            ui_redirect('/company/jobs.php');
        }
        $result = api_send('PATCH', '/company/jobs/' . $jobId, ['status' => $status]);
        $message = $status === 'open' ? 'Lowongan dibuka kembali.' : 'Lowongan ditutup.';
    } else {
        $result = api_send('DELETE', '/company/jobs/' . $jobId);
        $message = 'Lowongan dihapus.';
    }
    if (!$result['ok']) {
        ui_fail($result, '/company/jobs.php');
    }
    ui_flash('success', $message);
    ui_redirect('/company/jobs.php');
}

// application_status
$applicationId = post_id('application_id');
$jobId = post_id('job_id');
$status = $_POST['status'] ?? '';
$back = $jobId ? '/company/applicants.php?job_id=' . $jobId : '/company/jobs.php';
if ($applicationId === null || !in_array($status, ['reviewed', 'accepted', 'rejected'], true)) {
    ui_flash('danger', 'Data status lamaran tidak valid.');
    ui_redirect($back);
}
$result = api_send('PATCH', '/company/applications/' . $applicationId . '/status', ['status' => $status]);
if (!$result['ok']) {
    ui_fail($result, $back);
}
ui_flash('success', 'Status lamaran diperbarui.');
ui_redirect($back);
