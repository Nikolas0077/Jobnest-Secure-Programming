<?php
/**
 * Demo dataset (Slot E). CLI only. Run once after schema.sql:
 *   php database/seed.php
 * The password for all demo accounts is read from env JOBNEST_SEED_PASSWORD, or typed
 * at a hidden prompt. It is never printed, logged, or stored in plain text.
 * Admin accounts are only created here, never from the public register form.
 * For local testing and demos only; do not run on a real deployment.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../api/src/config.php';
require_once __DIR__ . '/../api/src/db.php';

/** Ask for a password without showing what is typed. */
function prompt_hidden(string $label): string {
    fwrite(STDOUT, $label);
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = 'powershell -NoProfile -Command "$s = Read-Host -AsSecureString; '
            . '[Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))"';
        $value = (string) shell_exec($cmd);
    } else {
        shell_exec('stty -echo');
        $value = (string) fgets(STDIN);
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }
    return rtrim($value, "\r\n");
}

// Run only once: if the demo data already exists, change nothing (passwords stay the same).
$existing = db_query("SELECT COUNT(*) FROM users WHERE email LIKE '%@jobnest.test' OR email LIKE '%@pelamar.test' OR email IN ('hr@majudigital.test', 'hr@sinarbaru.test')")->fetchColumn();
if ((int) $existing > 0) {
    echo "Data demo sudah ada. Tidak ada yang diubah, password akun demo tetap seperti sebelumnya.\n";
    exit(0);
}

$password = getenv('JOBNEST_SEED_PASSWORD');
if (!is_string($password) || $password === '') {
    $password = prompt_hidden('Password untuk semua akun demo (tidak ditampilkan): ');
    if (!hash_equals($password, prompt_hidden('Ulangi password: '))) {
        fwrite(STDERR, "Password tidak sama.\n");
        exit(1);
    }
}
if (strlen($password) < 12 || strlen($password) > 72 || !preg_match('/[a-z]/', $password)
    || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
    fwrite(STDERR, "Password minimal 12 karakter (maks. 72) dan berisi huruf besar, huruf kecil, dan angka.\n");
    exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo = db();
$pdo->beginTransaction();

$users = [
    ['Admin JobNest', 'admin@jobnest.test', 'admin'],
    ['PT Maju Digital', 'hr@majudigital.test', 'company'],
    ['CV Sinar Baru', 'hr@sinarbaru.test', 'company'],
    ['Budi Santoso', 'budi@pelamar.test', 'applicant'],
    ['Siti Rahma', 'siti@pelamar.test', 'applicant'],
];
$ids = [];
foreach ($users as [$name, $email, $role]) {
    db_query('INSERT INTO users (name, email, password_hash, role, email_verified_at) VALUES (?, ?, ?, ?, NOW())', [$name, $email, $hash, $role]);
    $ids[$email] = (int) $pdo->lastInsertId();
}

db_query("INSERT INTO companies (user_id, company_name, description, website, verification_status, verified_at, verified_by) VALUES (?, ?, ?, ?, 'approved', NOW(), ?)",
    [$ids['hr@majudigital.test'], 'PT Maju Digital', 'Perusahaan software house di Jakarta.', 'https://majudigital.example', $ids['admin@jobnest.test']]);
$approvedCompany = (int) $pdo->lastInsertId();
db_query("INSERT INTO companies (user_id, company_name, description, verification_status) VALUES (?, ?, ?, 'pending')",
    [$ids['hr@sinarbaru.test'], 'CV Sinar Baru', 'Distributor alat tulis di Bandung.']);

foreach (['budi@pelamar.test', 'siti@pelamar.test'] as $email) {
    db_query('INSERT INTO applicant_profiles (user_id, phone, education, skills) VALUES (?, ?, ?, ?)',
        [$ids[$email], '081234567890', 'S1 Teknik Informatika', 'PHP, MySQL, HTML, CSS']);
}

$jobs = [
    ['Backend Developer PHP', 'Membangun REST API dengan PHP dan MySQL. Paham OWASP Top 10.', 'Jakarta', 'IT', 8000000, 12000000],
    ['Frontend Developer', 'Membuat antarmuka responsif dengan HTML, CSS, dan JavaScript.', 'Jakarta', 'IT', 7000000, 10000000],
    ['Security Analyst', 'Melakukan code review dan pengujian keamanan aplikasi web.', 'Remote', 'Security', 10000000, 15000000],
];
foreach ($jobs as [$title, $description, $location, $category, $min, $max]) {
    db_query("INSERT INTO job_posts (company_id, title, description, location, category, salary_min, salary_max, deadline, status)
              VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'open')",
        [$approvedCompany, $title, $description, $location, $category, $min, $max]);
}

$pdo->commit();

echo "Seed selesai. Akun demo (semua memakai password yang tadi Anda masukkan):\n";
foreach ($users as [, $email, $role]) {
    printf("  %-10s %s\n", $role, $email);
}
