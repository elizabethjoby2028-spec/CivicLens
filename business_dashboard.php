<?php
/**
 * CivicLens — Local Business Portal Dashboard
 * Campaign creation, banner uploads, service area selection, and performance analytics (CTR)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/PaymentService.php';

require_auth('business');

$business_id = current_business_id();
$business = get_current_business();
$db = get_db();

// Fetch packages
$packages_stmt = $db->query("SELECT * FROM advertising_packages WHERE is_active = 1 ORDER BY price ASC");
$packages = $packages_stmt->fetchAll();

// Handle New Campaign Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_campaign') {
    verify_csrf();

    $title = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $service_area = sanitize_input($_POST['service_area'] ?? '');
    $target_url = sanitize_input($_POST['target_url'] ?? '');
    $package_id = (int)($_POST['package_id'] ?? 0);

    // Validate package
    $pkg_stmt = $db->prepare("SELECT * FROM advertising_packages WHERE id = ? AND is_active = 1");
    $pkg_stmt->execute([$package_id]);
    $selected_pkg = $pkg_stmt->fetch();

    if (empty($title) || empty($description) || empty($service_area) || !$selected_pkg) {
        flash('error', 'Please fill in all campaign details and select an advertising package.');
    } elseif (!empty($target_url) && !filter_var($target_url, FILTER_VALIDATE_URL)) {
        flash('error', 'Please provide a valid website destination URL (e.g. https://yourbusiness.com).');
    } else {
        // Upload promotional banner
        $banner_path = null;
        if (!empty($_FILES['banner_image']['name'])) {
            $upload_res = validate_and_save_upload($_FILES['banner_image'], 'ads');
            if (!$upload_res['success']) {
                flash('error', 'Banner upload failed: ' . $upload_res['error']);
                redirect('business_dashboard.php');
            }
            $banner_path = $upload_res['path'];
        }

        // Insert campaign
        $ins = $db->prepare("
            INSERT INTO advertising_campaigns 
            (business_id, package_id, title, description, banner_image_path, service_area, target_url, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_approval')
        ");
        $ins->execute([
            $business_id,
            $package_id,
            $title,
            $description,
            $banner_path,
            $service_area,
            $target_url
        ]);
        $campaign_id = $db->lastInsertId();

        // Create payment order
        $order_ref = 'AD-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        $pay_stmt = $db->prepare("
            INSERT INTO payment_orders (order_reference, purpose, amount, currency, payer_email, payer_phone, status, related_entity_id, idempotency_key)
            VALUES (?, 'advertising_package', ?, 'INR', ?, ?, 'paid', ?, ?)
        ");
        $idempotency = 'ad_pkg_' . $campaign_id . '_' . time();
        $pay_stmt->execute([
            $order_ref,
            $selected_pkg['price'],
            $business['email'],
            $business['phone'],
            $campaign_id,
            $idempotency
        ]);

        flash('success', "Campaign '{$title}' created successfully! It is now pending administrative approval.");
        redirect('business_dashboard.php');
    }
}

// Fetch campaigns with analytics
$c_stmt = $db->prepare("
    SELECT c.*, p.name as package_name, p.duration_days, p.price as package_price,
           (SELECT COUNT(*) FROM advertisement_analytics WHERE campaign_id = c.id AND event_type = 'impression') as impressions,
           (SELECT COUNT(*) FROM advertisement_analytics WHERE campaign_id = c.id AND event_type = 'click') as clicks
    FROM advertising_campaigns c
    LEFT JOIN advertising_packages p ON c.package_id = p.id
    WHERE c.business_id = ?
    ORDER BY c.created_at DESC
");
$c_stmt->execute([$business_id]);
$my_campaigns = $c_stmt->fetchAll();

// Calculate totals
$total_impressions = 0;
$total_clicks = 0;
$active_ads_count = 0;

foreach ($my_campaigns as $mc) {
    $total_impressions += (int)$mc['impressions'];
    $total_clicks += (int)$mc['clicks'];
    if ($mc['status'] === 'active') {
        $active_ads_count++;
    }
}

$overall_ctr = ($total_impressions > 0) ? round(($total_clicks / $total_impressions) * 100, 2) : 0;

$page_title = 'Business Dashboard — ' . $business['business_name'];
require_once __DIR__ . '/includes/header.php';
?>

<div style="margin: 1.5rem 0;">
    <!-- Welcome Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.85rem; color: var(--primary-navy); margin-bottom: 0.25rem;">
                💼 <?= e($business['business_name']) ?>
            </h2>
            <p style="color: var(--text-secondary); font-size: 0.95rem;">
                Category: <strong><?= e($business['category']) ?></strong> &bull; City: <?= e($business['city']) ?> &bull; Contact: <?= e($business['owner_name']) ?>
            </p>
        </div>

        <div>
            <button class="btn btn-primary" onclick="document.getElementById('newCampaignModal').style.display='block'">
                + Create Advertising Campaign
            </button>
        </div>
    </div>

    <!-- Verification Banner -->
    <?php if (!$business['is_verified']): ?>
        <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
            <span>⏳ <strong>Account Verification Pending:</strong> Your business profile is under review by administrators. You can draft and schedule campaigns now; they will become active upon approval.</span>
        </div>
    <?php else: ?>
        <div class="alert alert-success" style="margin-bottom: 1.5rem;">
            <span>✓ <strong>Verified Business Partner:</strong> Your commercial account is certified by CivicLens administration.</span>
        </div>
    <?php endif; ?>

    <!-- Performance Analytics Cards -->
    <div class="stat-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <span class="stat-label">Total Campaigns</span>
            <div class="stat-value"><?= count($my_campaigns) ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Active Advertisements</span>
            <div class="stat-value" style="color: #059669;"><?= $active_ads_count ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Total Impressions</span>
            <div class="stat-value" style="color: #2563eb;"><?= number_format($total_impressions) ?></div>
            <span style="font-size: 0.78rem; color: var(--text-muted);">Civic portal views</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Direct Clicks</span>
            <div class="stat-value" style="color: var(--primary-navy);"><?= number_format($total_clicks) ?></div>
            <span style="font-size: 0.78rem; color: var(--text-muted);">Website visitors</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Average CTR</span>
            <div class="stat-value" style="color: #0d9488;"><?= $overall_ctr ?>%</div>
            <span style="font-size: 0.78rem; color: var(--text-muted);">Click-Through Rate</span>
        </div>
    </div>

    <!-- Campaigns List -->
    <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 2rem;">
        <div class="card-header" style="padding: 1.25rem 1.5rem;">
            <h3 class="card-title">Promotional Campaigns</h3>
        </div>

        <?php if (empty($my_campaigns)): ?>
            <div style="text-align: center; padding: 3rem 1.5rem; color: var(--text-secondary);">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📣</div>
                <h4 style="color: var(--navy-blue); margin-bottom: 0.5rem;">No campaigns created yet</h4>
                <p style="margin-bottom: 1.25rem;">Start reaching neighborhood customers in your local service area today.</p>
                <button class="btn btn-primary btn-sm" onclick="document.getElementById('newCampaignModal').style.display='block'">
                    Launch Your First Campaign &rarr;
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Target Service Area</th>
                            <th>Package</th>
                            <th>Status</th>
                            <th>Impressions</th>
                            <th>Clicks</th>
                            <th>CTR</th>
                            <th>Schedule</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_campaigns as $camp): ?>
                            <?php 
                            $c_ctr = ($camp['impressions'] > 0) ? round(($camp['clicks'] / $camp['impressions']) * 100, 2) : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong style="color: var(--primary-navy);"><?= e($camp['title']) ?></strong>
                                    <div style="font-size: 0.82rem; color: var(--text-muted); max-width: 250px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                        <?= e($camp['description']) ?>
                                    </div>
                                    <?php if (!empty($camp['banner_image_path'])): ?>
                                        <a href="<?= url($camp['banner_image_path']) ?>" target="_blank" style="font-size: 0.75rem; text-decoration: underline; color: var(--navy-light);">
                                            View Banner Image
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><strong>📍 <?= e($camp['service_area']) ?></strong></td>
                                <td style="font-size: 0.85rem;">
                                    <?= e($camp['package_name']) ?><br>
                                    <span style="color: var(--text-muted);">₹<?= number_format($camp['package_price'], 2) ?></span>
                                </td>
                                <td>
                                    <?php if ($camp['status'] === 'active'): ?>
                                        <span class="badge badge-success">✓ Active</span>
                                    <?php elseif ($camp['status'] === 'pending_approval'): ?>
                                        <span class="badge badge-warning">Pending Review</span>
                                    <?php elseif ($camp['status'] === 'rejected'): ?>
                                        <span class="badge badge-danger">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= e(ucfirst($camp['status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($camp['impressions']) ?></td>
                                <td><?= number_format($camp['clicks']) ?></td>
                                <td><strong style="color: #059669;"><?= $c_ctr ?>%</strong></td>
                                <td style="font-size: 0.82rem; color: var(--text-secondary);">
                                    <?php if ($camp['status'] === 'active'): ?>
                                        <?= format_date($camp['start_date'], 'M d') ?> &ndash; <?= format_date($camp['end_date'], 'M d, Y') ?>
                                    <?php else: ?>
                                        <?= (int)$camp['duration_days'] ?> Days
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Create New Campaign -->
<div id="newCampaignModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; overflow-y: auto; padding: 2rem 1rem;">
    <div class="card" style="max-width: 650px; margin: 2rem auto; position: relative;">
        <div class="card-header">
            <div>
                <span style="font-size: 0.8rem; font-weight: 700; color: #059669; text-transform: uppercase;">Digital Marketing</span>
                <h3 class="card-title">Create Local Advertisement Campaign</h3>
            </div>
            <button onclick="document.getElementById('newCampaignModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="business_dashboard.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_campaign">

            <div class="form-group">
                <label class="form-label" for="camp_title">Campaign Title / Headline <span class="required">*</span></label>
                <input type="text" id="camp_title" name="title" class="form-control" placeholder="e.g. 20% Off Eco-Friendly Plumbing Services" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="camp_desc">Promotional Description <span class="required">*</span></label>
                <textarea id="camp_desc" name="description" class="form-control" rows="3" placeholder="Highlight your services, neighborhood emergency response, or special offers..." required></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="service_area">Target Service Area / City <span class="required">*</span></label>
                    <input type="text" id="service_area" name="service_area" class="form-control" value="<?= e($business['city']) ?>" placeholder="e.g. Kozhikode North" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="target_url">Destination Link (Website / WhatsApp)</label>
                    <input type="url" id="target_url" name="target_url" class="form-control" value="<?= e($business['website_url'] ?? '') ?>" placeholder="https://yourshop.com">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="banner_image">Promotional Banner Image (PNG, JPG, WebP &bull; Max 5MB)</label>
                <input type="file" id="banner_image" name="banner_image" class="form-control" accept="image/png, image/jpeg, image/webp">
                <span class="form-hint">Displayed in "Discover Local Businesses Near You" civic section.</span>
            </div>

            <div class="form-group">
                <label class="form-label">Select Advertising Package <span class="required">*</span></label>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem;">
                    <?php foreach ($packages as $idx => $pkg): ?>
                        <label style="border: 2px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; cursor: pointer; display: block;">
                            <input type="radio" name="package_id" value="<?= (int)$pkg['id'] ?>" <?= ($idx === 0) ? 'checked' : '' ?> required>
                            <div style="margin-top: 0.35rem;">
                                <strong style="color: var(--primary-navy);"><?= e($pkg['name']) ?></strong>
                                <div style="font-size: 1.15rem; font-weight: 700; color: #059669; margin: 0.2rem 0;">₹<?= number_format($pkg['price'], 2) ?></div>
                                <span style="font-size: 0.8rem; color: var(--text-muted);"><?= (int)$pkg['duration_days'] ?> Days Duration</span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('newCampaignModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #059669; border-color: #059669;">
                    Submit Campaign &rarr;
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
