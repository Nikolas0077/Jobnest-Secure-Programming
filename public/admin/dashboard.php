<?php
/**
 * JobNest - Dashboard Admin (Slot B). GET /api/admin/summary
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('admin');
$summary = api_request('GET', '/admin/summary');
$s = $summary['ok'] && is_array($summary['data']) ? $summary['data'] : [];
$num = static fn (string $key): string => is_int($s[$key] ?? null) ? (string) $s[$key] : '-';

render_dashboard_start($user, 'dashboard', 'Dashboard Admin', 'Verifikasi perusahaan dan moderasi konten.');
?>
<?php if (!$summary['ok']): ?>
    <?php render_api_error($summary); ?>
<?php else: ?>
    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-label">Perusahaan Menunggu</span>
            <strong class="stat-value"><?= e($num('companies_pending')) ?></strong>
            <a href="/admin/companies.php?verified=pending" class="link-primary">Tinjau sekarang</a>
        </div>
        <div class="stat-card">
            <span class="stat-label">Total Pengguna</span>
            <strong class="stat-value"><?= e($num('users_total')) ?></strong>
            <span class="form-help"><?= e($num('users_inactive')) ?> nonaktif</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Lowongan Dibuka</span>
            <strong class="stat-value"><?= e($num('jobs_active')) ?></strong>
            <a href="/admin/jobs.php" class="link-primary">Moderasi lowongan</a>
        </div>
        <div class="stat-card">
            <span class="stat-label">Total Lamaran</span>
            <strong class="stat-value"><?= e($num('applications_total')) ?></strong>
            <a href="/admin/logs.php" class="link-primary">Lihat log aktivitas</a>
        </div>
    </div>
<?php endif; ?>
<?php render_dashboard_end(); ?>
