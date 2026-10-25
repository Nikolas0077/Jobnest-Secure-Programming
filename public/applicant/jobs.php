<?php
/**
 * JobNest - Cari & Lamar Lowongan (Slot B).
 * Same data as the public list (GET /api/jobs), plus Apply and Save buttons for applicants.
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('applicant');
$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$page = get_id('page') ?? 1;
$query = array_filter(['q' => $q]);
$result = api_request('GET', '/jobs?' . http_build_query($query + ['page' => $page, 'per_page' => 10]));
$items = $result['ok'] && is_array($result['data']['items'] ?? null) ? array_filter($result['data']['items'], 'valid_job_summary') : [];

render_dashboard_start($user, 'jobs', 'Cari & Lamar Lowongan', 'Lamaran dikirim bersama CV yang tersimpan di profil Anda.');
?>
<form method="GET" action="/applicant/jobs.php" class="search-inline">
    <div class="form-group">
        <label for="q" class="form-label">Kata kunci</label>
        <input type="text" id="q" name="q" class="form-control" maxlength="100" value="<?= e($q) ?>" placeholder="Contoh: Backend, Jakarta">
    </div>
    <button type="submit" class="btn btn-outline">Cari</button>
</form>

<?php if (!$result['ok']): ?>
    <?php render_api_error($result); ?>
<?php elseif ($items === []): ?>
    <section class="dashboard-panel"><p class="panel-empty">Tidak ada lowongan yang cocok.</p></section>
<?php else: ?>
    <div class="dashboard-jobs-grid">
        <?php foreach ($items as $job): ?>
            <div class="saved-job">
                <?php render_job_card($job); ?>
                <div class="table-actions saved-job-remove">
                    <a href="<?= e('/applicant/apply.php?job_id=' . $job['id']) ?>" class="btn btn-primary btn-sm">Lamar</a>
                    <form method="POST" action="/applicant/action.php?intent=save">
                        <?= form_token_field() ?>
                        <input type="hidden" name="job_id" value="<?= e((string) $job['id']) ?>">
                        <button type="submit" class="btn btn-outline btn-sm">Simpan</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php render_simple_pagination($result['data']['pagination'] ?? null, '/applicant/jobs.php', $query); ?>
<?php endif; ?>
<?php render_dashboard_end(); ?>
