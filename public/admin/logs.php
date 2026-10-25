<?php
/**
 * JobNest - Log Aktivitas (Slot B). GET /api/admin/logs?page=
 * Logs contain only action, user, target, IP, and time (no passwords, tokens, or CV content).
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('admin');
$page = get_id('page') ?? 1;
$result = api_request('GET', '/admin/logs?' . http_build_query(['page' => $page]));
$items = $result['ok'] && is_array($result['data']['items'] ?? null) ? $result['data']['items'] : [];

render_dashboard_start($user, 'logs', 'Log Aktivitas');
?>
<section class="dashboard-panel">
    <?php if (!$result['ok']): ?>
        <?php render_api_error($result); ?>
    <?php elseif ($items === []): ?>
        <p class="panel-empty">Belum ada aktivitas.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Waktu</th><th scope="col">Pengguna</th><th scope="col">Aksi</th><th scope="col">Target</th><th scope="col">IP</th></tr></thead>
                <tbody>
                <?php foreach ($items as $log): if (!is_array($log)) continue; ?>
                    <tr>
                        <td><?= e(job_text($log['created_at'] ?? '')) ?></td>
                        <td><?= e(job_text($log['user_name'] ?? '') ?: (is_int($log['user_id'] ?? null) ? '#' . $log['user_id'] : 'Tamu')) ?></td>
                        <td><code><?= e(job_text($log['action'] ?? '')) ?></code></td>
                        <td><?= e(job_text($log['target_type'] ?? '') !== '' ? job_text($log['target_type']) . ' #' . job_text($log['target_id'] ?? '') : '-') ?></td>
                        <td><?= e(job_text($log['ip_address'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php render_simple_pagination($result['data']['pagination'] ?? null, '/admin/logs.php'); ?>
    <?php endif; ?>
</section>
<?php render_dashboard_end(); ?>
