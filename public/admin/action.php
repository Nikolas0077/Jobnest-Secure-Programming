<?php
/**
 * JobNest - Aksi form admin (Slot B).
 * POST /admin/action.php?intent=verify_company|user_status|delete_job
 * The API enforces role admin, blocks self-disable, and logs every action.
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$intents = ['verify_company', 'user_status', 'delete_job'];
$intent = $_GET['intent'] ?? '';
if (!is_string($intent) || !in_array($intent, $intents, true)) {
    render_error_page(404, 'Aksi tidak ditemukan.');
}
ui_verify_post();
ui_require_role('admin');

if ($intent === 'verify_company') {
    $companyId = post_id('company_id');
    $decision = $_POST['decision'] ?? '';
    $back = '/admin/companies.php?verified=' . (in_array($_POST['back'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_POST['back'] : 'pending');
    if ($companyId === null || !in_array($decision, ['approve', 'reject'], true)) {
        ui_flash('danger', 'Data verifikasi tidak valid.');
        ui_redirect($back);
    }
    $result = api_send('PATCH', '/admin/companies/' . $companyId . '/verification', ['is_verified' => $decision === 'approve']);
    if (!$result['ok']) {
        ui_fail($result, $back);
    }
    ui_flash('success', $decision === 'approve' ? 'Perusahaan disetujui.' : 'Verifikasi perusahaan ditolak.');
    ui_redirect($back);
}

if ($intent === 'user_status') {
    $userId = post_id('user_id');
    $active = $_POST['active'] ?? '';
    if ($userId === null || !in_array($active, ['0', '1'], true)) {
        ui_flash('danger', 'Data pengguna tidak valid.');
        ui_redirect('/admin/users.php');
    }
    $result = api_send('PATCH', '/admin/users/' . $userId . '/status', ['is_active' => $active === '1']);
    if (!$result['ok']) {
        ui_fail($result, '/admin/users.php');
    }
    ui_flash('success', $active === '1' ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    ui_redirect('/admin/users.php');
}

$jobId = post_id('job_id');
if ($jobId === null) {
    ui_flash('danger', 'Lowongan tidak valid.');
    ui_redirect('/admin/jobs.php');
}
$result = api_send('DELETE', '/admin/jobs/' . $jobId);
if (!$result['ok']) {
    ui_fail($result, '/admin/jobs.php');
}
ui_flash('success', 'Lowongan dihapus dari portal.');
ui_redirect('/admin/jobs.php');
