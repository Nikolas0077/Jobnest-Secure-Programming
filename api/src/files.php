<?php
/**
 * Private CV storage (Slot D). Files live in storage/private/cv (outside public/),
 * get a random name, and are only served through authorized endpoints.
 * The database stores the random file name only; a path is never taken from a request.
 */
declare(strict_types=1);

const CV_MAX_BYTES = 2 * 1024 * 1024;
const CV_NAME_PATTERN = '/^[a-f0-9]{32}\.pdf$/';

function ensure_storage_dir(): string {
    $dir = storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create storage directory');
    }
    return $dir;
}

/** Absolute path for a stored file name, or null if the name is not one we generate. */
function cv_file_path(?string $name): ?string {
    if (!is_string($name) || !preg_match(CV_NAME_PATTERN, $name)) {
        return null;
    }
    $path = storage_dir() . DIRECTORY_SEPARATOR . $name;
    return is_file($path) ? $path : null;
}

/**
 * Validate the uploaded $_FILES['cv'] entry. Checks: upload error, real upload,
 * size <= 2 MB, .pdf extension, MIME from file content (finfo), %PDF- header,
 * %%EOF trailer, and no embedded PHP code or PDF JavaScript/launch actions.
 * Returns the temp path, or sends a JSON error.
 */
function validate_cv_upload(mixed $file): string {
    if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size'], $file['name']) || is_array($file['error'])) {
        validation_error(['cv' => 'Pilih satu file CV.']);
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        json_error(413, 'PAYLOAD_TOO_LARGE', 'Ukuran CV maksimal 2 MB.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        validation_error(['cv' => 'Upload CV gagal.']);
    }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size === 0) {
        validation_error(['cv' => 'File CV kosong.']);
    }
    if ($size > CV_MAX_BYTES) {
        json_error(413, 'PAYLOAD_TOO_LARGE', 'Ukuran CV maksimal 2 MB.');
    }
    if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        validation_error(['cv' => 'CV harus berformat PDF.']);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $content = file_get_contents($file['tmp_name']);
    if ($mime !== 'application/pdf' || $content === false || !str_starts_with($content, '%PDF-')
        || !str_contains(substr($content, -1024), '%%EOF')) {
        validation_error(['cv' => 'CV harus berformat PDF yang valid.']);
    }
    if (preg_match('/<\?php|<\?=|\/JavaScript|\/JS\s*[\(<]|\/Launch|\/EmbeddedFile/i', $content)) {
        validation_error(['cv' => 'PDF berisi konten aktif yang tidak diizinkan.']);
    }
    return $file['tmp_name'];
}

/** Move a validated upload into private storage under a random name. */
function store_uploaded_cv(string $tmpPath): string {
    $name = bin2hex(random_bytes(16)) . '.pdf';
    $target = ensure_storage_dir() . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmpPath, $target)) {
        throw new RuntimeException('Cannot store CV');
    }
    chmod($target, 0600);
    return $name;
}

/** Copy a stored CV under a new random name (used as the snapshot for an application). */
function copy_stored_cv(string $name): string {
    $source = cv_file_path($name);
    if ($source === null) {
        throw new RuntimeException('Stored CV missing');
    }
    $copy = bin2hex(random_bytes(16)) . '.pdf';
    $target = ensure_storage_dir() . DIRECTORY_SEPARATOR . $copy;
    if (!copy($source, $target)) {
        throw new RuntimeException('Cannot copy CV');
    }
    chmod($target, 0600);
    return $copy;
}

function delete_stored_cv(?string $name): void {
    $path = cv_file_path($name);
    if ($path !== null) {
        unlink($path);
    }
}

/** Send a stored PDF as a download with a safe, server-chosen file name. */
function send_pdf_download(string $path, string $downloadName): never {
    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $downloadName) ?: 'cv';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . substr($safeName, 0, 80) . '.pdf"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}
