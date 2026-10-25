<?php
/**
 * JobNest - Ganti Password (Slot B, semua peran). PATCH /api/auth/password
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

$user = ui_require_role(...UI_ROLES);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ui_verify_post();
    $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirm = is_string($_POST['new_password_confirmation'] ?? null) ? $_POST['new_password_confirmation'] : '';
    if ($current === '' || strlen($new) < 8 || strlen($new) > 72 || $new !== $confirm) {
        ui_flash('danger', 'Isi password saat ini, dan password baru 8-72 karakter yang sama di kedua kolom.');
        ui_redirect('/account/password.php');
    }
    // Passwords are sent straight to the API and never stored in the UI session.
    $result = api_send('PATCH', '/auth/password', ['current_password' => $current, 'new_password' => $new]);
    if (!$result['ok']) {
        ui_fail($result, '/account/password.php');
    }
    ui_flash('success', 'Password berhasil diganti.');
    ui_redirect('/account/password.php');
}

render_dashboard_start($user, 'password', 'Ganti Password');
?>
<section class="dashboard-panel">
    <form method="POST" action="/account/password.php" class="dashboard-form">
        <?= form_token_field() ?>
        <div class="form-group">
            <label for="current_password" class="form-label">Password Saat Ini <span class="required" aria-hidden="true">*</span></label>
            <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label for="new_password" class="form-label">Password Baru <span class="required" aria-hidden="true">*</span></label>
            <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" maxlength="72" autocomplete="new-password">
            <p class="form-help">8-72 karakter, berisi huruf dan angka.</p>
        </div>
        <div class="form-group">
            <label for="new_password_confirmation" class="form-label">Ulangi Password Baru <span class="required" aria-hidden="true">*</span></label>
            <input type="password" id="new_password_confirmation" name="new_password_confirmation" class="form-control" required minlength="8" maxlength="72" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary">Simpan Password</button>
    </form>
</section>
<?php render_dashboard_end(); ?>
