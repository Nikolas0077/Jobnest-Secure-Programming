<?php
/**
 * JobNest - Riwayat Lamaran (Slot B). GET /api/applicant/applications?page=
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('applicant');
$page = get_id('page') ?? 1;
$result = api_request('GET', '/applicant/applications?' . http_build_query(['page' => $page]));
$items = $result['ok'] && is_array($result['data']['items'] ?? null) ? $result['data']['items'] : [];

render_dashboard_start($user, 'applications', 'Riwayat Lamaran', 'Status diperbarui oleh perusahaan setelah meninjau lamaran Anda.');
?>
<section class="dashboard-panel">
    <?php if (!$result['ok']): ?>
        <?php render_api_error($result); ?>
    <?php elseif ($items === []): ?>
        <p class="panel-empty">Belum ada lamaran. <a href="/applicant/jobs.php" class="link-primary">Cari lowongan</a></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Posisi</th><th scope="col">Perusahaan</th><th scope="col">Dikirim</th><th scope="col">Status</th></tr></thead>
                <tbody>
                <?php foreach ($items as $app): if (!is_array($app) || !is_int($app['job_id'] ?? null)) continue; ?>
                    <tr>
                        <td><a href="<?= e('/jobs/detail.php?id=' . $app['job_id']) ?>"><?= e(job_text($app['job_title'] ?? '')) ?></a></td>
                        <td><?= e(job_text($app['company_name'] ?? '')) ?></td>
                        <td><?= e(format_date($app['created_at'] ?? null)) ?></td>
                        <td><?= status_badge(job_text($app['status'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php render_simple_pagination($result['data']['pagination'] ?? null, '/applicant/applications.php'); ?>
    <?php endif; ?>
</section>
<?php render_dashboard_end(); ?>
