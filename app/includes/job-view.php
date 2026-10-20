<?php
require_once __DIR__ . '/layout.php';

/** Convert an optional scalar API field to display text; reject arrays/objects. */
function job_text(mixed $value): string {
    return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
}

/** Check the fields required to render one GET /jobs item safely. */
function valid_job_summary(mixed $job): bool {
    return is_array($job) && is_int($job['id'] ?? null) && $job['id'] > 0
        && is_string($job['title'] ?? null) && is_string($job['company_name'] ?? null);
}

/** Validate the whole GET /jobs item list before rendering any cards. */
function array_every_job(array $jobs): bool {
    foreach ($jobs as $job) {
        if (!valid_job_summary($job)) return false;
    }
    return true;
}

/** Format nullable salary bounds from the API as Indonesian rupiah. */
function salary_label(mixed $min, mixed $max): string {
    $min = is_numeric($min) ? (float) $min : null;
    $max = is_numeric($max) ? (float) $max : null;
    if ($min === null && $max === null) return 'Gaji dirahasiakan';
    if ($min !== null && $max !== null) return 'Rp ' . number_format($min, 0, ',', '.') . ' - Rp ' . number_format($max, 0, ',', '.');
    return $min !== null ? 'Mulai dari Rp ' . number_format($min, 0, ',', '.') : 'Hingga Rp ' . number_format($max, 0, ',', '.');
}

/** Print a validated job summary card; call valid_job_summary() first. */
function render_job_card(array $job): void {
    $url = '/jobs/detail.php?id=' . $job['id'];
    ?>
    <article class="job-card">
        <div class="job-card-header">
            <h3 class="job-card-title"><a href="<?= e($url) ?>"><?= e($job['title']) ?></a></h3>
            <?php if (job_text($job['category'] ?? null) !== ''): ?><span class="job-badge job-badge-category"><?= e(job_text($job['category'])) ?></span><?php endif; ?>
        </div>
        <div class="job-company"><?= e($job['company_name']) ?></div>
        <div class="job-meta-list">
            <?php if (job_text($job['location'] ?? null) !== ''): ?><span class="job-meta-item"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#map-pin"></use></svg><span><?= e(job_text($job['location'])) ?></span></span><?php endif; ?>
            <span class="job-meta-item job-salary-highlight"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#banknote"></use></svg><span><?= e(salary_label($job['salary_min'] ?? null, $job['salary_max'] ?? null)) ?></span></span>
        </div>
        <?php if (job_text($job['description'] ?? null) !== ''): ?><p class="job-description-preview"><?= e(job_text($job['description'])) ?></p><?php endif; ?>
        <div class="job-card-footer">
            <span class="job-deadline"><?= e(job_text($job['deadline'] ?? null) !== '' ? 'Batas: ' . job_text($job['deadline']) : 'Batas: Terbuka') ?></span>
            <a class="btn btn-outline btn-sm" href="<?= e($url) ?>">Lihat Detail</a>
        </div>
    </article>
    <?php
}

/** Print an empty/error/unavailable state; $action is an optional local retry URL. */
function render_job_state(string $type, string $title, string $message, ?string $action = null): void {
    $icon = $type === 'empty' ? 'search' : ($type === 'unavailable' ? 'alert' : 'circle-x');
    ?>
    <div class="state-card state-<?= e($type) ?>" role="<?= $type === 'empty' ? 'status' : 'alert' ?>">
        <div class="state-icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#<?= e($icon) ?>"></use></svg></div>
        <h2 class="state-title"><?= e($title) ?></h2>
        <p class="state-text"><?= e($message) ?></p>
        <?php if ($action): ?><div class="state-action"><a href="<?= e($action) ?>" class="btn btn-outline btn-sm">Coba Lagi</a></div><?php endif; ?>
    </div>
    <?php
}
