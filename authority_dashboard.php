<?php
/**
 * CivicLens — Authority Official Dashboard
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';

require_auth('authority');

$authority_id = current_authority_id();
$authority = get_current_authority();
$has_active_sub = is_authority_subscription_active($authority_id);

$db = get_db();

// Filter parameters
$status_filter = sanitize_input($_GET['status'] ?? '');
$priority_filter = sanitize_input($_GET['priority'] ?? '');
$search_query = sanitize_input($_GET['q'] ?? '');

// Metrics query
$metrics_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_assigned,
        SUM(CASE WHEN status = 'Opened' THEN 1 ELSE 0 END) as opened_count,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_count,
        SUM(CASE WHEN status = 'Solved' THEN 1 ELSE 0 END) as solved_count,
        SUM(CASE WHEN status = 'Reopened' THEN 1 ELSE 0 END) as reopened_count,
        SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed_count
    FROM complaints 
    WHERE assigned_authority_id = ?
");
$metrics_stmt->execute([$authority_id]);
$metrics = $metrics_stmt->fetch();

// Build complaints query
$query = "
    SELECT c.*, cit.name as citizen_name, cit.phone as citizen_phone
    FROM complaints c
    LEFT JOIN citizens cit ON c.citizen_id = cit.id
    WHERE c.assigned_authority_id = ?
";
$params = [$authority_id];

if (!empty($status_filter)) {
    $query .= " AND c.status = ?";
    $params[] = $status_filter;
}

if (!empty($priority_filter)) {
    $query .= " AND c.priority = ?";
    $params[] = $priority_filter;
}

if (!empty($search_query)) {
    $query .= " AND (c.complaint_id LIKE ? OR c.title LIKE ? OR c.location LIKE ?)";
    $like = '%' . $search_query . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$query .= " ORDER BY (c.status = 'Reopened') DESC, (c.priority = 'Urgent') DESC, (c.priority = 'High') DESC, c.created_at DESC";

$cstmt = $db->prepare($query);
$cstmt->execute($params);
$assigned_complaints = $cstmt->fetchAll();

$page_title = 'Authority Dashboard — ' . $authority['department'];
require_once __DIR__ . '/includes/header.php';
?>

<div style="margin: 1.5rem 0;">
    <!-- Welcome Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.85rem; color: var(--primary-navy); margin-bottom: 0.25rem;">
                🏛️ <?= e($authority['department']) ?>
            </h2>
            <p style="color: var(--text-secondary); font-size: 0.95rem;">
                Official: <strong><?= e($authority['official_name']) ?></strong> (<?= e($authority['designation']) ?>) &bull; Jurisdiction: <?= e($authority['jurisdiction']) ?>
            </p>
        </div>

        <div>
            <a href="<?= url('authority_subscription.php') ?>" class="btn <?= $has_active_sub ? 'btn-outline' : 'btn-danger' ?> btn-sm">
                <?= $has_active_sub ? '✓ Subscription Active (₹5,000/yr)' : '⚠️ Renew Subscription' ?>
            </a>
        </div>
    </div>

    <!-- Alert for reopened cases requiring prompt attention -->
    <?php if ($metrics['reopened_count'] > 0): ?>
        <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
            <span>⚡ <strong>Attention Required:</strong> You have <?= (int)$metrics['reopened_count'] ?> reopened complaint(s) where citizens indicated dissatisfaction with previous actions.</span>
            <a href="?status=Reopened" style="font-weight: 700; text-decoration: underline; margin-left: 0.5rem;">View Reopened Cases</a>
        </div>
    <?php endif; ?>

    <!-- Metrics Cards -->
    <div class="stat-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <span class="stat-label">Assigned Complaints</span>
            <div class="stat-value"><?= (int)($metrics['total_assigned'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">In Progress</span>
            <div class="stat-value" style="color: #2563eb;"><?= (int)($metrics['in_progress_count'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Reopened Cases</span>
            <div class="stat-value" style="color: var(--danger);"><?= (int)($metrics['reopened_count'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Resolved / Solved</span>
            <div class="stat-value" style="color: #059669;"><?= (int)($metrics['solved_count'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Closed</span>
            <div class="stat-value" style="color: var(--text-muted);"><?= (int)($metrics['closed_count'] ?? 0) ?></div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
        <form method="GET" action="authority_dashboard.php" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 2; min-width: 220px;">
                <label class="form-label" style="font-size: 0.85rem;">Search</label>
                <input type="text" name="q" class="form-control" placeholder="Search by ID, title, landmark..." value="<?= e($search_query) ?>">
            </div>

            <div style="flex: 1; min-width: 140px;">
                <label class="form-label" style="font-size: 0.85rem;">Status</label>
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Opened" <?= ($status_filter === 'Opened') ? 'selected' : '' ?>>Opened</option>
                    <option value="In Progress" <?= ($status_filter === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                    <option value="Reopened" <?= ($status_filter === 'Reopened') ? 'selected' : '' ?>>Reopened</option>
                    <option value="Solved" <?= ($status_filter === 'Solved') ? 'selected' : '' ?>>Solved</option>
                    <option value="Closed" <?= ($status_filter === 'Closed') ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>

            <div style="flex: 1; min-width: 130px;">
                <label class="form-label" style="font-size: 0.85rem;">Priority</label>
                <select name="priority" class="form-control">
                    <option value="">All Priorities</option>
                    <option value="Urgent" <?= ($priority_filter === 'Urgent') ? 'selected' : '' ?>>Urgent</option>
                    <option value="High" <?= ($priority_filter === 'High') ? 'selected' : '' ?>>High</option>
                    <option value="Medium" <?= ($priority_filter === 'Medium') ? 'selected' : '' ?>>Medium</option>
                    <option value="Low" <?= ($priority_filter === 'Low') ? 'selected' : '' ?>>Low</option>
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="authority_dashboard.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Assigned Complaints Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="card-header" style="padding: 1.25rem 1.5rem;">
            <h3 class="card-title">Assigned Municipal Issues (<?= count($assigned_complaints) ?>)</h3>
        </div>

        <?php if (empty($assigned_complaints)): ?>
            <div style="text-align: center; padding: 3rem 1.5rem; color: var(--text-secondary);">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📋</div>
                <p>No complaints match the current filter criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Complaint ID</th>
                            <th>Category</th>
                            <th>Title & Location</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned / Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assigned_complaints as $c): ?>
                            <tr style="<?= ($c['status'] === 'Reopened') ? 'background: #fff1f2;' : '' ?>">
                                <td>
                                    <strong style="font-family: monospace; color: var(--navy-blue);">
                                        <?= e($c['complaint_id']) ?>
                                    </strong>
                                    <?php if ($c['is_anonymous']): ?>
                                        <span class="badge badge-warning" style="font-size: 0.65rem; display: block; margin-top: 3px;">Anonymous (₹9)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size: 0.88rem; font-weight: 600; color: var(--primary-navy);">
                                        <?= e($c['category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-primary); max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= e($c['title']) ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                                        📍 <?= e($c['location']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= ($c['priority'] === 'Urgent' || $c['priority'] === 'High') ? 'badge-danger' : 'badge-secondary' ?>" style="font-size: 0.75rem;">
                                        <?= e($c['priority']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= render_status_badge($c['status']) ?>
                                </td>
                                <td style="font-size: 0.82rem; color: var(--text-secondary);">
                                    <?= format_date($c['created_at'], 'M d, Y') ?>
                                </td>
                                <td>
                                    <a href="complaint_details.php?id=<?= urlencode($c['complaint_id']) ?>" class="btn btn-outline btn-sm">
                                        Take Action &rarr;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
