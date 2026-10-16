<?php
/**
 * CivicLens — Ad Click Tracker & Safe Redirector
 * Records click event in advertisement_analytics and safely redirects without exposing user PII.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$ad_id = (int)($_GET['ad_id'] ?? 0);

if ($ad_id <= 0) {
    redirect('advertisements.php');
}

$db = get_db();

// Verify active campaign and verified business
$stmt = $db->prepare("
    SELECT c.id, c.target_url, c.status
    FROM advertising_campaigns c
    JOIN businesses b ON c.business_id = b.id
    WHERE c.id = ? 
      AND c.status = 'active'
      AND b.is_verified = 1
      AND (c.start_date IS NULL OR c.start_date <= NOW())
      AND (c.end_date IS NULL OR c.end_date >= NOW())
");
$stmt->execute([$ad_id]);
$ad = $stmt->fetch();

if (!$ad) {
    flash('info', 'This advertisement campaign is no longer active.');
    redirect('advertisements.php');
}

// Log click event without storing IP or personal identifiers
try {
    $log = $db->prepare("
        INSERT INTO advertisement_analytics (campaign_id, event_type, ip_hash, user_agent, created_at)
        VALUES (?, 'click', NULL, NULL, NOW())
    ");
    $log->execute([$ad['id']]);
} catch (Exception $e) {
    // Non-fatal logging error
}

$target_url = trim($ad['target_url'] ?? '');

// Validate destination URL to prevent Open Redirect attacks or malicious protocols
if (empty($target_url) || !filter_var($target_url, FILTER_VALIDATE_URL)) {
    redirect('advertisements.php');
}

$parsed = parse_url($target_url);
$scheme = strtolower($parsed['scheme'] ?? '');
if (!in_array($scheme, ['http', 'https'], true)) {
    redirect('advertisements.php');
}

// Perform safe external redirect
header("Location: " . $target_url, true, 302);
exit;
