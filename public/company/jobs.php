<?php
/**
 * JobNest - Lowongan Saya (Slot B). GET /api/company/jobs
 * Actions: edit, open/close, delete (soft), and view applicants.
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('company');
$jobs = api_request('GET', '/company/jobs');
$items = $jobs['ok'] && is_array($jobs['data']['items'] ?? null) ? $jobs['data']['items'] : [];

render_dashboard_start($user, 'jobs', 'Lowongan Saya');
?>
<section class="dashboard-panel">
    <div class="panel-header">
        <h2 class="panel-title">Daftar Lowongan</h2>
        <a href="/company/job-form.php" class="btn btn-primary btn-sm">Pasang Lowongan</a>
    </div>
    <?php if (!$jobs['ok']): ?>
        <?php render_api_error($jobs); ?>
    <?php elseif ($items === []): ?>
        <p class="panel-empty">Belum ada lowongan.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Posisi</th><th scope="col">Status</th><th scope="col">Batas</th><th scope="col">Pelamar</th><th scope="col">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($items as $job): if (!is_array($job) || !is_int($job['id'] ?? null)) continue; $isOpen = ($job['status'] ?? '') === 'open'; ?>
                    <tr>
                        <td><?= e(job_text($job['title'] ?? '')) ?></td>
                        <td><?= status_badge(job_text($job['status'] ?? '')) ?></td>
                        <td><?= e(format_date($job['deadline'] ?? null)) ?></td>
                        <td><a href="<?= e('/company/applicants.php?job_id=' . $job['id']) ?>"><?= e((string) ($job['applications_count'] ?? 0)) ?> pelamar</a></td>
                        <td>
                            <div class="table-actions">
                                <a href="<?= e('/company/job-form.php?id=' . $job['id']) ?>" class="btn btn-outline btn-sm">Ubah</a>
                                <form method="POST" action="/company/action.php?intent=job_status">
                                    <?= form_token_field() ?>
                                    <input type="hidden" name="job_id" value="<?= e((string) $job['id']) ?>">
                                    <input type="hidden" name="status" value="<?= $isOpen ? 'closed' : 'open' ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm"><?= $isOpen ? 'Tutup' : 'Buka Lagi' ?></button>
                                </form>
                                <form method="POST" action="/company/action.php?intent=job_delete" data-confirm="Hapus lowongan ini? Tindakan ini tidak dapat dibatalkan.">
                                    <?= form_token_field() ?>
                                    <input type="hidden" name="job_id" value="<?= e((string) $job['id']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php render_dashboard_end(); ?>
