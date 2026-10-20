<?php
/**
 * JobNest - Shared Public Layout & Helpers
 *
 * Provides the shared page shell and accessible navbar,
 * and safe output escaping. Integrates with backend session and auth
 * helpers when supplied.
 */

// Conditionally include backend team's helpers if present
require_once __DIR__ . '/api.php';
$includesDir = __DIR__;
foreach (['csrf.php', 'auth.php', 'security.php'] as $helper) {
    $helperPath = $includesDir . '/' . $helper;
    if (file_exists($helperPath)) {
        require_once $helperPath;
    }
}

/** Escape text before placing user or API data in an HTML text or attribute node. */
if (!function_exists('e')) {
    function e(?string $value): string {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/** Start the UI session for form tokens and flash messages (separate from API auth). */
function start_public_session(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    session_name('JOBNEST_UI');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    if (!session_start()) {
        throw new RuntimeException('Unable to start session');
    }
}

/** Return the session-bound token to place in every UI POST form. */
function public_form_token(): string {
    start_public_session();
    return $_SESSION['public_form_token'] ??= bin2hex(random_bytes(32));
}

/** Read and clear the message shown after an auth form redirects (POST/Redirect/GET). */
function take_public_flash(): ?array {
    start_public_session();
    $flash = $_SESSION['public_flash'] ?? null;
    unset($_SESSION['public_flash']);
    return is_array($flash) ? $flash : null;
}

/**
 * Resolve the signed-in user using a local auth helper or GET /auth/me.
 * Returns null for guests; role and ID must never be taken from form input.
 * Protected pages must also enforce role and ownership on the backend.
 */
if (!function_exists('get_current_user_data')) {
    function get_current_user_data(): ?array {
        if (function_exists('current_user')) {
            $user = current_user();
            if (is_array($user)) {
                return $user;
            }
        }
        if (function_exists('auth_user')) {
            $user = auth_user();
            if (is_array($user)) {
                return $user;
            }
        }
        start_public_session();
        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            return $_SESSION['user'];
        }
        if (getenv('JOBNEST_API_BASE_URL')) {
            $response = api_request('GET', '/auth/me');
            if ($response['ok'] && is_array($response['data']['user'] ?? null)) {
                return $response['data']['user'];
            }
        }
        return null;
    }
}

/**
 * Open the shared page shell and main element. Call render_footer() after HTML.
 * Params: title, description, active ('home', 'jobs', 'login', 'register').
 */
function render_header(array $params = []): void {
    $pageTitle = !empty($params['title']) ? $params['title'] . ' | JobNest' : 'JobNest - Portal Lowongan Kerja Terpercaya';
    $metaDescription = !empty($params['description']) ? $params['description'] : 'Temukan lowongan kerja terbaru dan terpercaya dari perusahaan terverifikasi di JobNest.';
    $activeNav = $params['active'] ?? '';
    $currentUser = get_current_user_data();
    $role = $currentUser['role'] ?? 'applicant';
    $dashboardUrl = $role === 'admin' ? '/admin/dashboard.php' : ($role === 'company' ? '/company/dashboard.php' : '/applicant/dashboard.php');
    $displayName = $currentUser['name'] ?? $currentUser['email'] ?? 'Akun';
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="description" content="<?= e($metaDescription) ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/public.css">
    <script defer src="/assets/js/navigation.js"></script>
</head>
<body>
    <a href="#main-content" class="skip-link">Lewati ke konten utama</a>

    <header class="site-header" role="banner">
        <div class="container header-container">
            <a href="/index.php" class="brand" aria-label="Halaman Beranda JobNest">
                <svg class="icon brand-logo" aria-hidden="true"><use href="/assets/icons.svg#briefcase"></use></svg>
                <span class="brand-name">JobNest</span>
            </a>

            <button type="button" class="nav-toggle" id="nav-toggle" aria-controls="primary-nav" aria-expanded="false" aria-label="Buka menu navigasi">
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
            </button>

            <nav class="primary-nav" id="primary-nav" aria-label="Navigasi Utama">
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="/index.php" class="nav-link <?= $activeNav === 'home' ? 'active' : '' ?>" <?= $activeNav === 'home' ? 'aria-current="page"' : '' ?>>Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a href="/jobs/index.php" class="nav-link <?= $activeNav === 'jobs' ? 'active' : '' ?>" <?= $activeNav === 'jobs' ? 'aria-current="page"' : '' ?>>Lowongan Kerja</a>
                    </li>
                </ul>

                <div class="auth-nav">
                    <div id="user-nav" class="user-nav" <?= $currentUser ? '' : 'hidden' ?>>
                        <span class="user-greeting">
                            <span id="nav-user-role" class="user-role-badge"><?= e(ucfirst($role)) ?></span>
                            <span id="nav-user-name" class="user-name"><?= e($displayName) ?></span>
                        </span>
                        <a id="nav-dashboard" href="<?= e($dashboardUrl) ?>" class="btn btn-outline btn-sm">Dashboard</a>
                        <form method="POST" action="/auth-action.php?intent=logout" class="logout-form">
                            <input type="hidden" name="form_token" value="<?= e(public_form_token()) ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Keluar</button>
                        </form>
                    </div>
                    <div id="guest-nav" class="guest-nav" <?= $currentUser ? 'hidden' : '' ?>>
                        <a href="/login.php" class="btn btn-outline btn-sm <?= $activeNav === 'login' ? 'active' : '' ?>">Masuk</a>
                        <a href="/register.php" class="btn btn-primary btn-sm <?= $activeNav === 'register' ? 'active' : '' ?>">Daftar</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <main id="main-content" class="main-content" tabindex="-1">
    <?php
}

/**
 * Close main and the page shell after render_header().
 */
function render_footer(): void {
    ?>
    </main>

    <footer class="site-footer" role="contentinfo">
        <div class="container footer-container">
            <div class="footer-brand">
                <div class="footer-logo"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#briefcase"></use></svg><strong>JobNest</strong></div>
                <p class="footer-tagline">Platform pencarian kerja aman dan terverifikasi untuk pelamar dan perusahaan.</p>
            </div>
            <div class="footer-links">
                <div class="footer-col">
                    <h3 class="footer-heading">Navigasi</h3>
                    <ul class="footer-list">
                        <li><a href="/index.php">Beranda</a></li>
                        <li><a href="/jobs/index.php">Daftar Lowongan</a></li>
                        <li><a href="/login.php">Masuk Akun</a></li>
                        <li><a href="/register.php">Registrasi Akun</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h3 class="footer-heading">Keamanan & Privasi</h3>
                    <ul class="footer-list">
                        <li><span>Verifikasi Perusahaan</span></li>
                        <li><span>Perlindungan Data CV</span></li>
                        <li><span>Anti-Spam & Rate Limiting</span></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container footer-bottom-container">
                <p>&copy; <?= date('Y') ?> JobNest. All rights reserved. Secure Programming COMP6695001.</p>
            </div>
        </div>
    </footer>
</body>
</html>
    <?php
}

/**
 * Render a full page when $content is trusted HTML created by server templates.
 * Escape untrusted values with e() before constructing $content.
 */
function render_layout(string $title, string $content, array $params = []): void {
    $params['title'] = $title;
    render_header($params);
    echo $content;
    render_footer();
}
