<?php
/**
 * JobNest API front controller. Only this folder is web-accessible; code, config,
 * and storage live outside it.
 *
 * Dev server: php -S 127.0.0.1:8001 -t api/public api/public/index.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/routes/auth.php';
require_once __DIR__ . '/../src/routes/jobs.php';
require_once __DIR__ . '/../src/routes/applicant.php';
require_once __DIR__ . '/../src/routes/company.php';
require_once __DIR__ . '/../src/routes/admin.php';

send_security_headers();
dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
