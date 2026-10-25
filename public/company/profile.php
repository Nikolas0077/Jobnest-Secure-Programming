<?php
/**
 * JobNest - Profil Perusahaan (Slot B). GET/PATCH /api/company/profile
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('company');
$profile = api_request('GET', '/company/profile');
$old = ui_take_old();
$p = $profile['ok'] && is_array($profile['data']) ? $profile['data'] : [];

render_dashboard_start($user, 'profile', 'Profil Perusahaan', 'Informasi ini tampil di halaman detail lowongan Anda.');
?>
<?php if (!$profile['ok']): ?>
    <?php render_api_error($profile); ?>
<?php else: ?>
<section class="dashboard-panel">
    <p class="panel-text">Status verifikasi: <?= status_badge(job_text($p['verification_status'] ?? 'pending')) ?></p>
    <div class="feedback-alert alert-info" role="note">Mengubah nama perusahaan akan mengirim ulang profil ke admin untuk diverifikasi.</div>
    <form method="POST" action="/company/action.php?intent=profile" class="dashboard-form">
        <?= form_token_field() ?>
        <div class="form-group">
            <label for="company_name" class="form-label">Nama Perusahaan <span class="required" aria-hidden="true">*</span></label>
            <input type="text" id="company_name" name="company_name" class="form-control" required minlength="2" maxlength="255" value="<?= e(old_value($old, 'company_name', job_text($p['company_name'] ?? ''))) ?>">
        </div>
        <div class="form-group">
            <label for="website" class="form-label">Website</label>
            <input type="url" id="website" name="website" class="form-control" maxlength="255" placeholder="https://perusahaan.co.id" value="<?= e(old_value($old, 'website', job_text($p['website'] ?? ''))) ?>">
            <p class="form-help">Harus diawali http:// atau https://</p>
        </div>
        <div class="form-group">
            <label for="description" class="form-label">Deskripsi Perusahaan</label>
            <textarea id="description" name="description" class="form-control" rows="6" maxlength="5000"><?= e(old_value($old, 'description', job_text($p['description'] ?? ''))) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Simpan Profil</button>
    </form>
</section>
<?php endif; ?>
<?php render_dashboard_end(); ?>
