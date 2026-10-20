<?php
/**
 * JobNest - Halaman Masuk (Login)
 *
 * Accessible HTML form posting to the PHP auth action.
 */
require_once __DIR__ . '/../app/includes/layout.php';

// If user is already logged in, show status or link to dashboard
$currentUser = get_current_user_data();
$flash = take_public_flash();

render_header([
    'title' => 'Masuk ke Akun',
    'active' => 'login',
    'description' => 'Masuk ke akun JobNest Anda untuk melamar lowongan atau mengelola publikasi pekerjaan.'
]);
?>

<div class="container auth-page-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Masuk ke JobNest</h1>
            <p class="auth-subtitle">Silakan masukkan email dan password akun Anda.</p>
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

            <form action="/auth-action.php?intent=login" method="POST" id="login-form" class="auth-form">
                <input type="hidden" name="form_token" value="<?= e(public_form_token()) ?>">
                <div class="form-group">
                    <label for="login-email" class="form-label">Alamat Email <span class="required" aria-hidden="true">*</span></label>
                    <input
                        type="email"
                        id="login-email"
                        name="email"
                        class="form-control"
                        required
                        autocomplete="email"
                        placeholder="nama@domain.com"
                        aria-required="true"
                    >
                </div>

                <div class="form-group">
                    <div class="label-with-link">
                        <label for="login-password" class="form-label">Password <span class="required" aria-hidden="true">*</span></label>
                        <a href="/forgot-password.php" class="link-muted">Lupa password?</a>
                    </div>
                    <input
                        type="password"
                        id="login-password"
                        name="password"
                        class="form-control"
                        required
                        autocomplete="current-password"
                        placeholder="Masukkan password Anda"
                        aria-required="true"
                    >
                </div>

                <div class="form-group auth-submit-group">
                    <button type="submit" class="btn btn-primary btn-block btn-lg" id="login-submit-btn">
                        Masuk
                    </button>
                </div>
            </form>

            <div class="auth-footer">
                <p>Belum memiliki akun? <a href="/register.php" class="link-primary">Daftar sekarang</a></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>
