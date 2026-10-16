<?php
/**
 * CivicLens — Discover Local Businesses Near You
 * Dedicated local business spotlight section.
 * Only displays active, approved ads from verified local businesses.
 * Records privacy-preserving impression analytics.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';

$db = get_db();

// Filter parameters
$area_filter = sanitize_input($_GET['area'] ?? '');
$cat_filter  = sanitize_input($_GET['category'] ?? '');

$query = "
    SELECT c.*, b.business_name, b.category as biz_category, b.city as biz_city, b.phone as biz_phone
    FROM advertising_campaigns c
    JOIN businesses b ON c.business_id = b.id
    WHERE c.status = 'active'
      AND b.is_verified = 1
      AND (c.start_date IS NULL OR c.start_date <= NOW())
      AND (c.end_date IS NULL OR c.end_date >= NOW())
";
$params = [];

if (!empty($area_filter)) {
    $query .= " AND (c.service_area LIKE ? OR b.city LIKE ?)";
    $like = '%' . $area_filter . '%';
    $params[] = $like;
    $params[] = $like;
}

if (!empty($cat_filter)) {
    $query .= " AND b.category = ?";
    $params[] = $cat_filter;
}

$query .= " ORDER BY c.created_at DESC LIMIT 30";
$stmt = $db->prepare($query);
$stmt->execute($params);
$active_ads = $stmt->fetchAll();

// Record privacy-preserving impression analytics for each displayed advertisement
if (!empty($active_ads)) {
    $impr_stmt = $db->prepare("
        INSERT INTO advertisement_analytics (campaign_id, event_type, created_at)
        VALUES (?, 'impression', NOW())
    ");
    foreach ($active_ads as $ad) {
        try {
            $impr_stmt->execute([$ad['id']]);
        } catch (Exception $e) {
            // Non-blocking
        }
    }
}

// Fetch list of distinct service areas and categories for filters
$areas = $db->query("
    SELECT DISTINCT c.service_area 
    FROM advertising_campaigns c
    JOIN businesses b ON c.business_id = b.id
    WHERE c.status = 'active' AND b.is_verified = 1
")->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'Discover Local Businesses Near You — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 1000px; margin: 1.5rem auto;">
    <!-- Header Banner -->
    <div style="background: linear-gradient(135deg, #0a2540 0%, #1e3a8a 100%); color: #fff; padding: 2.5rem 2rem; border-radius: var(--radius-lg); margin-bottom: 2rem; box-shadow: var(--shadow-md);">
        <div style="max-width: 650px;">
            <span style="background: rgba(255,255,255,0.15); color: #93c5fd; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; padding: 4px 10px; border-radius: 9999px; display: inline-block; margin-bottom: 0.75rem;">
                Civic Community Directory
            </span>
            <h1 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 0.75rem; letter-spacing: -0.02em; color: #fff;">
                Discover Local Businesses Near You
            </h1>
            <p style="font-size: 1.05rem; color: #cbd5e1; line-height: 1.6; margin-bottom: 1.25rem;">
                Connect with verified local merchants, service technicians, emergency hardware providers, and neighborhood shops certified in your municipal area.
            </p>
            <div>
                <a href="<?= url('business_register.php') ?>" class="btn btn-outline" style="color: #fff; border-color: rgba(255,255,255,0.4); background: rgba(255,255,255,0.1);">
                    Are you a local business? Partner With Us &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 2rem; padding: 1.25rem;">
        <form method="GET" action="advertisements.php" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 2; min-width: 220px;">
                <label class="form-label" style="font-size: 0.85rem;">Service Area / City</label>
                <input type="text" name="area" class="form-control" placeholder="Search by city or neighborhood..." value="<?= e($area_filter) ?>">
            </div>

            <div style="flex: 1; min-width: 200px;">
                <label class="form-label" style="font-size: 0.85rem;">Category</label>
                <select name="category" class="form-control">
                    <option value="">All Business Sectors</option>
                    <option value="Plumbing & Electrical" <?= ($cat_filter === 'Plumbing & Electrical') ? 'selected' : '' ?>>Plumbing & Electrical</option>
                    <option value="Construction & Hardware" <?= ($cat_filter === 'Construction & Hardware') ? 'selected' : '' ?>>Construction & Hardware</option>
                    <option value="Waste & Recycling Services" <?= ($cat_filter === 'Waste & Recycling Services') ? 'selected' : '' ?>>Waste & Recycling Services</option>
                    <option value="Grocery & Organic Food" <?= ($cat_filter === 'Grocery & Organic Food') ? 'selected' : '' ?>>Grocery & Organic Food</option>
                    <option value="Healthcare & Pharmacy" <?= ($cat_filter === 'Healthcare & Pharmacy') ? 'selected' : '' ?>>Healthcare & Pharmacy</option>
                    <option value="Automotive & Transport" <?= ($cat_filter === 'Automotive & Transport') ? 'selected' : '' ?>>Automotive & Transport</option>
                    <option value="Other Local Services" <?= ($cat_filter === 'Other Local Services') ? 'selected' : '' ?>>Other Local Services</option>
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="advertisements.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Advertisements Grid -->
    <?php if (empty($active_ads)): ?>
        <div class="card" style="text-align: center; padding: 3.5rem 1.5rem; color: var(--text-secondary);">
            <div style="font-size: 3rem; margin-bottom: 0.75rem;">🏪</div>
            <h3 style="color: var(--primary-navy); margin-bottom: 0.5rem;">No active business spotlights found</h3>
            <p style="max-width: 450px; margin: 0 auto 1.5rem auto;">
                Currently no commercial campaigns match the selected location or category.
            </p>
            <a href="<?= url('advertisements.php') ?>" class="btn btn-outline btn-sm">Clear Filters</a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <?php foreach ($active_ads as $ad): ?>
                <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; position: relative; border-top: 3px solid #059669;">
                    <!-- Sponsored Tag -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                        <span style="font-size: 0.72rem; font-weight: 700; color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 2px 8px; border-radius: 4px; text-transform: uppercase;">
                            Sponsored &bull; Local Business
                        </span>
                        <span style="font-size: 0.78rem; color: var(--text-muted);">
                            📍 <?= e($ad['service_area']) ?>
                        </span>
                    </div>

                    <?php if (!empty($ad['banner_image_path']) && file_exists(ROOT_PATH . '/' . $ad['banner_image_path'])): ?>
                        <div style="margin-bottom: 1rem; border-radius: var(--radius-sm); overflow: hidden; max-height: 180px;">
                            <img src="<?= url($ad['banner_image_path']) ?>" alt="<?= e($ad['title']) ?>" style="width: 100%; height: 160px; object-fit: cover;">
                        </div>
                    <?php endif; ?>

                    <div style="flex: 1;">
                        <span style="font-size: 0.8rem; font-weight: 600; color: #059669; text-transform: uppercase;"><?= e($ad['biz_category']) ?></span>
                        <h3 style="font-size: 1.2rem; color: var(--primary-navy); margin: 0.25rem 0 0.5rem 0;">
                            <?= e($ad['title']) ?>
                        </h3>
                        <p style="font-size: 0.92rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 1rem;">
                            <?= nl2br(e($ad['description'])) ?>
                        </p>
                    </div>

                    <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="font-size: 0.9rem; color: var(--primary-navy);"><?= e($ad['business_name']) ?></strong>
                            <div style="font-size: 0.8rem; color: var(--text-muted);"><?= e($ad['biz_phone']) ?></div>
                        </div>

                        <?php if (!empty($ad['target_url'])): ?>
                            <a href="<?= url('api/track_click.php?ad_id=' . (int)$ad['id']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm" style="color: #047857; border-color: #059669;">
                                Visit Business &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
