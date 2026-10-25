<?php
/**
 * JobNest - Moderasi Lowongan (Slot B). GET /api/admin/jobs, DELETE /api/admin/jobs/{id}
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('admin');
$page = get_id('page') ?? 1;
$result = api_request('GET', '/admin/jobs?' . http_build_query(['page' => $page]));
$items = $result['ok'] && is_array($result['data']['items'] ?? null) ? $result['data']['items'] : [];

render_dashboard_start($user, 'jobs', 'Moderasi Lowongan', 'Hapus lowongan yang melanggar aturan (spam, penipuan, konten tidak pantas).');
?>
<section class="dashboard-panel">
    <?php if (!$result['ok']): ?>
        <?php render_api_error($result); ?>
    <?php elseif ($items === []): ?>
        <p class="panel-empty">Tidak ada lowongan.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Posisi</th><th scope="col">Perusahaan</th><th scope="col">Status</th><th scope="col">Dibuat</th><th scope="col">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($items as $job): if (!is_array($job) || !is_int($job['id'] ?? null)) continue; ?>
                    <tr>
                        <td><a href="<?= e('/jobs/detail.php?id=' . $job['id']) ?>"><?= e(job_text($job['title'] ?? '')) ?></a></td>
                        <td><?= e(job_text($job['company_name'] ?? '')) ?></td>
                        <td><?= status_badge(job_text($job['status'] ?? '')) ?></td>
                        <td><?= e(format_date($job['created_at'] ?? null)) ?></td>
                        <td>
                            <form method="POST" action="/admin/action.php?intent=delete_job" data-confirm="Hapus lowongan ini dari portal?">
                                <?= form_token_field() ?>
                                <input type="hidden" name="job_id" value="<?= e((string) $job['id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php render_simple_pagination($result['data']['pagination'] ?? null, '/admin/jobs.php'); ?>
    <?php endif; ?>
</section>
<?php render_dashboard_end(); ?>
