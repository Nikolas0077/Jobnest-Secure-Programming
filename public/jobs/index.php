<?php
/**
 * JobNest - Daftar Lowongan Kerja (Job List)
 *
 * Provides keyword, location, and category filters, job cards,
 * pagination, and server-rendered request states from GET /api/jobs.
 */
require_once __DIR__ . '/../../app/includes/job-view.php';

// Sanitize query params for initial form rendering (untrusted input)
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}
$location = is_string($_GET['location'] ?? null) ? trim($_GET['location']) : '';
$category = is_string($_GET['category'] ?? null) ? trim($_GET['category']) : '';
$salaryMin = is_string($_GET['salary_min'] ?? null) && ctype_digit($_GET['salary_min']) ? $_GET['salary_min'] : '';
$salaryMax = is_string($_GET['salary_max'] ?? null) && ctype_digit($_GET['salary_max']) ? $_GET['salary_max'] : '';
$sort = is_string($_GET['sort'] ?? null) && in_array($_GET['sort'], ['newest', 'salary_asc', 'salary_desc'], true) ? $_GET['sort'] : 'newest';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$filters = ['q' => $q, 'location' => $location, 'category' => $category, 'sort' => $sort, 'page' => $page, 'per_page' => 10];
if ($salaryMin !== '') $filters['salary_min'] = $salaryMin;
if ($salaryMax !== '') $filters['salary_max'] = $salaryMax;
$result = api_request('GET', '/jobs?' . http_build_query($filters));
$jobs = $result['data']['items'] ?? null;
$pagination = $result['data']['pagination'] ?? null;

render_header([
    'title' => 'Daftar Lowongan Kerja',
    'active' => 'jobs',
    'description' => 'Cari dan filter lowongan kerja berdasarkan posisi, lokasi, dan kategori di JobNest.'
]);
?>

<div class="page-header">
    <div class="container">
        <h1 class="page-title">Eksplorasi Lowongan Kerja</h1>
        <p class="page-subtitle">Temukan kesempatan karir terbaik yang sesuai dengan minat dan keahlian Anda.</p>
    </div>
</div>

<div class="container jobs-page-container">
    <!-- Filter Card -->
    <div class="jobs-filter-card">
        <form action="/jobs/index.php" method="GET" class="jobs-filter-form">
            <div class="filter-grid">
                <div class="form-group">
                    <label for="filter-q" class="form-label">Kata Kunci / Posisi</label>
                    <input
                        type="text"
                        name="q"
                        id="filter-q"
                        class="form-control"
                        placeholder="Contoh: Frontend, Data Analyst"
                        maxlength="100"
                        value="<?= e($q) ?>"
                        autocomplete="off"
                    >
                </div>

                <div class="form-group">
                    <label for="filter-location" class="form-label">Lokasi</label>
                    <input
                        type="text"
                        name="location"
                        id="filter-location"
                        class="form-control"
                        placeholder="Contoh: Jakarta, Surabaya, Remote"
                        value="<?= e($location) ?>"
                        autocomplete="off"
                    >
                </div>

                <div class="form-group">
                    <label for="filter-category" class="form-label">Kategori</label>
                    <input
                        type="text"
                        name="category"
                        id="filter-category"
                        class="form-control"
                        placeholder="Contoh: Teknologi, Pemasaran"
                        value="<?= e($category) ?>"
                        autocomplete="off"
                    >
                </div>

                <div class="form-group">
                    <label for="filter-salary-min" class="form-label">Gaji minimum (Rp)</label>
                    <input type="number" name="salary_min" id="filter-salary-min" class="form-control" min="0" step="1" value="<?= e($salaryMin) ?>">
                </div>

                <div class="form-group">
                    <label for="filter-salary-max" class="form-label">Gaji maksimum (Rp)</label>
                    <input type="number" name="salary_max" id="filter-salary-max" class="form-control" min="0" step="1" value="<?= e($salaryMax) ?>">
                </div>

                <div class="form-group">
                    <label for="filter-sort" class="form-label">Urutkan</label>
                    <select name="sort" id="filter-sort" class="form-control">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option>
                        <option value="salary_asc" <?= $sort === 'salary_asc' ? 'selected' : '' ?>>Gaji terendah</option>
                        <option value="salary_desc" <?= $sort === 'salary_desc' ? 'selected' : '' ?>>Gaji tertinggi</option>
                    </select>
                </div>

                <div class="form-group filter-actions">
                    <button type="submit" class="btn btn-primary" id="filter-submit-btn">
                        <svg class="icon btn-icon" aria-hidden="true"><use href="/assets/icons.svg#search"></use></svg> Terapkan Filter
                    </button>
                    <a href="/jobs/index.php" class="btn btn-outline" id="filter-reset-btn">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <?php if ($result['ok'] && is_array($jobs) && array_every_job($jobs)): ?>
        <div class="jobs-status"><p class="status-badge-count">Menampilkan <?= count($jobs) ?> lowongan</p></div>
    <?php endif; ?>
    <div id="jobs-loading" class="state-card state-loading" role="status" hidden><div class="spinner" aria-hidden="true"></div><p>Memuat daftar lowongan kerja...</p></div>
    <div id="jobs-list" class="jobs-list" aria-live="polite">
        <?php if (!$result['ok']): ?>
            <?php render_job_state('error', 'Gagal Memuat Lowongan', $result['error'], '/jobs/index.php?' . http_build_query($filters)); ?>
        <?php elseif (!is_array($jobs) || !array_every_job($jobs)): ?>
            <?php render_job_state('error', 'Format Data Tidak Valid', 'Data lowongan dari backend tidak sesuai.'); ?>
        <?php elseif ($jobs === []): ?>
            <?php render_job_state('empty', 'Tidak Ada Lowongan Ditemukan', 'Tidak ada lowongan yang cocok dengan filter Anda.', '/jobs/index.php'); ?>
        <?php else: foreach ($jobs as $job) render_job_card($job); endif; ?>
    </div>

    <?php $totalPages = is_array($pagination) ? max(1, (int) ($pagination['total_pages'] ?? 1)) : 1; ?>
    <?php if ($result['ok'] && $totalPages > 1): ?>
        <nav class="pagination-nav" aria-label="Navigasi Halaman Lowongan">
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <?php $pageFilters = $filters; $pageFilters['page'] = $p; ?>
                <a class="page-btn <?= $p === $page ? 'active' : '' ?>" href="/jobs/index.php?<?= e(http_build_query($pageFilters)) ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</div>

<?php render_footer(); ?>
