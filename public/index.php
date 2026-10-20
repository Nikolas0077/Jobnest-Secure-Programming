<?php
/**
 * JobNest - Beranda (Homepage)
 *
 * Search entry point and job preview loaded from GET /api/jobs.
 */
require_once __DIR__ . '/../app/includes/job-view.php';
$flash = take_public_flash();
$preview = api_request('GET', '/jobs?' . http_build_query(['sort' => 'newest', 'page' => 1, 'per_page' => 6]));
$previewJobs = $preview['data']['items'] ?? null;

render_header([
    'title' => 'Beranda',
    'active' => 'home',
    'description' => 'Cari dan temukan lowongan kerja impian Anda dari berbagai perusahaan terverifikasi di JobNest.'
]);
?>

<section class="hero-section">
    <div class="container hero-container">
        <?php if ($flash): ?><div class="feedback-alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
        <div class="hero-content">
            <h1 class="hero-title">Temukan Karir Impian Anda Bersama <span class="text-primary">JobNest</span></h1>
            <p class="hero-subtitle">
                Eksplorasi ribuan lowongan kerja terpercaya dari perusahaan terverifikasi. Proses lamaran aman, transparan, dan tanpa perantara.
            </p>
        </div>

        <div class="search-box-card">
            <form action="/jobs/index.php" method="GET" class="hero-search-form" id="hero-search-form">
                <div class="form-group search-field">
                    <label for="hero-q" class="form-label">Posisi / Kata Kunci</label>
                    <input
                        type="text"
                        name="q"
                        id="hero-q"
                        class="form-control"
                        placeholder="Contoh: Backend Developer"
                        maxlength="100"
                        autocomplete="off"
                    >
                </div>

                <div class="form-group search-field">
                    <label for="hero-location" class="form-label">Lokasi</label>
                    <input
                        type="text"
                        name="location"
                        id="hero-location"
                        class="form-control"
                        placeholder="Contoh: Jakarta / Remote"
                        autocomplete="off"
                    >
                </div>

                <div class="form-group search-field">
                    <label for="hero-category" class="form-label">Kategori</label>
                    <input
                        type="text"
                        name="category"
                        id="hero-category"
                        class="form-control"
                        placeholder="Contoh: Teknologi, Desain"
                        autocomplete="off"
                    >
                </div>

                <div class="form-group search-submit-col">
                    <button type="submit" class="btn btn-primary btn-block search-btn">
                        <svg class="icon btn-icon" aria-hidden="true"><use href="/assets/icons.svg#search"></use></svg> Cari Lowongan
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<section class="preview-section">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title">Lowongan Terbaru</h2>
                <p class="section-subtitle">Peluang karir terhangat yang dibuka oleh mitra perusahaan kami.</p>
            </div>
            <a href="/jobs/index.php" class="btn btn-outline">Lihat Semua Lowongan &rarr;</a>
        </div>

        <div class="jobs-grid" aria-live="polite">
            <?php if (!$preview['ok']): ?>
                <?php render_job_state('error', 'Gagal Memuat Lowongan', $preview['error'], '/index.php'); ?>
            <?php elseif (!is_array($previewJobs) || !array_every_job($previewJobs)): ?>
                <?php render_job_state('error', 'Format Data Tidak Valid', 'Data lowongan dari backend tidak sesuai.'); ?>
            <?php elseif ($previewJobs === []): ?>
                <?php render_job_state('empty', 'Belum Ada Lowongan', 'Saat ini belum ada lowongan kerja yang tersedia.'); ?>
            <?php else: foreach (array_slice($previewJobs, 0, 6) as $job) render_job_card($job); endif; ?>
        </div>

        <div class="preview-footer text-center">
            <a href="/jobs/index.php" class="btn btn-primary btn-lg">Jelajahi Semua Lowongan Kerja</a>
        </div>
    </div>
</section>

<section class="features-section">
    <div class="container">
        <div class="section-header text-center">
            <h2 class="section-title">Mengapa Memilih JobNest?</h2>
            <p class="section-subtitle">Komitmen kami terhadap keamanan data dan keaslian lowongan kerja.</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#shield"></use></svg></div>
                <h3 class="feature-heading">Perusahaan Terverifikasi</h3>
                <p class="feature-text">Setiap akun perusahaan melewati moderasi admin sebelum dapat mempublikasikan lowongan kerja.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#lock"></use></svg></div>
                <h3 class="feature-heading">Privasi CV Terlindungi</h3>
                <p class="feature-text">Dokumen lamaran dan CV Anda disimpan di lokasi penyimpanan private, hanya dapat diakses oleh perusahaan yang dilamar.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#bolt"></use></svg></div>
                <h3 class="feature-heading">Aplikasi Cepat & Transparan</h3>
                <p class="feature-text">Pantau tahapan seleksi secara berkala langsung dari dashboard akun Anda.</p>
            </div>
        </div>
    </div>
</section>

<?php render_footer(); ?>
