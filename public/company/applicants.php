<?php
/**
 * JobNest - Pelamar per Lowongan (Slot B).
 * GET /api/company/jobs/{id}/applications (owner only), status update, CV download.
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('company');
$jobId = get_id('job_id');
if ($jobId === null) {
    render_error_page(404, 'Lowongan tidak ditemukan.');
}
$result = api_request('GET', '/company/jobs/' . $jobId . '/applications');
if (!$result['ok'] && $result['status'] === 404) {
    render_error_page(404, 'Lowongan tidak ditemukan atau bukan milik perusahaan Anda.');
}
$items = $result['ok'] && is_array($result['data']['items'] ?? null) ? $result['data']['items'] : [];
$jobTitle = job_text($result['data']['job']['title'] ?? '');
$statuses = ['reviewed' => 'Ditinjau', 'accepted' => 'Diterima', 'rejected' => 'Ditolak'];

render_dashboard_start($user, 'jobs', 'Pelamar', $jobTitle !== '' ? 'Lowongan: ' . $jobTitle : '');
?>
<?php if (!$result['ok']): ?>
    <?php render_api_error($result); ?>
<?php elseif ($items === []): ?>
    <section class="dashboard-panel"><p class="panel-empty">Belum ada pelamar untuk lowongan ini.</p></section>
<?php else: ?>
    <?php foreach ($items as $app): if (!is_array($app) || !is_int($app['id'] ?? null)) continue; ?>
        <article class="dashboard-panel applicant-card">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title"><?= e(job_text($app['applicant_name'] ?? '')) ?></h2>
                    <p class="panel-text"><?= e(job_text($app['applicant_email'] ?? '')) ?> · <?= e(job_text($app['phone'] ?? '') ?: '-') ?> · Melamar <?= e(format_date($app['created_at'] ?? null)) ?></p>
                </div>
                <?= status_badge(job_text($app['status'] ?? '')) ?>
            </div>
            <dl class="detail-list">
                <div><dt>Pendidikan</dt><dd class="pre-text"><?= e(job_text($app['education'] ?? '') ?: '-') ?></dd></div>
                <div><dt>Keahlian</dt><dd class="pre-text"><?= e(job_text($app['skills'] ?? '') ?: '-') ?></dd></div>
                <div><dt>Surat Lamaran</dt><dd class="pre-text"><?= e(job_text($app['cover_letter'] ?? '') ?: '-') ?></dd></div>
            </dl>
            <div class="table-actions">
                <a href="<?= e('/company/cv.php?application_id=' . $app['id']) ?>" class="btn btn-outline btn-sm">Unduh CV (PDF)</a>
                <form method="POST" action="/company/action.php?intent=application_status" class="inline-form">
                    <?= form_token_field() ?>
                    <input type="hidden" name="application_id" value="<?= e((string) $app['id']) ?>">
                    <input type="hidden" name="job_id" value="<?= e((string) $jobId) ?>">
                    <label for="status-<?= e((string) $app['id']) ?>" class="sr-only">Ubah status</label>
                    <select id="status-<?= e((string) $app['id']) ?>" name="status" class="form-control form-control-sm">
                        <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($app['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Status</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<p><a href="/company/jobs.php" class="link-primary">&larr; Kembali ke daftar lowongan</a></p>
<?php render_dashboard_end(); ?>
