<?php
/**
 * CivicLens — Administrator Advertisement Moderation & Package Management
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';

require_auth('admin');

$db = get_db();

// Handle Moderation & Package Price Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = sanitize_input($_POST['action'] ?? '');

    // Action 1: Approve Campaign
    if ($action === 'approve_campaign') {
        $campaign_id = (int)($_POST['campaign_id'] ?? 0);

        // Fetch campaign details to calculate dates based on package duration
        $stmt = $db->prepare("
            SELECT c.*, p.duration_days 
            FROM advertising_campaigns c
            LEFT JOIN advertising_packages p ON c.package_id = p.id
            WHERE c.id = ?
        ");
        $stmt->execute([$campaign_id]);
        $camp = $stmt->fetch();

        if ($camp) {
            $duration = (int)($camp['duration_days'] ?? 30);
            $upd = $db->prepare("
                UPDATE advertising_campaigns 
                SET status = 'active',
                    start_date = NOW(),
                    end_date = DATE_ADD(NOW(), INTERVAL ? DAY)
                WHERE id = ?
            ");
            $upd->execute([$duration, $campaign_id]);
            flash('success', "Campaign '{$camp['title']}' approved and activated for {$duration} days.");
        }
        redirect('admin_ads.php');
    }

    // Action 2: Reject Campaign
    if ($action === 'reject_campaign') {
        $campaign_id = (int)($_POST['campaign_id'] ?? 0);
        $reason = sanitize_input($_POST['rejection_reason'] ?? 'Content does not meet civic ad guidelines.');

        $stmt = $db->prepare("
            UPDATE advertising_campaigns 
            SET status = 'rejected' 
            WHERE id = ?
        ");
        $stmt->execute([$campaign_id]);
        flash('warning', 'Campaign has been rejected.');
        redirect('admin_ads.php');
    }

    // Action 3: Pause or Expire Campaign
    if ($action === 'toggle_campaign_status') {
        $campaign_id = (int)($_POST['campaign_id'] ?? 0);
        $new_status = sanitize_input($_POST['new_status'] ?? 'paused');
        $stmt = $db->prepare("UPDATE advertising_campaigns SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $campaign_id]);
        flash('info', "Campaign status changed to {$new_status}.");
        redirect('admin_ads.php');
    }

    // Action 4: Update Package Pricing
    if ($action === 'update_package_price') {
        $package_id = (int)($_POST['package_id'] ?? 0);
        $new_price = (float)($_POST['price'] ?? 0);
        $new_duration = (int)($_POST['duration_days'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($new_price > 0 && $new_duration > 0) {
            $stmt = $db->prepare("
                UPDATE advertising_packages 
                SET price = ?, duration_days = ?, is_active = ? 
                WHERE id = ?
            ");
            $stmt->execute([$new_price, $new_duration, $is_active, $package_id]);
            flash('success', 'Advertising package updated successfully.');
        } else {
            flash('error', 'Invalid package price or duration.');
        }
        redirect('admin_ads.php#packages');
    }
}

// Fetch all campaigns with business and package details
$campaigns_stmt = $db->query("
    SELECT c.*, 
           b.business_name, b.email as business_email, b.is_verified as business_verified,
           p.name as package_name, p.duration_days, p.price as package_price,
           (SELECT COUNT(*) FROM advertisement_analytics WHERE campaign_id = c.id AND event_type = 'impression') as impressions,
           (SELECT COUNT(*) FROM advertisement_analytics WHERE campaign_id = c.id AND event_type = 'click') as clicks
    FROM advertising_campaigns c
    JOIN businesses b ON c.business_id = b.id
    LEFT JOIN advertising_packages p ON c.package_id = p.id
    ORDER BY (c.status = 'pending_approval') DESC, c.created_at DESC
");
$campaigns = $campaigns_stmt->fetchAll();

// Fetch all advertising packages
$packages_stmt = $db->query("SELECT * FROM advertising_packages ORDER BY price ASC");
$packages = $packages_stmt->fetchAll();

$page_title = 'Advertisement Moderation — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="margin: 1.5rem 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <a href="<?= url('admin_dashboard.php') ?>" style="font-size: 0.9rem; color: var(--text-muted);">&larr; Back to Admin Dashboard</a>
            <h2 style="font-size: 1.85rem; color: var(--primary-navy); margin-top: 0.25rem;">
                📢 Local Business Advertising Moderation
            </h2>
            <p style="color: var(--text-secondary); font-size: 0.95rem;">
                Review promotional campaigns, maintain advertising guidelines, and manage commercial packages.
            </p>
        </div>

        <a href="<?= url('advertisements.php') ?>" target="_blank" class="btn btn-outline btn-sm">
            View Public "Discover Local Businesses" Directory &rarr;
        </a>
    </div>

    <!-- Campaigns List -->
    <div class="card" style="margin-bottom: 2.5rem; padding: 0; overflow: hidden;">
        <div class="card-header" style="padding: 1.25rem 1.5rem;">
            <h3 class="card-title">Submitted Campaigns (<?= count($campaigns) ?>)</h3>
        </div>

        <?php if (empty($campaigns)): ?>
            <div style="text-align: center; padding: 3rem 1.5rem; color: var(--text-secondary);">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📣</div>
                <p>No business advertising campaigns submitted yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Campaign Title & Banner</th>
                            <th>Business Name</th>
                            <th>Service Area</th>
                            <th>Package</th>
                            <th>Status</th>
                            <th>Analytics (CTR)</th>
                            <th>Schedule</th>
                            <th>Moderation Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $camp): ?>
                            <?php 
                            $ctr = ($camp['impressions'] > 0) ? round(($camp['clicks'] / $camp['impressions']) * 100, 2) : 0;
                            ?>
                            <tr style="<?= ($camp['status'] === 'pending_approval') ? 'background: #fffbeb;' : '' ?>">
                                <td>
                                    <strong style="color: var(--primary-navy); display: block;"><?= e($camp['title']) ?></strong>
                                    <p style="font-size: 0.82rem; color: var(--text-secondary); margin: 0.2rem 0 0.4rem 0; max-width: 260px;">
                                        <?= e($camp['description']) ?>
                                    </p>
                                    <?php if (!empty($camp['banner_image_path'])): ?>
                                        <a href="<?= url($camp['banner_image_path']) ?>" target="_blank" style="font-size: 0.78rem; text-decoration: underline; color: var(--navy-light);">
                                            🖼️ View Banner Image
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= e($camp['business_name']) ?></strong>
                                    <div style="font-size: 0.78rem;">
                                        <?= $camp['business_verified'] ? '<span style="color:#059669;">✓ Verified Biz</span>' : '<span style="color:#b45309;">⚠️ Unverified Biz</span>' ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 0.88rem; font-weight: 600;">📍 <?= e($camp['service_area']) ?></span>
                                </td>
                                <td style="font-size: 0.85rem;">
                                    <strong><?= e($camp['package_name']) ?></strong><br>
                                    <span style="color: var(--text-muted);">₹<?= number_format($camp['package_price'], 2) ?></span>
                                </td>
                                <td>
                                    <?php if ($camp['status'] === 'active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php elseif ($camp['status'] === 'pending_approval'): ?>
                                        <span class="badge badge-warning">Pending Approval</span>
                                    <?php elseif ($camp['status'] === 'rejected'): ?>
                                        <span class="badge badge-danger">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= e(ucfirst($camp['status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.82rem;">
                                    <div>👁️ <strong><?= (int)$camp['impressions'] ?></strong> Impr</div>
                                    <div>🖱️ <strong><?= (int)$camp['clicks'] ?></strong> Clicks</div>
                                    <div style="color: #059669; font-weight: 700;"><?= $ctr ?>% CTR</div>
                                </td>
                                <td style="font-size: 0.8rem; color: var(--text-secondary);">
                                    <?php if ($camp['status'] === 'active'): ?>
                                        <?= format_date($camp['start_date'], 'M d') ?> &ndash; <?= format_date($camp['end_date'], 'M d, Y') ?>
                                    <?php else: ?>
                                        Duration: <?= (int)$camp['duration_days'] ?> Days
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.4rem; flex-direction: column;">
                                        <?php if ($camp['status'] === 'pending_approval'): ?>
                                            <form method="POST" action="admin_ads.php" style="margin: 0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="approve_campaign">
                                                <input type="hidden" name="campaign_id" value="<?= (int)$camp['id'] ?>">
                                                <button type="submit" class="btn btn-success btn-sm btn-block" style="font-size: 0.78rem;">
                                                    ✓ Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="admin_ads.php" style="margin: 0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="reject_campaign">
                                                <input type="hidden" name="campaign_id" value="<?= (int)$camp['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm btn-block" style="font-size: 0.78rem;">
                                                    ✗ Reject
                                                </button>
                                            </form>
                                        <?php elseif ($camp['status'] === 'active'): ?>
                                            <form method="POST" action="admin_ads.php" style="margin: 0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_campaign_status">
                                                <input type="hidden" name="campaign_id" value="<?= (int)$camp['id'] ?>">
                                                <input type="hidden" name="new_status" value="paused">
                                                <button type="submit" class="btn btn-secondary btn-sm btn-block" style="font-size: 0.78rem;">
                                                    Pause
                                                </button>
                                            </form>
                                        <?php elseif ($camp['status'] === 'paused'): ?>
                                            <form method="POST" action="admin_ads.php" style="margin: 0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_campaign_status">
                                                <input type="hidden" name="campaign_id" value="<?= (int)$camp['id'] ?>">
                                                <input type="hidden" name="new_status" value="active">
                                                <button type="submit" class="btn btn-outline btn-sm btn-block" style="font-size: 0.78rem;">
                                                    Resume
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Advertising Packages Management -->
    <div id="packages" class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">📦 Commercial Advertising Packages & Pricing</h3>
                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.2rem;">
                    Configure pricing and active package offerings available to local businesses.
                </p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
            <?php foreach ($packages as $pkg): ?>
                <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; background: var(--bg-surface-alt);">
                    <form method="POST" action="admin_ads.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_package_price">
                        <input type="hidden" name="package_id" value="<?= (int)$pkg['id'] ?>">

                        <div style="margin-bottom: 0.75rem;">
                            <strong style="font-size: 1.15rem; color: var(--primary-navy);"><?= e($pkg['name']) ?></strong>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0.35rem 0 0 0;">
                                <?= e($pkg['description']) ?>
                            </p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-size: 0.82rem;">Price (₹)</label>
                            <input type="number" step="0.01" name="price" class="form-control" value="<?= (float)$pkg['price'] ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-size: 0.82rem;">Duration (Days)</label>
                            <input type="number" name="duration_days" class="form-control" value="<?= (int)$pkg['duration_days'] ?>" required>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label style="font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="is_active" value="1" <?= $pkg['is_active'] ? 'checked' : '' ?>>
                                Available for Purchase
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block btn-sm">
                            Save Package Changes
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
