<?php
/**
 * CivicLens — Scheduled Task: 10-Day Feedback Window Expiration Handler
 *
 * Automatically closes complaints where the 10-day feedback window has elapsed
 * without citizen response.
 * Records:
 * - status = 'Closed'
 * - satisfaction_status = 'No Response'
 * - closure_reason = 'No Response'
 *
 * Safe against race conditions and concurrent user submissions.
 * Usage: php cron/close_expired_complaints.php
 */

if (php_sapi_name() !== 'cli' && empty($_GET['cron_key'])) {
    // If run via web, require a secure CRON_KEY
    require_once __DIR__ . '/../config.php';
    $providedKey = $_GET['key'] ?? '';
    $expectedKey = env('CRON_SECRET_KEY', 'civiclens_cron_token_2026');
    if ($providedKey !== $expectedKey) {
        http_response_code(403);
        die("Forbidden: Invalid Cron Secret Key\n");
    }
} else {
    require_once __DIR__ . '/../config.php';
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/NotificationService.php';

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] [CivicLens Cron] Starting 10-day feedback deadline expiration check...\n";

$db = get_db();

// Find complaints in 'Solved' status where deadline has passed and feedback is still 'Pending'
$stmt = $db->query("
    SELECT id, complaint_id, citizen_id, assigned_authority_id, feedback_deadline
    FROM complaints
    WHERE status = 'Solved'
      AND satisfaction_status = 'Pending'
      AND feedback_deadline IS NOT NULL
      AND feedback_deadline <= NOW()
");

$expired = $stmt->fetchAll();
$totalFound = count($expired);
$closedCount = 0;

echo "[CivicLens Cron] Found {$totalFound} candidate complaint(s) with expired feedback deadline.\n";

foreach ($expired as $c) {
    try {
        $db->beginTransaction();

        // Atomically update only if status is still 'Solved' and satisfaction_status is 'Pending'
        // This ensures concurrent user submissions are never overwritten
        $upd = $db->prepare("
            UPDATE complaints 
            SET status = 'Closed',
                satisfaction_status = 'No Response',
                closure_reason = 'No Response',
                closed_at = NOW()
            WHERE id = ? 
              AND status = 'Solved' 
              AND satisfaction_status = 'Pending'
        ");
        $upd->execute([$c['id']]);

        if ($upd->rowCount() > 0) {
            // Record status history entry
            $hist = $db->prepare("
                INSERT INTO complaint_status_history 
                (complaint_id, from_status, to_status, actor_type, actor_id, actor_name, note)
                VALUES (?, 'Solved', 'Closed', 'system_cron', NULL, 'Automated System Scheduler', 
                        'Automatically closed after 10-day feedback window expired without citizen response. Satisfaction marked as No Response.')
            ");
            $hist->execute([$c['id']]);

            // Notify citizen
            if (!empty($c['citizen_id'])) {
                NotificationService::notifyCitizen(
                    $c['citizen_id'],
                    $c['id'],
                    'Complaint Closed (Feedback Window Expired)',
                    "Complaint {$c['complaint_id']} has been closed as the 10-day satisfaction feedback period concluded without response.",
                    'complaint_closed'
                );
            }

            // Notify assigned authority
            if (!empty($c['assigned_authority_id'])) {
                NotificationService::notifyAuthority(
                    $c['assigned_authority_id'],
                    $c['id'],
                    'Complaint Closed — No Response After 10 Days',
                    "Complaint {$c['complaint_id']} was automatically closed as the 10-day citizen feedback period elapsed with no response.",
                    'complaint_closed'
                );
            }

            $closedCount++;
            echo " -> Closed complaint {$c['complaint_id']} (Deadline: {$c['feedback_deadline']})\n";
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        echo " [ERROR] Failed processing complaint {$c['complaint_id']}: {$e->getMessage()}\n";
    }
}

$elapsed = round(microtime(true) - $startTime, 4);
echo "[CivicLens Cron] Task finished in {$elapsed}s. Successfully closed {$closedCount} complaint(s).\n";
