<?php
/**
 * JobNest - Halaman Registrasi (Register)
 *
 * Accessible HTML form posting to the PHP auth action.
 * Supported roles are strictly 'applicant' or 'company'.
 */
require_once __DIR__ . '/../app/includes/layout.php';

$currentUser = get_current_user_data();
$flash = take_public_flash();

render_header([
    'title' => 'Daftar Akun Baru',
    'active' => 'register',
    'description' => 'Bergabung dengan JobNest sebagai pelamar kerja atau perusahaan pemberi kerja.'
]);
?>

<div class="container auth-page-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Daftar Akun JobNest</h1>
            <p class="auth-subtitle">Pilih jenis akun dan lengkapi data pendaftaran Anda.</p>
        </div>

        <?php if ($currentUser): ?>
            <div class="feedback-alert alert-info" role="status">
                <p>Anda saat ini telah masuk sebagai <strong><?= e($currentUser['name'] ?? $currentUser['email'] ?? 'Akun') ?></strong>.</p>
                <div class="auth-signed-in-action">
                    <a href="<?= e(($currentUser['role'] ?? '') === 'admin' ? '/admin/dashboard.php' : (($currentUser['role'] ?? '') === 'company' ? '/company/dashboard.php' : '/applicant/dashboard.php')) ?>" class="btn btn-primary btn-sm">Buka Dashboard</a>
                </div>
            </div>
        <?php else: ?>
            <?php if ($flash): ?><div class="feedback-alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div><?php endif; ?>

            <form action="/auth-action.php?intent=register" method="POST" id="register-form" class="auth-form">
                <input type="hidden" name="form_token" value="<?= e(public_form_token()) ?>">
                <div class="form-group">
                    <fieldset class="role-fieldset">
                        <legend class="form-label">Tipe Akun <span class="required" aria-hidden="true">*</span></legend>
                        <div class="role-options">
                            <label class="role-radio-label">
                                <input type="radio" name="role" value="applicant" checked id="role-applicant">
                                <span class="role-radio-box">
                                    <svg class="icon role-icon" aria-hidden="true"><use href="/assets/icons.svg#user"></use></svg>
                                    <span class="role-title">Pelamar Kerja</span>
                                    <span class="role-desc">Cari dan lamar lowongan kerja</span>
                                </span>
                            </label>
                            <label class="role-radio-label">
                                <input type="radio" name="role" value="company" id="role-company">
                                <span class="role-radio-box">
                                    <svg class="icon role-icon" aria-hidden="true"><use href="/assets/icons.svg#building"></use></svg>
                                    <span class="role-title">Perusahaan</span>
                                    <span class="role-desc">Pasang lowongan & kelola pelamar</span>
                                </span>
                            </label>
                        </div>
                    </fieldset>
                </div>

                <div class="form-group">
                    <label for="reg-name" class="form-label" id="label-reg-name">Nama Lengkap <span class="required" aria-hidden="true">*</span></label>
                    <input
                        type="text"
                        id="reg-name"
                        name="name"
                        class="form-control"
                        required
                        autocomplete="name"
                        placeholder="Contoh: Budi Santoso / PT Maju Berkarya"
                        aria-required="true"
                    >
                </div>

                <div class="form-group">
                    <label for="reg-email" class="form-label">Alamat Email <span class="required" aria-hidden="true">*</span></label>
                    <input
                        type="email"
                        id="reg-email"
                        name="email"
                        class="form-control"
                        required
                        autocomplete="email"
                        placeholder="nama@domain.com"
                        aria-required="true"
                    >
                </div>

                <div class="form-group">
                    <label for="reg-password" class="form-label">Password <span class="required" aria-hidden="true">*</span></label>
                    <input
                        type="password"
                        id="reg-password"
                        name="password"
                        class="form-control"
                        required
                        autocomplete="new-password"
                        placeholder="Minimal 8 karakter"
                        aria-required="true"
                        aria-describedby="password-help"
                    >
                    <small id="password-help" class="form-help">Minimal 8 karakter, kombinasikan huruf besar, huruf kecil, angka, dan simbol.</small>
                </div>

                <div class="form-group">
                    <label for="reg-password-confirmation" class="form-label">Konfirmasi Password <span class="required" aria-hidden="true">*</span></label>
                    <input
                        type="password"
                        id="reg-password-confirmation"
                        name="password_confirmation"
                        class="form-control"
                        required
                        autocomplete="new-password"
                        placeholder="Ulangi password"
                        aria-required="true"
                    >
                </div>

                <div class="form-group auth-submit-group">
                    <button type="submit" class="btn btn-primary btn-block btn-lg" id="register-submit-btn">
                        Daftar Sekarang
                    </button>
                </div>
            </form>

            <div class="auth-footer">
                <p>Sudah memiliki akun? <a href="/login.php" class="link-primary">Masuk di sini</a></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>
