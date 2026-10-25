<?php
/**
 * JobNest - Lowongan Disimpan (Slot B, fitur P1). GET /api/applicant/saved-jobs
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('applicant');
$result = api_request('GET', '/applicant/saved-jobs');
$items = $result['ok'] && is_array($result['data']['items'] ?? null) ? array_filter($result['data']['items'], 'valid_job_summary') : [];

render_dashboard_start($user, 'saved', 'Lowongan Disimpan', 'Lowongan yang sudah ditutup otomatis tidak ditampilkan.');
?>
<?php if (!$result['ok']): ?>
    <?php render_api_error($result); ?>
<?php elseif ($items === []): ?>
    <section class="dashboard-panel"><p class="panel-empty">Belum ada lowongan yang disimpan.</p></section>
<?php else: ?>
    <div class="jobs-grid dashboard-jobs-grid">
        <?php foreach ($items as $job): ?>
            <div class="saved-job">
                <?php render_job_card($job); ?>
                <form method="POST" action="/applicant/action.php?intent=unsave" class="saved-job-remove">
                    <?= form_token_field() ?>
                    <input type="hidden" name="job_id" value="<?= e((string) $job['id']) ?>">
                    <input type="hidden" name="back" value="saved">
                    <button type="submit" class="btn btn-secondary btn-sm">Hapus dari simpanan</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_dashboard_end(); ?>
