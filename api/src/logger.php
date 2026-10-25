<?php
/**
 * Audit log (Slot E). Store only who did what to which record and from where.
 * Never pass passwords, tokens, session IDs, email addresses of failed logins, or CV content.
 *   log_activity('company.job.create', $userId, 'job', $jobId);
 */
declare(strict_types=1);

function log_activity(string $action, ?int $userId = null, ?string $targetType = null, ?int $targetId = null): void {
    if (!preg_match('/^[a-z_.]{3,64}$/', $action)) {
        throw new InvalidArgumentException('Invalid log action');
    }
    try {
        db_query(
            'INSERT INTO activity_logs (user_id, action, target_type, target_id, ip_address) VALUES (?, ?, ?, ?, ?)',
            [$userId, $action, $targetType, $targetId, client_ip()]
        );
    } catch (PDOException $e) {
        // Logging must not break the request; record the failure in the server log only.
        error_log('[jobnest-api] activity log failed: ' . $e->getCode());
    }
}
