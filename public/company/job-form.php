<?php
/**
 * JobNest - Form Pasang/Ubah Lowongan (Slot B).
 * Without ?id: POST /api/company/jobs. With ?id: GET + PATCH /api/company/jobs/{id}.
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('company');
$jobId = get_id('id');
$job = [];
if ($jobId !== null) {
    $result = api_request('GET', '/company/jobs/' . $jobId);
    if (!$result['ok']) {
        // 404 for jobs of another company: the API checks ownership.
        render_error_page($result['status'] === 404 ? 404 : 500, api_error_message($result));
    }
    $job = is_array($result['data']) ? $result['data'] : [];
} else {
    $profile = api_request('GET', '/company/profile');
    if ($profile['ok'] && ($profile['data']['is_verified'] ?? false) !== true) {
        ui_flash('danger', 'Perusahaan harus diverifikasi admin sebelum memasang lowongan.');
        ui_redirect('/company/dashboard.php');
    }
}
$old = ui_take_old();
$v = static fn (string $key): string => old_value($old, $key, job_text($job[$key] ?? ''));

render_dashboard_start($user, $jobId ? 'jobs' : 'job-new', $jobId ? 'Ubah Lowongan' : 'Pasang Lowongan Baru');
?>
<section class="dashboard-panel">
    <form method="POST" action="/company/action.php?intent=<?= $jobId ? 'job_update' : 'job_create' ?>" class="dashboard-form">
        <?= form_token_field() ?>
        <?php if ($jobId): ?><input type="hidden" name="job_id" value="<?= e((string) $jobId) ?>"><?php endif; ?>
        <div class="form-group">
            <label for="title" class="form-label">Judul Posisi <span class="required" aria-hidden="true">*</span></label>
            <input type="text" id="title" name="title" class="form-control" required minlength="3" maxlength="150" value="<?= e($v('title')) ?>">
        </div>
        <div class="form-grid-2">
            <div class="form-group">
                <label for="location" class="form-label">Lokasi <span class="required" aria-hidden="true">*</span></label>
                <input type="text" id="location" name="location" class="form-control" required minlength="2" maxlength="100" value="<?= e($v('location')) ?>">
            </div>
            <div class="form-group">
                <label for="category" class="form-label">Kategori <span class="required" aria-hidden="true">*</span></label>
                <input type="text" id="category" name="category" class="form-control" required minlength="2" maxlength="100" placeholder="IT, Marketing, Keuangan" value="<?= e($v('category')) ?>">
            </div>
            <div class="form-group">
                <label for="salary_min" class="form-label">Gaji Minimum (Rp)</label>
                <input type="number" id="salary_min" name="salary_min" class="form-control" min="0" max="2000000000" step="1" value="<?= e($v('salary_min')) ?>">
            </div>
            <div class="form-group">
                <label for="salary_max" class="form-label">Gaji Maksimum (Rp)</label>
                <input type="number" id="salary_max" name="salary_max" class="form-control" min="0" max="2000000000" step="1" value="<?= e($v('salary_max')) ?>">
            </div>
            <div class="form-group">
                <label for="deadline" class="form-label">Batas Lamaran</label>
                <input type="date" id="deadline" name="deadline" class="form-control" min="<?= e(date('Y-m-d')) ?>" value="<?= e($v('deadline')) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="description" class="form-label">Deskripsi & Kualifikasi <span class="required" aria-hidden="true">*</span></label>
            <textarea id="description" name="description" class="form-control" rows="10" required minlength="10" maxlength="10000"><?= e($v('description')) ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $jobId ? 'Simpan Perubahan' : 'Pasang Lowongan' ?></button>
            <a href="/company/jobs.php" class="btn btn-outline">Batal</a>
        </div>
    </form>
</section>
<?php render_dashboard_end(); ?>
