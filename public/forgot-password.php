<?php
/**
 * JobNest - Lupa Password (Forgot Password)
 *
 * UI-only page stating that password reset is unavailable until a reset API exists.
 * Does not claim that an email was sent.
 */
require_once __DIR__ . '/../app/includes/layout.php';

render_header([
    'title' => 'Lupa Password',
    'active' => 'login',
    'description' => 'Informasi layanan pemulihan kata sandi akun JobNest.'
]);
?>

<div class="container auth-page-container">
    <div class="auth-card text-center">
        <div class="status-icon-wrapper" aria-hidden="true">
            <svg class="icon status-icon-badge" aria-hidden="true"><use href="/assets/icons.svg#lock"></use></svg>
        </div>

        <h1 class="auth-title">Pemulihan Password</h1>

        <div class="info-notice-box" role="note">
            <h2 class="notice-heading">Layanan Reset Password Belum Tersedia</h2>
            <p class="notice-message">
                Fitur reset password mandiri saat ini sedang dalam tahap pengembangan dan belum dapat digunakan hingga API reset password dan layanan pengiriman email aktif.
            </p>
            <p class="notice-detail">
                Jika Anda lupa password atau mengalami kendala saat masuk ke akun Anda, silakan hubungi tim administrator JobNest atau gunakan akun terdaftar lainnya.
            </p>
        </div>

        <div class="auth-actions-group">
            <a href="/login.php" class="btn btn-primary btn-block">Kembali ke Halaman Masuk</a>
            <a href="/index.php" class="btn btn-outline btn-block">Kembali ke Beranda</a>
        </div>
    </div>
</div>

<?php render_footer(); ?>
