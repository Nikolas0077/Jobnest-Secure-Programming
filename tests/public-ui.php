<?php
require_once __DIR__ . '/../app/includes/job-view.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$payload = '<img src=x onerror=alert(1)>';
ob_start();
render_job_card([
    'id' => 1,
    'title' => $payload,
    'company_name' => $payload,
    'location' => $payload,
    'description' => $payload,
]);
$html = ob_get_clean();
check(!str_contains($html, $payload), 'Job card reflected unsafe HTML');
check(substr_count($html, '&lt;img src=x onerror=alert(1)&gt;') === 4, 'Job card output was not escaped');
check(valid_job_summary(['id' => 1, 'title' => 'Developer', 'company_name' => 'Acme']), 'Valid job rejected');
check(!valid_job_summary(['id' => '1', 'title' => 'Developer', 'company_name' => 'Acme']), 'Invalid job accepted');

$_GET['q'] = $payload;
ob_start();
include __DIR__ . '/../public/jobs/index.php';
$html = ob_get_clean();
check(str_contains($html, 'value="&lt;img src=x onerror=alert(1)&gt;"'), 'Filter query was not escaped');
check(!str_contains($html, 'value="' . $payload . '"'), 'Filter reflected unsafe HTML');

ob_start();
include __DIR__ . '/../public/login.php';
$html = ob_get_clean();
check(str_contains($html, 'action="/auth-action.php?intent=login"'), 'Login form does not submit to PHP');
check(str_contains($html, 'name="form_token"'), 'Login form lacks CSRF token');

echo "PHP markup, form action, and escaping checks passed.\n";
