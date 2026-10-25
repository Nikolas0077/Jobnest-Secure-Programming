<?php
/**
 * JobNest - Form Lamar Lowongan (Slot B).
 * Shows the job and a cover letter form; POST goes to action.php?intent=apply,
 * which calls POST /api/jobs/{id}/applications with the saved CV.
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('applicant');
$jobId = get_id('job_id');
if ($jobId === null) {
    render_error_page(404, 'Lowongan tidak ditemukan.');
}
$job = api_request('GET', '/jobs/' . $jobId);
$profile = api_request('GET', '/applicant/profile');
$old = ui_take_old();
$cvReady = $profile['ok'] && ($profile['data']['cv_available'] ?? false) === true;

render_dashboard_start($user, 'applications', 'Lamar Lowongan');
?>
<?php if (!$job['ok'] || !is_string($job['data']['title'] ?? null)): ?>
    <?php render_job_state('unavailable', 'Lowongan Tidak Tersedia', 'Lowongan ini tidak ditemukan atau sudah ditutup.', '/applicant/jobs.php'); ?>
<?php else: ?>
<section class="dashboard-panel">
    <h2 class="panel-title"><?= e($job['data']['title']) ?></h2>
    <p class="panel-text"><?= e(job_text($job['data']['company']['name'] ?? '')) ?> · <?= e(job_text($job['data']['location'] ?? '')) ?> · Batas: <?= e(job_text($job['data']['deadline'] ?? '') ?: 'Terbuka') ?></p>

    <?php if (!$cvReady): ?>
        <div class="feedback-alert alert-danger" role="alert">
            Anda belum mengunggah CV. <a href="/applicant/profile.php#cv-section" class="link-primary">Unggah CV dulu</a>, lalu kembali ke halaman ini.
        </div>
    <?php else: ?>
        <form method="POST" action="/applicant/action.php?intent=apply" class="dashboard-form">
            <?= form_token_field() ?>
            <input type="hidden" name="job_id" value="<?= e((string) $jobId) ?>">
            <div class="form-group">
                <label for="cover_letter" class="form-label">Surat Lamaran (opsional)</label>
                <textarea id="cover_letter" name="cover_letter" class="form-control" rows="8" maxlength="5000" placeholder="Ceritakan singkat mengapa Anda cocok untuk posisi ini."><?= e(old_value($old, 'cover_letter')) ?></textarea>
                <p class="form-help">Maksimal 5000 karakter. CV yang tersimpan di profil akan dilampirkan.</p>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Kirim Lamaran</button>
                <a href="<?= e('/applicant/jobs.php') ?>" class="btn btn-outline">Batal</a>
            </div>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php render_dashboard_end(); ?>
