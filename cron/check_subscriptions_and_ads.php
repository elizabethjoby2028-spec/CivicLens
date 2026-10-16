<?php
/**
 * CivicLens — Scheduled Task: Subscriptions & Ad Expiry Processor
 *
 * Automatically marks ended advertising campaigns as expired and
 * updates expired authority subscriptions.
 *
 * Usage: php cron/check_subscriptions_and_ads.php
 */

if (php_sapi_name() !== 'cli' && empty($_GET['cron_key'])) {
    require_once __DIR__ . '/../config.php';
    $providedKey = $_GET['key'] ?? '';
    $expectedKey = env('CRON_SECRET_KEY', 'civiclens_cron_token_2026');
    if ($providedKey !== $expectedKey) {
        http_response_code(403);
        die("Forbidden\n");
    }
} else {
    require_once __DIR__ . '/../config.php';
}

require_once __DIR__ . '/../includes/db.php';

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] [CivicLens Cron] Starting ad campaigns and authority subscriptions expiry check...\n";

$db = get_db();

// 1. Expire ended Ad Campaigns
$ads_stmt = $db->query("
    UPDATE advertising_campaigns 
    SET status = 'expired' 
    WHERE status = 'active' 
      AND end_date IS NOT NULL 
      AND end_date <= NOW()
");
$expiredAdsCount = $ads_stmt->rowCount();
echo " -> Expired {$expiredAdsCount} advertising campaign(s).\n";

// 2. Expire ended Authority Subscriptions
$subs_stmt = $db->query("
    UPDATE authority_subscriptions 
    SET payment_status = 'expired' 
    WHERE payment_status = 'active' 
      AND end_date IS NOT NULL 
      AND end_date <= NOW()
");
$expiredSubsCount = $subs_stmt->rowCount();
echo " -> Expired {$expiredSubsCount} authority subscription(s).\n";

$elapsed = round(microtime(true) - $startTime, 4);
echo "[CivicLens Cron] Expiry sweep completed in {$elapsed}s.\n";
