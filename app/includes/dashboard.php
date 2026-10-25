<?php
/**
 * JobNest - Shared dashboard helpers (Slot B).
 *
 * Use in every page under public/applicant, public/company, public/admin:
 *   require_once __DIR__ . '/../../app/includes/dashboard.php';
 *   $user = ui_require_role('company');
 *   render_dashboard_start($user, 'jobs', 'Lowongan Saya');
 *   ...
 *   render_dashboard_end();
 *
 * The role check here only decides what to show. The API checks role and
 * ownership again on every request, so a hidden menu is never the only protection.
 */
require_once __DIR__ . '/job-view.php';

const UI_ROLES = ['applicant', 'company', 'admin'];

/* ---------- Session, redirects, and form tokens ---------- */

/** Redirect to a local path only (blocks open redirects like //evil.com or https://...). */
function ui_redirect(string $path): never {
    if (!preg_match('~^/(?!/)[A-Za-z0-9/_\-.?=&%#]*$~', $path) || str_contains($path, '\\')) {
        $path = '/index.php';
    }
    header('Location: ' . $path, true, 303);
    exit;
}

function ui_flash(string $type, string $message): void {
    start_public_session();
    $_SESSION['public_flash'] = ['type' => in_array($type, ['info', 'danger', 'success'], true) ? $type : 'info', 'message' => $message];
}

/** Keep submitted values for one redirect so a form with an error is not emptied. */
function ui_keep_old(array $values): void {
    start_public_session();
    $_SESSION['ui_old'] = $values;
}

function ui_take_old(): array {
    start_public_session();
    $old = $_SESSION['ui_old'] ?? [];
    unset($_SESSION['ui_old']);
    return is_array($old) ? $old : [];
}

/** Old value as string, for value="" attributes (escape with e() when printing). */
function old_value(array $old, string $key, mixed $fallback = ''): string {
    $value = $old[$key] ?? $fallback;
    return is_scalar($value) ? (string) $value : '';
}

/** Stop unless this is a POST with the session form token (CSRF check on the UI side). */
function ui_verify_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        render_error_page(405, 'Metode tidak diizinkan.');
    }
    if (!is_string($_POST['form_token'] ?? null) || !hash_equals(public_form_token(), $_POST['form_token'])) {
        render_error_page(403, 'Formulir kedaluwarsa. Muat ulang halaman dan coba lagi.');
    }
}

function form_token_field(): string {
    return '<input type="hidden" name="form_token" value="' . e(public_form_token()) . '">';
}

/**
 * Require a logged-in user with one of the roles. Guests go to the login page;
 * other roles get a 403 page (force browsing /admin as an applicant is blocked here
 * and again by the API).
 */
function ui_require_role(string ...$roles): array {
    $user = get_current_user_data();
    if (!is_array($user) || !in_array($user['role'] ?? null, UI_ROLES, true)) {
        ui_flash('danger', 'Silakan masuk terlebih dahulu.');
        ui_redirect('/login.php');
    }
    if (!in_array($user['role'], $roles, true)) {
        render_error_page(403, 'Halaman ini tidak tersedia untuk peran akun Anda.');
    }
    return $user;
}

/* ---------- Input helpers for action handlers ---------- */

function post_text(string $key, int $maxLength): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim(str_replace("\r\n", "\n", $value));
    return mb_substr($value, 0, $maxLength);
}

function post_id(string $key): ?int {
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return is_int($value) ? $value : null;
}

function get_id(string $key): ?int {
    $value = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return is_int($value) ? $value : null;
}

/** Optional whole number from a form ("" becomes null). Returns false when invalid. */
function post_optional_int(string $key): int|null|false {
    $value = $_POST[$key] ?? null;
    if ($value === null) {
        return null;
    }
    if (!is_string($value)) {
        return false;
    }
    $value = str_replace(['.', ','], '', trim($value));
    if ($value === '') {
        return null;
    }
    return ctype_digit($value) && strlen($value) <= 10 ? (int) $value : false;
}

/* ---------- API helpers that the base api.php does not cover ---------- */

/** PATCH/DELETE/POST JSON with a fresh backend CSRF token. */
function api_send(string $method, string $path, ?array $body = null): array {
    $token = api_request('GET', '/auth/csrf');
    if (!$token['ok'] || !is_string($token['data']['csrf_token'] ?? null)) {
        return ['ok' => false, 'status' => $token['status'], 'error' => 'Token keamanan tidak tersedia.'];
    }
    return api_request($method, $path, $body ?? ($method === 'DELETE' ? null : []), $token['data']['csrf_token']);
}

/** cURL handle for the backend with the user's cookies and real IP. */
function api_curl(string $path, array $headers = []): CurlHandle|false {
    $base = rtrim((string) getenv('JOBNEST_API_BASE_URL'), '/');
    if ($base === '') {
        return false;
    }
    $curl = curl_init($base . $path);
    $headers[] = 'Accept: application/json, application/pdf';
    if (filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP)) {
        $headers[] = 'X-Forwarded-For: ' . $_SERVER['REMOTE_ADDR'];
    }
    if (($_SERVER['HTTP_COOKIE'] ?? '') !== '') {
        curl_setopt($curl, CURLOPT_COOKIE, $_SERVER['HTTP_COOKIE']);
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    return $curl;
}

/** Turn a raw backend reply into the same shape api_request() returns. */
function api_parse(string|false $raw, int $status): array {
    if ($raw === false) {
        return ['ok' => false, 'status' => 0, 'error' => 'Backend tidak dapat dihubungi.'];
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'status' => $status, 'error' => 'Respons backend tidak valid.'];
    }
    return $status >= 200 && $status < 300 && ($json['success'] ?? false) === true
        ? ['ok' => true, 'status' => $status, 'data' => $json['data'] ?? null]
        : ['ok' => false, 'status' => $status, 'error' => is_string($json['error']['message'] ?? null) ? $json['error']['message'] : 'Permintaan gagal.'];
}

/**
 * Forward an uploaded file as multipart/form-data. The original file name from
 * the browser is not forwarded; the API stores the file under a random name.
 */
function api_upload(string $path, string $field, string $tmpPath, string $mime, string $sendName): array {
    $token = api_request('GET', '/auth/csrf');
    if (!$token['ok'] || !is_string($token['data']['csrf_token'] ?? null)) {
        return ['ok' => false, 'status' => $token['status'], 'error' => 'Token keamanan tidak tersedia.'];
    }
    $curl = api_curl($path, ['X-CSRF-Token: ' . $token['data']['csrf_token']]);
    if ($curl === false) {
        return ['ok' => false, 'status' => 0, 'error' => 'Backend belum dikonfigurasi.'];
    }
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, [$field => new CURLFile($tmpPath, $mime, $sendName)]);
    $raw = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return api_parse($raw, $status);
}

/** GET a file (e.g. CV PDF). Returns ['ok', 'status', 'body', 'type'] or an api_parse() error. */
function api_download(string $path): array {
    $curl = api_curl($path);
    if ($curl === false) {
        return ['ok' => false, 'status' => 0, 'error' => 'Backend belum dikonfigurasi.'];
    }
    $raw = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $type = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);
    if ($raw !== false && $status === 200 && str_starts_with($type, 'application/pdf')) {
        return ['ok' => true, 'status' => 200, 'body' => $raw, 'type' => 'application/pdf'];
    }
    return api_parse($raw, $status);
}

/** Pick a user-facing message for common API error codes (401/403/409/422/429). */
function api_error_message(array $result, string $fallback = 'Permintaan gagal.'): string {
    return match ($result['status']) {
        0 => 'Backend tidak dapat dihubungi. Coba lagi nanti.',
        401 => 'Sesi Anda berakhir. Silakan masuk kembali.',
        403 => $result['error'] ?? 'Anda tidak memiliki akses untuk aksi ini.',
        404 => 'Data tidak ditemukan atau bukan milik Anda.',
        413 => 'File terlalu besar.',
        429 => 'Terlalu banyak percobaan. Coba lagi nanti.',
        default => is_string($result['error'] ?? null) ? $result['error'] : $fallback,
    };
}

/** After a failed API call in an action handler: 401 goes to login, others flash and go back. */
function ui_fail(array $result, string $back): never {
    if ($result['status'] === 401) {
        ui_flash('danger', 'Sesi Anda berakhir. Silakan masuk kembali.');
        ui_redirect('/login.php');
    }
    ui_flash('danger', api_error_message($result));
    ui_redirect($back);
}

/* ---------- Rendering ---------- */

/** Full error page (403/404/405/500). Never shows internal details. */
function render_error_page(int $code, string $message): never {
    http_response_code($code);
    $titles = [403 => 'Akses Ditolak', 404 => 'Halaman Tidak Ditemukan', 405 => 'Metode Tidak Diizinkan', 500 => 'Terjadi Kesalahan'];
    render_header(['title' => $titles[$code] ?? 'Kesalahan']);
    ?>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <div class="container error-page">
        <div class="state-card state-error" role="alert">
            <div class="state-icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#lock"></use></svg></div>
            <h1 class="state-title"><?= e($code . ' - ' . ($titles[$code] ?? 'Kesalahan')) ?></h1>
            <p class="state-text"><?= e($message) ?></p>
            <div class="state-action"><a href="/index.php" class="btn btn-primary btn-sm">Kembali ke Beranda</a></div>
        </div>
    </div>
    <?php
    render_footer();
    exit;
}

function dashboard_menu(string $role): array {
    return match ($role) {
        'applicant' => [
            'dashboard' => ['/applicant/dashboard.php', 'Ringkasan'],
            'jobs' => ['/applicant/jobs.php', 'Cari & Lamar'],
            'profile' => ['/applicant/profile.php', 'Profil & CV'],
            'applications' => ['/applicant/applications.php', 'Riwayat Lamaran'],
            'saved' => ['/applicant/saved-jobs.php', 'Lowongan Disimpan'],
            'password' => ['/account/password.php', 'Ganti Password'],
        ],
        'company' => [
            'dashboard' => ['/company/dashboard.php', 'Ringkasan'],
            'profile' => ['/company/profile.php', 'Profil Perusahaan'],
            'jobs' => ['/company/jobs.php', 'Lowongan Saya'],
            'job-new' => ['/company/job-form.php', 'Pasang Lowongan'],
            'password' => ['/account/password.php', 'Ganti Password'],
        ],
        'admin' => [
            'dashboard' => ['/admin/dashboard.php', 'Ringkasan'],
            'companies' => ['/admin/companies.php', 'Verifikasi Perusahaan'],
            'users' => ['/admin/users.php', 'Kelola Pengguna'],
            'jobs' => ['/admin/jobs.php', 'Moderasi Lowongan'],
            'logs' => ['/admin/logs.php', 'Log Aktivitas'],
            'password' => ['/account/password.php', 'Ganti Password'],
        ],
        default => [],
    };
}

function role_label(string $role): string {
    return ['applicant' => 'Pelamar', 'company' => 'Perusahaan', 'admin' => 'Admin'][$role] ?? 'Pengguna';
}

/** Open the page shell, role-aware sidebar, title, and flash message. */
function render_dashboard_start(array $user, string $active, string $title, string $subtitle = ''): void {
    $flash = take_public_flash();
    render_header(['title' => $title, 'description' => 'Dashboard JobNest']);
    ?>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <div class="container dashboard-layout">
        <aside class="dashboard-sidebar" aria-label="Menu dashboard">
            <div class="dashboard-user">
                <span class="user-role-badge"><?= e(role_label((string) $user['role'])) ?></span>
                <strong class="dashboard-user-name"><?= e(job_text($user['name'] ?? '')) ?></strong>
            </div>
            <nav>
                <ul class="dashboard-menu">
                    <?php foreach (dashboard_menu((string) $user['role']) as $key => [$url, $label]): ?>
                        <li><a href="<?= e($url) ?>" class="dashboard-menu-link <?= $key === $active ? 'active' : '' ?>" <?= $key === $active ? 'aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>
        <section class="dashboard-main">
            <header class="dashboard-header">
                <h1 class="page-title"><?= e($title) ?></h1>
                <?php if ($subtitle !== ''): ?><p class="page-subtitle"><?= e($subtitle) ?></p><?php endif; ?>
            </header>
            <?php render_flash($flash); ?>
    <?php
}

function render_dashboard_end(): void {
    ?>
        </section>
    </div>
    <script src="/assets/js/dashboard.js"></script>
    <?php
    render_footer();
}

function render_flash(?array $flash): void {
    if (!$flash || !is_string($flash['message'] ?? null)) {
        return;
    }
    $type = in_array($flash['type'] ?? '', ['info', 'danger', 'success'], true) ? $flash['type'] : 'info';
    echo '<div class="feedback-alert alert-' . e($type) . '" role="' . ($type === 'danger' ? 'alert' : 'status') . '">' . e($flash['message']) . '</div>';
}

/** Inline error box for a failed API GET. */
function render_api_error(array $result): void {
    render_job_state('error', 'Data gagal dimuat', api_error_message($result, 'Data gagal dimuat.'));
}

function status_label(string $status): string {
    return [
        'submitted' => 'Terkirim', 'reviewed' => 'Ditinjau', 'accepted' => 'Diterima', 'rejected' => 'Ditolak',
        'open' => 'Dibuka', 'closed' => 'Ditutup', 'pending' => 'Menunggu', 'approved' => 'Disetujui',
    ][$status] ?? 'Tidak diketahui';
}

/** Status badge; the class comes from a fixed list, never from API text. */
function status_badge(string $status): string {
    $tone = match ($status) {
        'accepted', 'approved', 'open' => 'success',
        'rejected', 'closed' => 'danger',
        'reviewed' => 'info',
        default => 'neutral',
    };
    return '<span class="status-badge status-' . $tone . '">' . e(status_label($status)) . '</span>';
}

function format_date(mixed $value): string {
    if (!is_string($value) || $value === '') {
        return '-';
    }
    $time = strtotime($value);
    return $time === false ? '-' : date('d M Y', $time);
}

/** Previous/next links that keep only whitelisted query values. */
function render_simple_pagination(mixed $pagination, string $baseUrl, array $query = []): void {
    if (!is_array($pagination) || !is_int($pagination['page'] ?? null) || !is_int($pagination['total_pages'] ?? null) || $pagination['total_pages'] <= 1) {
        return;
    }
    $page = $pagination['page'];
    $pages = $pagination['total_pages'];
    $link = static fn (int $p): string => $baseUrl . '?' . http_build_query($query + ['page' => $p]);
    ?>
    <nav class="pagination-nav" aria-label="Navigasi halaman">
        <?php if ($page > 1): ?><a class="btn btn-outline btn-sm" href="<?= e($link($page - 1)) ?>">&larr; Sebelumnya</a><?php endif; ?>
        <span class="status-badge-count">Halaman <?= e((string) $page) ?> dari <?= e((string) $pages) ?></span>
        <?php if ($page < $pages): ?><a class="btn btn-outline btn-sm" href="<?= e($link($page + 1)) ?>">Berikutnya &rarr;</a><?php endif; ?>
    </nav>
    <?php
}
