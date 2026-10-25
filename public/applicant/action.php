<?php
/**
 * JobNest - Aksi form pelamar (Slot B).
 * POST /applicant/action.php?intent=profile|cv|apply|save|unsave
 * Checks the UI form token, validates input, calls the API, then redirects (303).
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$intents = ['profile', 'cv', 'apply', 'save', 'unsave'];
$intent = $_GET['intent'] ?? '';
if (!is_string($intent) || !in_array($intent, $intents, true)) {
    render_error_page(404, 'Aksi tidak ditemukan.');
}
ui_verify_post();
ui_require_role('applicant');

if ($intent === 'profile') {
    $data = [
        'phone' => post_text('phone', 20),
        'address' => post_text('address', 500),
        'education' => post_text('education', 1000),
        'skills' => post_text('skills', 1000),
    ];
    if ($data['phone'] !== '' && !preg_match('/^\+?[0-9][0-9 \-]{6,18}$/', $data['phone'])) {
        ui_keep_old($data);
        ui_flash('danger', 'Nomor telepon tidak valid.');
        ui_redirect('/applicant/profile.php');
    }
    $result = api_send('PATCH', '/applicant/profile', array_map(static fn (string $v): ?string => $v === '' ? null : $v, $data));
    if (!$result['ok']) {
        ui_keep_old($data);
        ui_fail($result, '/applicant/profile.php');
    }
    ui_flash('success', 'Profil berhasil disimpan.');
    ui_redirect('/applicant/profile.php');
}

if ($intent === 'cv') {
    $file = $_FILES['cv'] ?? null;
    // First check here (the API checks again): one file, uploaded OK, <= 2 MB, real PDF.
    if (!is_array($file) || is_array($file['error'] ?? null) || ($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $tooBig = in_array($file['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        ui_flash('danger', $tooBig ? 'Ukuran CV maksimal 2 MB.' : 'Pilih file CV terlebih dahulu.');
        ui_redirect('/applicant/profile.php#cv-section');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($file['size'] > 2 * 1024 * 1024 || $mime !== 'application/pdf'
        || strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        ui_flash('danger', 'CV harus berupa file PDF dengan ukuran maksimal 2 MB.');
        ui_redirect('/applicant/profile.php#cv-section');
    }
    $result = api_upload('/applicant/cv', 'cv', $file['tmp_name'], 'application/pdf', 'cv.pdf');
    if (!$result['ok']) {
        ui_fail($result, '/applicant/profile.php#cv-section');
    }
    ui_flash('success', 'CV berhasil diunggah.');
    ui_redirect('/applicant/profile.php#cv-section');
}

$jobId = post_id('job_id');
if ($jobId === null) {
    ui_flash('danger', 'Lowongan tidak valid.');
    ui_redirect('/applicant/jobs.php');
}

if ($intent === 'apply') {
    $coverLetter = post_text('cover_letter', 5000);
    $result = api_send('POST', '/jobs/' . $jobId . '/applications', ['cover_letter' => $coverLetter === '' ? null : $coverLetter]);
    if (!$result['ok']) {
        if ($result['status'] === 409) {
            ui_flash('info', 'Anda sudah melamar lowongan ini. Lihat statusnya di Riwayat Lamaran.');
            ui_redirect('/applicant/applications.php');
        }
        ui_keep_old(['cover_letter' => $coverLetter]);
        ui_fail($result, '/applicant/apply.php?job_id=' . $jobId);
    }
    ui_flash('success', 'Lamaran berhasil dikirim.');
    ui_redirect('/applicant/applications.php');
}

$back = ($_POST['back'] ?? '') === 'saved' ? '/applicant/saved-jobs.php' : '/applicant/jobs.php';
$result = $intent === 'save'
    ? api_send('POST', '/applicant/saved-jobs/' . $jobId)
    : api_send('DELETE', '/applicant/saved-jobs/' . $jobId);
if (!$result['ok']) {
    ui_fail($result, $back);
}
ui_flash('success', $intent === 'save' ? 'Lowongan disimpan.' : 'Lowongan dihapus dari simpanan.');
ui_redirect($back);
