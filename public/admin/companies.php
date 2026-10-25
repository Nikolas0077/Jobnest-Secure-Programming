<?php
/**
 * JobNest - Verifikasi Perusahaan (Slot B).
 * GET /api/admin/companies?verified=pending|approved|rejected, PATCH .../verification
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('admin');
$tabs = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
$filter = is_string($_GET['verified'] ?? null) && isset($tabs[$_GET['verified']]) ? $_GET['verified'] : 'pending';
$result = api_request('GET', '/admin/companies?' . http_build_query(['verified' => $filter]));
$items = $result['ok'] && is_array($result['data']['items'] ?? null) ? $result['data']['items'] : [];

render_dashboard_start($user, 'companies', 'Verifikasi Perusahaan', 'Hanya lowongan dari perusahaan yang disetujui yang tampil ke publik.');
?>
<nav class="tab-nav" aria-label="Filter status">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="<?= e('/admin/companies.php?verified=' . $key) ?>" class="tab-link <?= $key === $filter ? 'active' : '' ?>" <?= $key === $filter ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
<section class="dashboard-panel">
    <?php if (!$result['ok']): ?>
        <?php render_api_error($result); ?>
    <?php elseif ($items === []): ?>
        <p class="panel-empty">Tidak ada perusahaan dengan status ini.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Perusahaan</th><th scope="col">Email Akun</th><th scope="col">Website</th><th scope="col">Daftar</th><th scope="col">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($items as $c): if (!is_array($c) || !is_int($c['id'] ?? null)) continue; $site = job_text($c['website'] ?? ''); ?>
                    <tr>
                        <td>
                            <strong><?= e(job_text($c['company_name'] ?? '')) ?></strong>
                            <?php if (job_text($c['description'] ?? '') !== ''): ?><p class="form-help pre-text"><?= e(mb_substr(job_text($c['description']), 0, 200)) ?></p><?php endif; ?>
                        </td>
                        <td><?= e(job_text($c['email'] ?? '')) ?></td>
                        <td>
                            <?php // Only http(s) links become clickable; anything else stays plain text. ?>
                            <?php if (preg_match('#^https?://#i', $site)): ?>
                                <a href="<?= e($site) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($site) ?></a>
                            <?php else: ?><?= e($site ?: '-') ?><?php endif; ?>
                        </td>
                        <td><?= e(format_date($c['created_at'] ?? null)) ?></td>
                        <td>
                            <div class="table-actions">
                                <?php if ($filter !== 'approved'): ?>
                                    <form method="POST" action="/admin/action.php?intent=verify_company">
                                        <?= form_token_field() ?>
                                        <input type="hidden" name="company_id" value="<?= e((string) $c['id']) ?>">
                                        <input type="hidden" name="decision" value="approve">
                                        <input type="hidden" name="back" value="<?= e($filter) ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Setujui</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($filter !== 'rejected'): ?>
                                    <form method="POST" action="/admin/action.php?intent=verify_company" data-confirm="Tolak verifikasi perusahaan ini? Lowongannya akan disembunyikan dari publik.">
                                        <?= form_token_field() ?>
                                        <input type="hidden" name="company_id" value="<?= e((string) $c['id']) ?>">
                                        <input type="hidden" name="decision" value="reject">
                                        <input type="hidden" name="back" value="<?= e($filter) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
                                    </form>
                                <?php endif; ?>
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
