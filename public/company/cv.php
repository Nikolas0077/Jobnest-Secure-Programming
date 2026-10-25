<?php
/**
 * JobNest - Unduh CV pelamar (Slot B).
 * Passes only the integer application ID to GET /api/company/applications/{id}/cv.
 * The API checks that the application belongs to this company and picks the file
 * from the database, so no file path ever comes from the browser.
 */
require_once __DIR__ . '/../../app/includes/dashboard.php';

ui_require_role('company');
$applicationId = get_id('application_id');
if ($applicationId === null) {
    render_error_page(404, 'Lamaran tidak ditemukan.');
}
$file = api_download('/company/applications/' . $applicationId . '/cv');
if (!$file['ok']) {
    render_error_page(in_array($file['status'], [403, 404], true) ? $file['status'] : 500, api_error_message($file));
}
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="CV-lamaran-' . $applicationId . '.pdf"');
header('Content-Length: ' . strlen($file['body']));
header('Cache-Control: private, no-store');
header("Content-Security-Policy: sandbox; default-src 'none'");
echo $file['body'];
