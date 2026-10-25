<?php
/**
 * Public job endpoints (Slot D). Only open, non-deleted, not-expired jobs of an
 * approved company whose account is active are visible.
 */
declare(strict_types=1);

const PUBLIC_JOB_SQL = "j.status = 'open' AND j.deleted_at IS NULL
    AND (j.deadline IS NULL OR j.deadline >= CURDATE())
    AND c.verification_status = 'approved' AND cu.is_active = 1";

const PUBLIC_JOB_FROM = 'FROM job_posts j
    JOIN companies c ON c.id = j.company_id
    JOIN users cu ON cu.id = c.user_id';

/** Escape LIKE wildcards so user text is matched literally. */
function like_pattern(string $value): string {
    return '%' . addcslashes($value, '%_\\') . '%';
}

/** Same shape as GET /jobs items; also used by saved jobs. */
function job_summary(array $row): array {
    return [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'company_name' => $row['company_name'],
        'location' => $row['location'],
        'category' => $row['category'],
        'salary_min' => $row['salary_min'] !== null ? (int) $row['salary_min'] : null,
        'salary_max' => $row['salary_max'] !== null ? (int) $row['salary_max'] : null,
        'deadline' => $row['deadline'],
    ];
}

function find_public_job(int $jobId): ?array {
    return db_one(
        'SELECT j.*, c.id AS company_id, c.company_name, c.website ' . PUBLIC_JOB_FROM . ' WHERE j.id = ? AND ' . PUBLIC_JOB_SQL,
        [$jobId]
    );
}

route('GET', '/jobs', function (): void {
    $where = [PUBLIC_JOB_SQL];
    $params = [];
    $q = query_string('q', 100);
    if ($q !== '') {
        $where[] = '(j.title LIKE ? OR j.description LIKE ? OR c.company_name LIKE ?)';
        array_push($params, like_pattern($q), like_pattern($q), like_pattern($q));
    }
    $location = query_string('location', 100);
    if ($location !== '') {
        $where[] = 'j.location LIKE ?';
        $params[] = like_pattern($location);
    }
    $category = query_string('category', 100);
    if ($category !== '') {
        $where[] = 'j.category LIKE ?';
        $params[] = like_pattern($category);
    }
    $salaryMin = filter_var($_GET['salary_min'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2000000000]]);
    if (is_int($salaryMin)) {
        $where[] = 'COALESCE(j.salary_max, j.salary_min) >= ?';
        $params[] = $salaryMin;
    }
    $salaryMax = filter_var($_GET['salary_max'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2000000000]]);
    if (is_int($salaryMax)) {
        $where[] = 'COALESCE(j.salary_min, j.salary_max) <= ?';
        $params[] = $salaryMax;
    }

    // ORDER BY can't be a bound parameter, so it only ever comes from this whitelist.
    $sorts = [
        'newest' => 'j.created_at DESC, j.id DESC',
        'salary_asc' => 'j.salary_min IS NULL, j.salary_min ASC, j.id DESC',
        'salary_desc' => 'j.salary_max IS NULL, j.salary_max DESC, j.id DESC',
    ];
    $sortKey = is_string($_GET['sort'] ?? null) && isset($sorts[$_GET['sort']]) ? $_GET['sort'] : 'newest';
    $page = query_int('page', 1, 1, 1000);
    $perPage = query_int('per_page', 10, 1, 50);
    $whereSql = implode(' AND ', $where); // only fixed SQL fragments; values are in $params
    // $perPage and $page are ints clamped by query_int(), so LIMIT/OFFSET are safe numbers.

    $total = (int) db_one('SELECT COUNT(*) AS n ' . PUBLIC_JOB_FROM . ' WHERE ' . $whereSql, $params)['n'];
    $rows = db_all(
        'SELECT j.id, j.title, j.location, j.category, j.salary_min, j.salary_max, j.deadline, c.company_name '
        . PUBLIC_JOB_FROM . ' WHERE ' . $whereSql . ' ORDER BY ' . $sorts[$sortKey] . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
        $params
    );
    json_success([
        'items' => array_map('job_summary', $rows),
        'pagination' => pagination_meta($page, $perPage, $total),
    ]);
});

route('GET', '/jobs/{id}', function (array $params): void {
    $job = find_public_job($params['id']);
    if ($job === null) {
        json_error(404, 'NOT_FOUND', 'Lowongan tidak ditemukan.');
    }
    json_success([
        'id' => (int) $job['id'],
        'title' => $job['title'],
        'description' => $job['description'],
        'location' => $job['location'],
        'category' => $job['category'],
        'salary_min' => $job['salary_min'] !== null ? (int) $job['salary_min'] : null,
        'salary_max' => $job['salary_max'] !== null ? (int) $job['salary_max'] : null,
        'deadline' => $job['deadline'],
        'company' => ['id' => (int) $job['company_id'], 'name' => $job['company_name'], 'website' => $job['website']],
    ]);
});
