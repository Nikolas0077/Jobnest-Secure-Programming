<?php
/**
 * HTML form target: POST /auth-action.php?intent=login|register|logout.
 * Check the UI form token and input, call the backend through api.php, then
 * redirect with a one-time flash message. Never store passwords in the UI session.
 */
require_once __DIR__ . '/../app/includes/layout.php';

$intent = $_GET['intent'] ?? '';
$destinations = ['login' => '/login.php', 'register' => '/register.php', 'logout' => '/index.php'];
if (!is_string($intent) || !isset($destinations[$intent])) {
    http_response_code(404);
    exit('Aksi tidak ditemukan.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}
$destination = $destinations[$intent];
if (!is_string($_POST['form_token'] ?? null) || !hash_equals(public_form_token(), $_POST['form_token'])) {
    http_response_code(403);
    exit('Formulir kedaluwarsa. Muat ulang halaman dan coba lagi.');
}

if ($intent === 'logout') {
    $result = api_post_with_csrf('/auth/logout');
    $message = $result['ok'] ? 'Anda telah keluar.' : 'Gagal keluar. Silakan coba lagi.';
    $_SESSION['public_flash'] = ['type' => $result['ok'] ? 'info' : 'danger', 'message' => $message];
    header('Location: /index.php', true, 303);
    exit;
}

$email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$error = null;
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    $error = 'Isi email dan password yang valid.';
}

if ($intent === 'register') {
    $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
    $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
    $role = $_POST['role'] ?? null;
    if ($name === '' || mb_strlen($name) > 255 || !in_array($role, ['applicant', 'company'], true)
        || strlen($password) < 8 || $password !== $confirmation) {
        $error = 'Periksa nama, jenis akun, dan konfirmasi password Anda.';
    }
}
if ($error !== null) {
    $_SESSION['public_flash'] = ['type' => 'danger', 'message' => $error];
    header('Location: ' . $destination, true, 303);
    exit;
}

$body = $intent === 'login'
    ? ['email' => $email, 'password' => $password]
    : ['name' => $name, 'email' => $email, 'password' => $password, 'role' => $role];
$result = api_post_with_csrf('/auth/' . $intent, $body);
if (!$result['ok']) {
    $message = $intent === 'login' && in_array($result['status'], [401, 403], true)
        ? 'Email atau password salah.'
        : ($result['status'] === 429 ? 'Terlalu banyak percobaan. Coba lagi nanti.' : $result['error']);
    $_SESSION['public_flash'] = ['type' => 'danger', 'message' => $message];
    header('Location: ' . $destination, true, 303);
    exit;
}
$_SESSION['public_flash'] = [
    'type' => 'info',
    'message' => $intent === 'login' ? 'Berhasil masuk.' : 'Pendaftaran berhasil. Silakan masuk.',
];
header('Location: ' . ($intent === 'login' ? '/index.php' : '/login.php'), true, 303);
