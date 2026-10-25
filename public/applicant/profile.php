<?php
/**
 * JobNest - Profil & CV Pelamar (Slot B)
 * GET/PATCH /api/applicant/profile, POST /api/applicant/cv (multipart, PDF max 2 MB).
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role('applicant');
$profile = api_request('GET', '/applicant/profile');
$old = ui_take_old();
$p = $profile['ok'] && is_array($profile['data']) ? $profile['data'] : [];

render_dashboard_start($user, 'profile', 'Profil & CV', 'Data ini dilihat perusahaan saat Anda melamar.');
?>
<?php if (!$profile['ok']): ?>
    <?php render_api_error($profile); ?>
<?php else: ?>
<section class="dashboard-panel">
    <h2 class="panel-title">Data Pribadi</h2>
    <dl class="detail-list">
        <div><dt>Nama</dt><dd><?= e(job_text($p['name'] ?? '')) ?></dd></div>
        <div><dt>Email</dt><dd><?= e(job_text($p['email'] ?? '')) ?></dd></div>
    </dl>
    <form method="POST" action="/applicant/action.php?intent=profile" class="dashboard-form">
        <?= form_token_field() ?>
        <div class="form-group">
            <label for="phone" class="form-label">Nomor Telepon</label>
            <input type="tel" id="phone" name="phone" class="form-control" maxlength="20" pattern="\+?[0-9][0-9 \-]{6,18}" value="<?= e(old_value($old, 'phone', job_text($p['phone'] ?? ''))) ?>" autocomplete="tel">
            <p class="form-help">Contoh: 081234567890</p>
        </div>
        <div class="form-group">
            <label for="address" class="form-label">Alamat</label>
            <textarea id="address" name="address" class="form-control" rows="2" maxlength="500"><?= e(old_value($old, 'address', job_text($p['address'] ?? ''))) ?></textarea>
        </div>
        <div class="form-group">
            <label for="education" class="form-label">Pendidikan</label>
            <textarea id="education" name="education" class="form-control" rows="3" maxlength="1000"><?= e(old_value($old, 'education', job_text($p['education'] ?? ''))) ?></textarea>
        </div>
        <div class="form-group">
            <label for="skills" class="form-label">Keahlian</label>
            <textarea id="skills" name="skills" class="form-control" rows="3" maxlength="1000"><?= e(old_value($old, 'skills', job_text($p['skills'] ?? ''))) ?></textarea>
            <p class="form-help">Pisahkan dengan koma, misalnya: PHP, MySQL, Komunikasi</p>
        </div>
        <button type="submit" class="btn btn-primary">Simpan Profil</button>
    </form>
</section>

<section class="dashboard-panel" id="cv-section">
    <h2 class="panel-title">Curriculum Vitae (CV)</h2>
    <p class="panel-text">
        Status: <?= ($p['cv_available'] ?? false) === true ? status_badge('approved') . ' CV sudah tersimpan.' : status_badge('pending') . ' Belum ada CV.' ?>
    </p>
    <form method="POST" action="/applicant/action.php?intent=cv" enctype="multipart/form-data" class="dashboard-form">
        <?= form_token_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
        <div class="form-group">
            <label for="cv" class="form-label">File CV (PDF, maks. 2 MB) <span class="required" aria-hidden="true">*</span></label>
            <input type="file" id="cv" name="cv" class="form-control" accept="application/pdf,.pdf" required>
            <p class="form-help">CV disimpan privat dan hanya bisa diunduh perusahaan tempat Anda melamar. Mengunggah CV baru mengganti CV lama untuk lamaran berikutnya.</p>
        </div>
        <button type="submit" class="btn btn-primary">Unggah CV</button>
    </form>
</section>
<?php endif; ?>
<?php render_dashboard_end(); ?>
