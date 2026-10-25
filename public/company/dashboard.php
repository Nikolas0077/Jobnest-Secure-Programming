<?php
/**
 * JobNest - Dashboard Perusahaan (Slot B)
 * GET /api/company/profile + GET /api/company/jobs.
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('company');
$profile = api_request('GET', '/company/profile');
$jobs = api_request('GET', '/company/jobs');
$items = $jobs['ok'] && is_array($jobs['data']['items'] ?? null) ? $jobs['data']['items'] : [];
$openCount = count(array_filter($items, static fn ($j) => is_array($j) && ($j['status'] ?? '') === 'open'));
$appCount = array_sum(array_map(static fn ($j) => is_array($j) && is_int($j['applications_count'] ?? null) ? $j['applications_count'] : 0, $items));
$status = job_text($profile['data']['verification_status'] ?? 'pending');

render_dashboard_start($user, 'dashboard', 'Dashboard Perusahaan', 'Kelola lowongan dan pelamar perusahaan Anda.');
?>
<?php if (!$profile['ok']): ?>
    <?php render_api_error($profile); ?>
<?php else: ?>
    <?php if ($status !== 'approved'): ?>
        <div class="feedback-alert <?= $status === 'rejected' ? 'alert-danger' : 'alert-info' ?>" role="status">
            <?= $status === 'rejected'
                ? 'Verifikasi perusahaan ditolak admin. Perbarui profil perusahaan Anda dengan data yang benar.'
                : 'Perusahaan Anda sedang menunggu verifikasi admin. Lowongan baru bisa dipasang setelah disetujui.' ?>
        </div>
    <?php endif; ?>
    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-label">Status Verifikasi</span>
            <strong class="stat-value"><?= status_badge($status) ?></strong>
            <a href="/company/profile.php" class="link-primary">Lihat profil</a>
        </div>
        <div class="stat-card">
            <span class="stat-label">Lowongan Dibuka</span>
            <strong class="stat-value"><?= e((string) $openCount) ?></strong>
            <a href="/company/jobs.php" class="link-primary">Kelola lowongan</a>
        </div>
        <div class="stat-card">
            <span class="stat-label">Total Pelamar</span>
            <strong class="stat-value"><?= e((string) $appCount) ?></strong>
            <span class="form-help">Dari semua lowongan</span>
        </div>
    </div>
<?php endif; ?>

<section class="dashboard-panel">
    <div class="panel-header">
        <h2 class="panel-title">Lowongan Terbaru</h2>
        <?php if ($status === 'approved'): ?><a href="/company/job-form.php" class="btn btn-primary btn-sm">Pasang Lowongan</a><?php endif; ?>
    </div>
    <?php if (!$jobs['ok']): ?>
        <?php render_api_error($jobs); ?>
    <?php elseif ($items === []): ?>
        <p class="panel-empty">Belum ada lowongan.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Posisi</th><th scope="col">Status</th><th scope="col">Batas</th><th scope="col">Pelamar</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($items, 0, 5) as $job): if (!is_array($job) || !is_int($job['id'] ?? null)) continue; ?>
                    <tr>
                        <td><?= e(job_text($job['title'] ?? '')) ?></td>
                        <td><?= status_badge(job_text($job['status'] ?? '')) ?></td>
                        <td><?= e(format_date($job['deadline'] ?? null)) ?></td>
                        <td><a href="<?= e('/company/applicants.php?job_id=' . $job['id']) ?>"><?= e((string) ($job['applications_count'] ?? 0)) ?> pelamar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php render_dashboard_end(); ?>
