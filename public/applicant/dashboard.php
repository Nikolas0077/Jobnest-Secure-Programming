<?php
/**
 * JobNest - Dashboard Pelamar (Slot B)
 * GET /api/applicant/profile + GET /api/applicant/applications.
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('applicant');
$profile = api_request('GET', '/applicant/profile');
$applications = api_request('GET', '/applicant/applications?page=1');
$items = $applications['ok'] && is_array($applications['data']['items'] ?? null) ? array_slice($applications['data']['items'], 0, 5) : [];
$total = (int) ($applications['data']['pagination']['total'] ?? count($items));

render_dashboard_start($user, 'dashboard', 'Dashboard Pelamar', 'Pantau profil, CV, dan status lamaran Anda.');
?>
<?php if (!$profile['ok']): ?>
    <?php render_api_error($profile); ?>
<?php else: ?>
    <?php $p = $profile['data']; $cvReady = ($p['cv_available'] ?? false) === true; ?>
    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-label">Status CV</span>
            <strong class="stat-value"><?= $cvReady ? 'Sudah diunggah' : 'Belum ada' ?></strong>
            <a href="/applicant/profile.php#cv-section" class="link-primary"><?= $cvReady ? 'Ganti CV' : 'Unggah CV sekarang' ?></a>
        </div>
        <div class="stat-card">
            <span class="stat-label">Total Lamaran</span>
            <strong class="stat-value"><?= e((string) $total) ?></strong>
            <a href="/applicant/applications.php" class="link-primary">Lihat riwayat</a>
        </div>
        <div class="stat-card">
            <span class="stat-label">Kelengkapan Profil</span>
            <?php $filled = count(array_filter([$p['phone'] ?? null, $p['address'] ?? null, $p['education'] ?? null, $p['skills'] ?? null], static fn ($v) => is_string($v) && $v !== '')); ?>
            <strong class="stat-value"><?= e((string) $filled) ?>/4</strong>
            <a href="/applicant/profile.php" class="link-primary">Lengkapi profil</a>
        </div>
    </div>
    <?php if (!$cvReady): ?>
        <div class="feedback-alert alert-info" role="status">Anda perlu mengunggah CV (PDF, maks. 2 MB) sebelum bisa melamar lowongan.</div>
    <?php endif; ?>
<?php endif; ?>

<section class="dashboard-panel">
    <div class="panel-header">
        <h2 class="panel-title">Lamaran Terbaru</h2>
        <a href="/applicant/jobs.php" class="btn btn-primary btn-sm">Cari Lowongan</a>
    </div>
    <?php if (!$applications['ok']): ?>
        <?php render_api_error($applications); ?>
    <?php elseif ($items === []): ?>
        <p class="panel-empty">Belum ada lamaran. Mulai cari lowongan yang sesuai.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Posisi</th><th scope="col">Perusahaan</th><th scope="col">Tanggal</th><th scope="col">Status</th></tr></thead>
                <tbody>
                <?php foreach ($items as $app): if (!is_array($app)) continue; ?>
                    <tr>
                        <td><?= e(job_text($app['job_title'] ?? '')) ?></td>
                        <td><?= e(job_text($app['company_name'] ?? '')) ?></td>
                        <td><?= e(format_date($app['created_at'] ?? null)) ?></td>
                        <td><?= status_badge(job_text($app['status'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php render_dashboard_end(); ?>
