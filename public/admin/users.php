<?php
/**
 * JobNest - Kelola Pengguna (Slot B). GET /api/admin/users, PATCH /api/admin/users/{id}/status
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('admin');
$roles = ['' => 'Semua', 'applicant' => 'Pelamar', 'company' => 'Perusahaan', 'admin' => 'Admin'];
$role = is_string($_GET['role'] ?? null) && isset($roles[$_GET['role']]) ? $_GET['role'] : '';
$page = get_id('page') ?? 1;
$query = array_filter(['role' => $role]);
$result = api_request('GET', '/admin/users?' . http_build_query($query + ['page' => $page]));
$items = $result['ok'] && is_array($result['data']['items'] ?? null) ? $result['data']['items'] : [];

render_dashboard_start($user, 'users', 'Kelola Pengguna', 'Akun yang dinonaktifkan langsung keluar dan tidak bisa masuk lagi.');
?>
<nav class="tab-nav" aria-label="Filter peran">
    <?php foreach ($roles as $key => $label): ?>
        <a href="<?= e('/admin/users.php' . ($key !== '' ? '?role=' . $key : '')) ?>" class="tab-link <?= $key === $role ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
<section class="dashboard-panel">
    <?php if (!$result['ok']): ?>
        <?php render_api_error($result); ?>
    <?php elseif ($items === []): ?>
        <p class="panel-empty">Tidak ada pengguna.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Nama</th><th scope="col">Email</th><th scope="col">Peran</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($items as $u): if (!is_array($u) || !is_int($u['id'] ?? null)) continue; $active = ($u['is_active'] ?? false) === true; ?>
                    <tr>
                        <td><?= e(job_text($u['name'] ?? '')) ?></td>
                        <td><?= e(job_text($u['email'] ?? '')) ?></td>
                        <td><?= e(role_label(job_text($u['role'] ?? ''))) ?></td>
                        <td><?= $active ? '<span class="status-badge status-success">Aktif</span>' : '<span class="status-badge status-danger">Nonaktif</span>' ?></td>
                        <td>
                            <?php // Admin accounts (including your own) cannot be changed here; the API also returns 403. ?>
                            <?php if (($u['role'] ?? '') !== 'admin'): ?>
                                <form method="POST" action="/admin/action.php?intent=user_status" <?= $active ? 'data-confirm="Nonaktifkan akun ini?"' : '' ?>>
                                    <?= form_token_field() ?>
                                    <input type="hidden" name="user_id" value="<?= e((string) $u['id']) ?>">
                                    <input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>">
                                    <button type="submit" class="btn <?= $active ? 'btn-danger' : 'btn-primary' ?> btn-sm"><?= $active ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                </form>
                            <?php else: ?>
                                <span class="form-help">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php render_simple_pagination($result['data']['pagination'] ?? null, '/admin/users.php', $query); ?>
    <?php endif; ?>
</section>
<?php render_dashboard_end(); ?>
