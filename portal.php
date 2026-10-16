<?php
/**
 * CivicLens — Citizen Portal Hub
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';

$page_title = __('citizen_portal');
require_once __DIR__ . '/includes/header.php';

// Fetch approved & active advertisements for "Discover Local Businesses Near You"
$active_ads = [];
try {
    $db = get_db();
    $ad_stmt = $db->query("
        SELECT c.*, b.business_name, b.city, b.category as biz_category
        FROM advertising_campaigns c
        JOIN businesses b ON c.business_id = b.id
        WHERE c.status = 'active'
          AND c.paid_status = 'paid'
          AND (c.start_date IS NULL OR c.start_date <= NOW())
          AND (c.end_date IS NULL OR c.end_date >= NOW())
        ORDER BY RAND()
        LIMIT 3
    ");
    $active_ads = $ad_stmt->fetchAll();

    // Record impression for displayed ads
    if (!empty($active_ads)) {
        $ipHash = get_anonymized_ip_hash();
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
        $imp_stmt = $db->prepare("INSERT INTO advertisement_analytics (campaign_id, event_type, user_ip_hash, user_agent) VALUES (?, 'impression', ?, ?)");
        foreach ($active_ads as $ad) {
            $imp_stmt->execute([$ad['id'], $ipHash, $ua]);
        }
    }
} catch (Exception $e) {
    // Fail silently if DB not yet migrated
}
?>

<div style="margin-bottom: 2.5rem;">
    <div style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem;">
        <a href="<?= url('index.php') ?>">Home</a> &rarr; <span><?= e(__('citizen_portal')) ?></span>
    </div>
    <h2><?= e(__('citizen_portal')) ?></h2>
    <p style="color: var(--text-secondary);">Welcome to the citizen gateway. Submit verified or anonymous reports, track resolution progress in real time, and hold public authorities accountable.</p>
</div>

<!-- Citizen Action Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 3.5rem;">
    
    <!-- Option A: File a Regular Free Complaint -->
    <div class="card" style="margin-bottom: 0;">
        <div style="font-size: 2.5rem; margin-bottom: 1rem;">📝</div>
        <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;"><?= e(__('report_issue')) ?></h3>
        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1.5rem;">
            Submit a free verified civic issue under your account. Receive in-app notifications, view status updates, and submit satisfaction feedback.
        </p>
        <?php if (is_citizen_logged_in()): ?>
            <a href="<?= url('report_issue.php') ?>" class="btn btn-primary btn-block">+ Submit Complaint</a>
        <?php else: ?>
            <div style="display: flex; gap: 0.5rem;">
                <a href="<?= url('login.php') ?>" class="btn btn-primary" style="flex: 1;"><?= e(__('login')) ?></a>
                <a href="<?= url('register.php') ?>" class="btn btn-outline" style="flex: 1;"><?= e(__('register')) ?></a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Option B: Anonymous Complaint -->
    <div class="card" style="margin-bottom: 0; border-color: #bae6fd; background: #f0f9ff;">
        <div style="font-size: 2.5rem; margin-bottom: 1rem;">🕵️</div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
            <h3 style="font-size: 1.25rem;"><?= e(__('anonymous_complaint')) ?></h3>
            <span style="background: var(--navy-blue); color: #fff; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 9999px;">
                ₹<?= (int)ANONYMOUS_COMPLAINT_FEE ?>
            </span>
        </div>
        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1.5rem;">
            File sensitive issues without an account or exposing your identity. Protected with cryptographic tracking credentials and secure payment verification.
        </p>
        <a href="<?= url('anonymous.php') ?>" class="btn btn-accent btn-block">File Anonymous Complaint</a>
    </div>

    <!-- Option C: Track Complaint -->
    <div class="card" style="margin-bottom: 0;">
        <div style="font-size: 2.5rem; margin-bottom: 1rem;">🔍</div>
        <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;"><?= e(__('track_complaint')) ?></h3>
        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1.5rem;">
            Enter your unique Complaint ID (and secret tracking credential for anonymous reports) to view full history, resolution notes, and feedback.
        </p>
        <a href="<?= url('track_complaint.php') ?>" class="btn btn-secondary btn-block">Track Progress &rarr;</a>
    </div>

</div>

<!-- Discover Local Businesses Section -->
<section class="ads-showcase-section">
    <div class="ads-header">
        <div>
            <h3 style="font-size: 1.4rem; color: var(--primary-navy);"><?= e(__('discover_local')) ?></h3>
            <p style="font-size: 0.85rem; color: var(--text-muted);">Verified local businesses and professional services in your community.</p>
        </div>
        <a href="<?= url('advertisements.php') ?>" class="btn btn-outline btn-sm">View All Directory &rarr;</a>
    </div>

    <?php if (empty($active_ads)): ?>
        <div style="background: var(--bg-surface); border: 1px dashed var(--border-color); border-radius: var(--radius-lg); padding: 2.5rem; text-align: center;">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🛍️</div>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Are you a local business owner? Reach thousands of local citizens by promoting your services here.</p>
            <a href="<?= url('business_register.php') ?>" class="btn btn-primary btn-sm" style="margin-top: 1rem;">Promote Your Business</a>
        </div>
    <?php else: ?>
        <div class="ads-grid">
            <?php foreach ($active_ads as $ad): ?>
                <div class="ad-card">
                    <?php if (!empty($ad['banner_image']) && file_exists(ROOT_PATH . '/' . $ad['banner_image'])): ?>
                        <img src="<?= url($ad['banner_image']) ?>" alt="<?= e($ad['title']) ?>" class="ad-image">
                    <?php else: ?>
                        <div class="ad-image" style="display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #94a3b8;">
                            🏪
                        </div>
                    <?php endif; ?>
                    <div class="ad-body">
                        <div>
                            <div class="ad-meta">
                                <span class="ad-sponsored-badge"><?= e(__('sponsored')) ?></span>
                                <span class="ad-location-badge">📍 <?= e($ad['target_location']) ?></span>
                            </div>
                            <h4 class="ad-title"><?= e($ad['title']) ?></h4>
                            <p class="ad-desc"><?= e($ad['description']) ?></p>
                        </div>
                        <div style="margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 0.75rem; display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 0.8rem; font-weight: 600; color: var(--navy-blue);">
                                <?= e($ad['business_name']) ?>
                            </span>
                            <a href="<?= url('api/ad_click.php?id=' . (int)$ad['id']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline">
                                Visit Offer &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
