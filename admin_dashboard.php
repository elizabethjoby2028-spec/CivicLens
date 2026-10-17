<?php
/**
 * CivicLens — Comprehensive Administrator Dashboard
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/NotificationService.php';

require_auth('admin');

$db = get_db();
$admin = get_current_admin();

// Handle Actions (Authority creation, status toggle, business verification)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = sanitize_input($_POST['action'] ?? '');

    // Action: Add Authority Account
    if ($action === 'create_authority') {
        $department   = sanitize_input($_POST['department'] ?? '');
        $official_name = sanitize_input($_POST['official_name'] ?? '');
        $designation  = sanitize_input($_POST['designation'] ?? '');
        $email        = sanitize_input($_POST['email'] ?? '');
        $phone        = sanitize_input($_POST['phone'] ?? '');
        $jurisdiction = sanitize_input($_POST['jurisdiction'] ?? '');
        $password     = $_POST['password'] ?? '';

        if (empty($department) || empty($official_name) || empty($email) || empty($password)) {
            flash('error', 'Please fill in all required fields for the new authority account.');
        } else {
            $chk = $db->prepare("SELECT COUNT(*) FROM authorities WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetchColumn() > 0) {
                flash('error', 'An authority with this email already exists.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $db->prepare("
                    INSERT INTO authorities (department, official_name, designation, email, phone, password_hash, jurisdiction, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $ins->execute([$department, $official_name, $designation, $email, $phone, $hash, $jurisdiction]);
                flash('success', "Authority account for {$department} ({$official_name}) created successfully.");
            }
        }
        redirect('admin_dashboard.php#authorities');
    }

    // Action: Toggle Authority Active Status
    if ($action === 'toggle_authority_status') {
        $auth_id = (int)($_POST['authority_id'] ?? 0);
        $new_val = (int)($_POST['is_active'] ?? 0);
        $stmt = $db->prepare("UPDATE authorities SET is_active = ? WHERE id = ?");
        $stmt->execute([$new_val, $auth_id]);
        flash('success', 'Authority status updated.');
        redirect('admin_dashboard.php#authorities');
    }

    // Action: Grant / Verify Authority Subscription manually
    if ($action === 'grant_authority_subscription') {
        $auth_id = (int)($_POST['authority_id'] ?? 0);
        $days = (int)($_POST['days'] ?? 365);
        $sub = $db->prepare("
            INSERT INTO authority_subscriptions (authority_id, plan_name, amount, payment_status, payment_method, transaction_reference, start_date, end_date, verified_by_admin, admin_notes)
            VALUES (?, 'Admin Granted Official Access', 5000.00, 'active', 'Admin Exemption/Verification', 'MANUAL-ADMIN', NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 1, 'Manually verified by admin')
        ");
        $sub->execute([$auth_id, $days]);
        flash('success', "Active official subscription granted to authority for {$days} days.");
        redirect('admin_dashboard.php#authorities');
    }

    // Action: Toggle Business Verification
    if ($action === 'toggle_business_verification') {
        $biz_id = (int)($_POST['business_id'] ?? 0);
        $verified = (int)($_POST['is_verified'] ?? 0);
        $stmt = $db->prepare("UPDATE businesses SET is_verified = ? WHERE id = ?");
        $stmt->execute([$verified, $biz_id]);
        flash('success', 'Business verification status updated.');
        redirect('admin_dashboard.php#businesses');
    }
}

// Category Scope Check
$admin_category = $admin['category'] ?? null;
$is_superadmin = ($admin['role'] === 'superadmin' || empty($admin_category));

// Fetch Statistics (Global or Category-scoped)
if ($admin_category) {
    $stats_stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_complaints,
            SUM(CASE WHEN status = 'Opened' THEN 1 ELSE 0 END) as opened,
            SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'Solved' THEN 1 ELSE 0 END) as solved,
            SUM(CASE WHEN status = 'Reopened' THEN 1 ELSE 0 END) as reopened,
            SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed,
            SUM(CASE WHEN is_anonymous = 1 THEN 1 ELSE 0 END) as anonymous_count
        FROM complaints
        WHERE category = ?
    ");
    $stats_stmt->execute([$admin_category]);
} else {
    $stats_stmt = $db->query("
        SELECT 
            COUNT(*) as total_complaints,
            SUM(CASE WHEN status = 'Opened' THEN 1 ELSE 0 END) as opened,
            SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'Solved' THEN 1 ELSE 0 END) as solved,
            SUM(CASE WHEN status = 'Reopened' THEN 1 ELSE 0 END) as reopened,
            SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed,
            SUM(CASE WHEN is_anonymous = 1 THEN 1 ELSE 0 END) as anonymous_count
        FROM complaints
    ");
}
$stats = $stats_stmt->fetch();

// Total Revenue from Payment Orders
$rev_stmt = $db->query("
    SELECT 
        COALESCE(SUM(amount), 0) as total_revenue,
        COUNT(*) as paid_transactions
    FROM payment_orders 
    WHERE status = 'paid'
");
$revenue = $rev_stmt->fetch();

// Reopened complaints requiring administrative review
if ($admin_category) {
    $reopened_stmt = $db->prepare("
        SELECT c.*, a.department as authority_dept, a.official_name as authority_official
        FROM complaints c
        LEFT JOIN authorities a ON c.assigned_authority_id = a.id
        WHERE c.status = 'Reopened' AND c.category = ?
        ORDER BY c.updated_at DESC
        LIMIT 10
    ");
    $reopened_stmt->execute([$admin_category]);
} else {
    $reopened_stmt = $db->query("
        SELECT c.*, a.department as authority_dept, a.official_name as authority_official
        FROM complaints c
        LEFT JOIN authorities a ON c.assigned_authority_id = a.id
        WHERE c.status = 'Reopened'
        ORDER BY c.updated_at DESC
        LIMIT 10
    ");
}
$reopened_complaints = $reopened_stmt->fetchAll();

// Complaints Filtering & Search
$category_filter = sanitize_input($_GET['category'] ?? '');
$status_filter   = sanitize_input($_GET['status'] ?? '');
$priority_filter = sanitize_input($_GET['priority'] ?? '');
$search_query    = sanitize_input($_GET['q'] ?? '');

$complaints_query = "
    SELECT c.*, cit.name as citizen_name, a.department as authority_dept
    FROM complaints c
    LEFT JOIN citizens cit ON c.citizen_id = cit.id
    LEFT JOIN authorities a ON c.assigned_authority_id = a.id
    WHERE 1=1
";
$params = [];

if ($admin_category) {
    $complaints_query .= " AND c.category = ?";
    $params[] = $admin_category;
} elseif (!empty($category_filter)) {
    $complaints_query .= " AND c.category = ?";
    $params[] = $category_filter;
}
if (!empty($status_filter)) {
    $complaints_query .= " AND c.status = ?";
    $params[] = $status_filter;
}
if (!empty($priority_filter)) {
    $complaints_query .= " AND c.priority = ?";
    $params[] = $priority_filter;
}
if (!empty($search_query)) {
    $complaints_query .= " AND (c.complaint_id LIKE ? OR c.title LIKE ? OR c.location LIKE ?)";
    $like = '%' . $search_query . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$complaints_query .= " ORDER BY c.created_at DESC LIMIT 50";
$cstmt = $db->prepare($complaints_query);
$cstmt->execute($params);
$complaints_list = $cstmt->fetchAll();

// Authorities List with Subscription Status
$auth_list_stmt = $db->query("
    SELECT a.*, 
           sub.payment_status as sub_status,
           sub.end_date as sub_end_date,
           (SELECT COUNT(*) FROM complaints WHERE assigned_authority_id = a.id) as assigned_count
    FROM authorities a
    LEFT JOIN authority_subscriptions sub ON sub.id = (
        SELECT id FROM authority_subscriptions WHERE authority_id = a.id ORDER BY created_at DESC LIMIT 1
    )
    ORDER BY a.department ASC
");
$authorities = $auth_list_stmt->fetchAll();

// Businesses List
$biz_stmt = $db->query("
    SELECT b.*,
           (SELECT COUNT(*) FROM advertising_campaigns WHERE business_id = b.id) as campaign_count
    FROM businesses b
    ORDER BY b.created_at DESC
");
$businesses = $biz_stmt->fetchAll();

$page_title = 'Administration Dashboard — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="margin: 1.5rem 0;">
    <!-- Admin Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <?php if ($admin_category): ?>
                <span class="badge badge-primary" style="margin-bottom: 0.35rem; font-size: 0.82rem; background: var(--primary-light); color: #fff;">
                    🏷️ <?= e($admin_category) ?> Department Portal
                </span>
                <h2 style="font-size: 1.85rem; color: var(--primary-navy); margin-bottom: 0.25rem;">
                    🛡️ <?= e($admin_category) ?> Works & Issue Resolution
                </h2>
                <p style="color: var(--text-secondary); font-size: 0.95rem;">
                    Admin: <strong><?= e($admin['name']) ?></strong> &bull; Tracking & Resolving All <strong><?= e($admin_category) ?></strong> Complaints
                </p>
            <?php else: ?>
                <h2 style="font-size: 1.85rem; color: var(--primary-navy); margin-bottom: 0.25rem;">
                    🛡️ CivicLens Central Administration Suite
                </h2>
                <p style="color: var(--text-secondary); font-size: 0.95rem;">
                    Welcome, <strong><?= e($admin['name']) ?></strong> (Superadmin) &bull; City-Wide Municipal Oversight (All Categories)
                </p>
            <?php endif; ?>
        </div>

        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="<?= url('admin_ads.php') ?>" class="btn btn-outline btn-sm">
                💼 Moderation & Ads
            </a>
        </div>
    </div>

    <!-- High-level Metric Cards -->
    <div class="stat-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <span class="stat-label">Total Complaints</span>
            <div class="stat-value"><?= (int)$stats['total_complaints'] ?></div>
            <span style="font-size: 0.78rem; color: var(--text-muted);"><?= (int)$stats['anonymous_count'] ?> Paid Anonymous Reports</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Pending / Opened</span>
            <div class="stat-value" style="color: #eab308;"><?= (int)$stats['opened'] ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">In Progress</span>
            <div class="stat-value" style="color: #2563eb;"><?= (int)$stats['in_progress'] ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Reopened Cases</span>
            <div class="stat-value" style="color: var(--danger);"><?= (int)$stats['reopened'] ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Resolved & Closed</span>
            <div class="stat-value" style="color: #059669;"><?= (int)($stats['solved'] + $stats['closed']) ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Verified Revenue</span>
            <div class="stat-value" style="color: #0d9488; font-size: 1.45rem;">
                ₹<?= number_format($revenue['total_revenue'], 2) ?>
            </div>
            <span style="font-size: 0.78rem; color: var(--text-muted);"><?= (int)$revenue['paid_transactions'] ?> Transactions</span>
        </div>
    </div>

    <!-- Reopened Complaints Section -->
    <?php if (!empty($reopened_complaints)): ?>
        <div class="card" style="border: 2px solid #fecdd3; background: #fff1f2; margin-bottom: 2rem;">
            <div class="card-header" style="border-bottom-color: #fecdd3;">
                <div>
                    <span class="badge badge-danger" style="margin-bottom: 0.25rem;">Immediate Attention</span>
                    <h3 style="color: #9f1239; font-size: 1.25rem;">Reopened Complaints by Citizens (<?= count($reopened_complaints) ?>)</h3>
                </div>
            </div>
            <p style="font-size: 0.9rem; color: #881337; margin-bottom: 1rem;">
                Citizens reported dissatisfaction with official resolution. Inspect notes and review department responsiveness:
            </p>
            <div class="table-responsive">
                <table class="table" style="background: #fff; border-radius: var(--radius-sm); margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Complaint ID</th>
                            <th>Category</th>
                            <th>Title</th>
                            <th>Assigned Authority</th>
                            <th>Complainant Dissatisfaction Reason</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reopened_complaints as $rc): ?>
                            <tr>
                                <td style="font-family: monospace; font-weight: 700; color: var(--navy-blue);"><?= e($rc['complaint_id']) ?></td>
                                <td><?= e($rc['category']) ?></td>
                                <td><strong><?= e($rc['title']) ?></strong></td>
                                <td><?= !empty($rc['authority_dept']) ? e($rc['authority_dept']) : '<span style="color:var(--danger);">Unassigned</span>' ?></td>
                                <td style="color: var(--danger); font-size: 0.88rem;">
                                    "<?= e($rc['feedback_text'] ?? 'No written comment') ?>"
                                </td>
                                <td>
                                    <a href="complaint_details.php?id=<?= urlencode($rc['complaint_id']) ?>" class="btn btn-outline btn-sm">
                                        Review & Reassign &rarr;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Master Complaints Management -->
    <div class="card" style="margin-bottom: 2.5rem; padding: 0; overflow: hidden;">
        <div class="card-header" style="padding: 1.25rem 1.5rem;">
            <h3 class="card-title">
                <?= $admin_category ? '🏷️ ' . e($admin_category) . ' Complaints & Issue Resolution (' . count($complaints_list) . ')' : 'All Civic Complaints (' . count($complaints_list) . ')' ?>
            </h3>
        </div>

        <!-- Filter Bar -->
        <div style="padding: 1.25rem 1.5rem; background: var(--bg-surface-alt); border-bottom: 1px solid var(--border-color);">
            <form method="GET" action="admin_dashboard.php" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 2; min-width: 200px;">
                    <label class="form-label" style="font-size: 0.82rem;">Search Query</label>
                    <input type="text" name="q" class="form-control" placeholder="ID, Title, Location..." value="<?= e($search_query) ?>">
                </div>

                <?php if (!$admin_category): ?>
                    <div style="flex: 1; min-width: 140px;">
                        <label class="form-label" style="font-size: 0.82rem;">Category</label>
                        <select name="category" class="form-control">
                            <option value="">All Categories</option>
                            <option value="Water" <?= ($category_filter === 'Water') ? 'selected' : '' ?>>Water</option>
                            <option value="Road" <?= ($category_filter === 'Road') ? 'selected' : '' ?>>Road</option>
                            <option value="Food" <?= ($category_filter === 'Food') ? 'selected' : '' ?>>Food</option>
                            <option value="Waste" <?= ($category_filter === 'Waste') ? 'selected' : '' ?>>Waste</option>
                            <option value="Drugs / Substance Abuse (Thoofan)" <?= ($category_filter === 'Drugs / Substance Abuse (Thoofan)') ? 'selected' : '' ?>>Drugs (Thoofan)</option>
                            <option value="Other Issues" <?= ($category_filter === 'Other Issues') ? 'selected' : '' ?>>Other Issues</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div style="flex: 1; min-width: 120px;">
                    <label class="form-label" style="font-size: 0.82rem;">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="Opened" <?= ($status_filter === 'Opened') ? 'selected' : '' ?>>Opened</option>
                        <option value="In Progress" <?= ($status_filter === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                        <option value="Solved" <?= ($status_filter === 'Solved') ? 'selected' : '' ?>>Solved</option>
                        <option value="Reopened" <?= ($status_filter === 'Reopened') ? 'selected' : '' ?>>Reopened</option>
                        <option value="Closed" <?= ($status_filter === 'Closed') ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>

                <div style="flex: 1; min-width: 120px;">
                    <label class="form-label" style="font-size: 0.82rem;">Priority</label>
                    <select name="priority" class="form-control">
                        <option value="">All Priorities</option>
                        <option value="Urgent" <?= ($priority_filter === 'Urgent') ? 'selected' : '' ?>>Urgent</option>
                        <option value="High" <?= ($priority_filter === 'High') ? 'selected' : '' ?>>High</option>
                        <option value="Medium" <?= ($priority_filter === 'Medium') ? 'selected' : '' ?>>Medium</option>
                        <option value="Low" <?= ($priority_filter === 'Low') ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="admin_dashboard.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <?php if (empty($complaints_list)): ?>
            <div style="text-align: center; padding: 3rem 1.5rem; color: var(--text-secondary);">
                <p>No complaints found matching the criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>ID & Category</th>
                            <th>Issue Title & Location</th>
                            <th>Citizen</th>
                            <th>Status</th>
                            <th>Assigned Authority</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints_list as $c): ?>
                            <tr>
                                <td>
                                    <strong style="font-family: monospace; color: var(--navy-blue);"><?= e($c['complaint_id']) ?></strong>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?= e($c['category']) ?></div>
                                    <?php if ($c['is_anonymous']): ?>
                                        <span class="badge badge-warning" style="font-size: 0.65rem;">Anonymous (₹9)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: var(--text-primary);"><?= e($c['title']) ?></strong>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">📍 <?= e($c['location']) ?></div>
                                </td>
                                <td style="font-size: 0.85rem;">
                                    <?= $c['is_anonymous'] ? '<em>Anonymous</em>' : (!empty($c['citizen_name']) ? e($c['citizen_name']) : 'User #' . $c['citizen_id']) ?>
                                </td>
                                <td><?= render_status_badge($c['status']) ?></td>
                                <td>
                                    <?php if (!empty($c['authority_dept'])): ?>
                                        <span style="font-weight: 600; color: var(--navy-light); font-size: 0.85rem;">
                                            🏛️ <?= e($c['authority_dept']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--danger); font-size: 0.82rem; font-weight: 600;">
                                            ⚠️ Unassigned
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.82rem; color: var(--text-secondary);">
                                    <?= format_date($c['created_at'], 'M d, Y') ?>
                                </td>
                                <td>
                                    <a href="complaint_details.php?id=<?= urlencode($c['complaint_id']) ?>" class="btn btn-primary btn-sm">
                                        Track & Solve &rarr;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Authority Accounts Management Section -->
    <div id="authorities" class="card" style="margin-bottom: 2.5rem;">
        <div class="card-header">
            <div>
                <h3 class="card-title">🏛️ Municipal Authorities & Subscriptions (₹5,000/yr)</h3>
                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.2rem;">
                    Manage certified department representatives, official permissions, and subscription statuses.
                </p>
            </div>
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('newAuthModal').style.display='block'">
                + Register New Authority
            </button>
        </div>

        <div class="table-responsive">
            <table class="table" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Official Name & Designation</th>
                        <th>Contact</th>
                        <th>Jurisdiction</th>
                        <th>Assigned</th>
                        <th>Account Status</th>
                        <th>Subscription (₹5,000)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($authorities as $a): ?>
                        <?php 
                        $sub_is_valid = ($a['sub_status'] === 'active' && (empty($a['sub_end_date']) || strtotime($a['sub_end_date']) >= time()));
                        ?>
                        <tr>
                            <td><strong><?= e($a['department']) ?></strong></td>
                            <td><?= e($a['official_name']) ?><br><span style="font-size: 0.78rem; color: var(--text-muted);"><?= e($a['designation']) ?></span></td>
                            <td style="font-size: 0.82rem;"><?= e($a['email']) ?><br><?= e($a['phone']) ?></td>
                            <td style="font-size: 0.82rem;"><?= e($a['jurisdiction']) ?></td>
                            <td><span class="badge badge-secondary"><?= (int)$a['assigned_count'] ?></span></td>
                            <td>
                                <span class="badge <?= $a['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $a['is_active'] ? 'Active' : 'Deactivated' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($sub_is_valid): ?>
                                    <span class="badge badge-success">✓ Active (Exp: <?= date('M Y', strtotime($a['sub_end_date'])) ?>)</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Expired / Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.35rem;">
                                    <form method="POST" action="admin_dashboard.php" style="margin: 0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_authority_status">
                                        <input type="hidden" name="authority_id" value="<?= (int)$a['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= $a['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" style="font-size: 0.75rem;">
                                            <?= $a['is_active'] ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>

                                    <?php if (!$sub_is_valid): ?>
                                        <form method="POST" action="admin_dashboard.php" style="margin: 0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="grant_authority_subscription">
                                            <input type="hidden" name="authority_id" value="<?= (int)$a['id'] ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" style="font-size: 0.75rem;" title="Manually verify official subscription">
                                                Verify Sub
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
    </div>

    <!-- Local Businesses Verification Section -->
    <div id="businesses" class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">💼 Local Businesses & Verification</h3>
                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.2rem;">
                    Local businesses wishing to run targeted civic advertising campaigns must be verified by administrators.
                </p>
            </div>
            <a href="<?= url('admin_ads.php') ?>" class="btn btn-outline btn-sm">
                Manage Ad Campaigns &rarr;
            </a>
        </div>

        <?php if (empty($businesses)): ?>
            <div style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                No businesses registered yet.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Business Name</th>
                            <th>Owner & Contact</th>
                            <th>Category</th>
                            <th>City & Address</th>
                            <th>Campaigns</th>
                            <th>Verification Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($businesses as $b): ?>
                            <tr>
                                <td><strong><?= e($b['business_name']) ?></strong></td>
                                <td style="font-size: 0.85rem;">
                                    <?= e($b['owner_name']) ?><br>
                                    <span style="color: var(--text-muted);"><?= e($b['email']) ?> &bull; <?= e($b['phone']) ?></span>
                                </td>
                                <td><?= e($b['category']) ?></td>
                                <td style="font-size: 0.85rem;"><?= e($b['city']) ?><br><span style="color:var(--text-muted);"><?= e($b['address']) ?></span></td>
                                <td><span class="badge badge-secondary"><?= (int)$b['campaign_count'] ?></span></td>
                                <td>
                                    <span class="badge <?= $b['is_verified'] ? 'badge-success' : 'badge-warning' ?>">
                                        <?= $b['is_verified'] ? '✓ Verified' : 'Pending Verification' ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" action="admin_dashboard.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_business_verification">
                                        <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
                                        <input type="hidden" name="is_verified" value="<?= $b['is_verified'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn <?= $b['is_verified'] ? 'btn-secondary' : 'btn-primary' ?> btn-sm">
                                            <?= $b['is_verified'] ? 'Revoke Verification' : 'Verify Business' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: New Authority Account -->
<div id="newAuthModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; overflow-y: auto; padding: 2rem 1rem;">
    <div class="card" style="max-width: 600px; margin: 2rem auto; position: relative;">
        <div class="card-header">
            <h3 class="card-title">Register Official Authority Account</h3>
            <button onclick="document.getElementById('newAuthModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="admin_dashboard.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_authority">

            <div class="form-group">
                <label class="form-label">Department / Agency Name <span class="required">*</span></label>
                <input type="text" name="department" class="form-control" placeholder="e.g. Kerala Water Authority (KWA)" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Official Name <span class="required">*</span></label>
                    <input type="text" name="official_name" class="form-control" placeholder="Officer Name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Designation <span class="required">*</span></label>
                    <input type="text" name="designation" class="form-control" placeholder="Executive Engineer / Inspector" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Official Email <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="officer@department.gov" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Phone <span class="required">*</span></label>
                    <input type="tel" name="phone" class="form-control" placeholder="+91 9876543210" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Jurisdiction / Zone <span class="required">*</span></label>
                <input type="text" name="jurisdiction" class="form-control" placeholder="e.g. Central District / Ward 14-22" required>
            </div>

            <div class="form-group">
                <label class="form-label">Initial Password <span class="required">*</span></label>
                <input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" required>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('newAuthModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Authority Account &rarr;</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
