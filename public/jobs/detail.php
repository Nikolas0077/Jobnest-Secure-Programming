<?php
/**
 * JobNest - Detail Lowongan Kerja (Job Detail)
 *
 * Uses integer ?id= to fetch GET /api/jobs/{id}.
 * Avoids assuming Apache rewrite rules.
 */
require_once __DIR__ . '/../../app/includes/job-view.php';

// Validate integer ID parameter
$rawId = $_GET['id'] ?? null;
$jobId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$result = $jobId ? api_request('GET', '/jobs/' . $jobId) : null;
$job = $result['data'] ?? null;

render_header([
    'title' => $jobId ? "Detail Lowongan #{$jobId}" : 'Lowongan Tidak Valid',
    'active' => 'jobs',
    'description' => 'Lihat rincian lengkap kualifikasi, deskripsi pekerjaan, dan informasi perusahaan di JobNest.'
]);
?>

<div class="container job-detail-page-container">
    <nav class="breadcrumb-nav" aria-label="Breadcrumb">
        <ol class="breadcrumb-list">
            <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
            <li class="breadcrumb-item"><a href="/jobs/index.php">Lowongan Kerja</a></li>
            <li class="breadcrumb-item active" aria-current="page">Detail Lowongan</li>
        </ol>
    </nav>

    <?php if ($jobId === false || $jobId === null): ?>
        <div class="state-card state-unavailable" role="alert">
            <div class="state-icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#alert"></use></svg></div>
            <h2 class="state-title">ID Lowongan Tidak Valid</h2>
            <p class="state-text">Parameter ID lowongan yang diminta harus berupa angka bulat positif.</p>
            <div class="state-action">
                <a href="/jobs/index.php" class="btn btn-primary">&larr; Kembali ke Daftar Lowongan</a>
            </div>
        </div>
    <?php else: ?>
        <div class="job-detail-container" aria-live="polite">
            <?php if (!$result['ok']): ?>
                <?php render_job_state($result['status'] === 404 ? 'unavailable' : 'error', 'Lowongan Tidak Tersedia', $result['status'] === 404 ? 'Lowongan ini tidak ditemukan atau sudah tidak aktif.' : $result['error'], '/jobs/index.php'); ?>
            <?php elseif (!is_array($job) || !is_int($job['id'] ?? null) || !is_string($job['title'] ?? null) || !is_string($job['company']['name'] ?? null)): ?>
                <?php render_job_state('error', 'Format Data Tidak Valid', 'Data detail lowongan dari backend tidak sesuai.'); ?>
            <?php else: ?>
                <article class="job-detail-card">
                    <header class="job-detail-header">
                        <h1 class="job-detail-title"><?= e($job['title']) ?></h1>
                        <div class="job-detail-company"><?= e($job['company']['name']) ?></div>
                        <div class="detail-badge-group">
                            <?php if (job_text($job['category'] ?? null) !== ''): ?><span class="detail-badge"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#tag"></use></svg><span><?= e(job_text($job['category'])) ?></span></span><?php endif; ?>
                            <?php if (job_text($job['location'] ?? null) !== ''): ?><span class="detail-badge"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#map-pin"></use></svg><span><?= e(job_text($job['location'])) ?></span></span><?php endif; ?>
                            <span class="detail-badge detail-badge-salary"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#banknote"></use></svg><span><?= e(salary_label($job['salary_min'] ?? null, $job['salary_max'] ?? null)) ?></span></span>
                            <span class="detail-badge"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#calendar"></use></svg><span><?= e(job_text($job['deadline'] ?? null) !== '' ? 'Batas: ' . job_text($job['deadline']) : 'Batas Waktu: Terbuka') ?></span></span>
                        </div>
                    </header>
                    <div class="job-detail-body">
                        <h2 class="detail-section-heading">Deskripsi Pekerjaan</h2>
                        <div class="job-description-content"><?= e(job_text($job['description'] ?? null) ?: 'Tidak ada deskripsi pekerjaan yang disertakan.') ?></div>
                    </div>
                    <div class="job-apply-box">
                        <div class="apply-box-text"><h2>Tertarik dengan posisi ini?</h2><p>Masuk dengan akun pelamar untuk mengajukan lamaran lengkap dengan CV Anda.</p></div>
                        <div class="apply-box-action"><a href="/login.php" class="btn btn-primary">Masuk untuk Melamar</a></div>
                    </div>
                </article>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php render_footer(); ?>
