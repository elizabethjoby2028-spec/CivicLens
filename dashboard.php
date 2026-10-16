<?php
/**
 * CivicLens — Citizen Dashboard
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/NotificationService.php';

// Enforce citizen authentication
require_auth('citizen');

$citizen_id = current_citizen_id();
$citizen = get_current_citizen();
$db = get_db();

// Fetch summary metrics for this citizen
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('Opened', 'In Progress', 'Reopened') THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN status = 'Solved' AND satisfaction_status = 'Pending' AND (feedback_deadline IS NULL OR feedback_deadline >= NOW()) THEN 1 ELSE 0 END) as pending_feedback_count,
        SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed_count
    FROM complaints
    WHERE citizen_id = ?
");
$stmt->execute([$citizen_id]);
$stats = $stmt->fetch();

// Fetch citizen's complaints (ONLY their own complaints!)
$stmt = $db->prepare("
    SELECT c.*, a.department as authority_dept, a.official_name as authority_official
    FROM complaints c
    LEFT JOIN authorities a ON c.assigned_authority_id = a.id
    WHERE c.citizen_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$citizen_id]);
$complaints = $stmt->fetchAll();

// Fetch unread notifications
$unread_notifications = NotificationService::getUnread('citizen', $citizen_id, 3);

$page_title = __('dashboard') . ' — ' . __('citizen_portal');
require_once __DIR__ . '/includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;">
    <div>
        <h2 style="font-size: 1.85rem; color: var(--primary-navy);"><?= e(__('dashboard')) ?></h2>
        <p style="color: var(--text-secondary);">Welcome, <strong><?= e($citizen['name']) ?></strong> (<?= e($citizen['email']) ?>)</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="<?= url('report_issue.php') ?>" class="btn btn-primary">
            + <?= e(__('report_issue')) ?>
        </a>
        <a href="<?= url('track_complaint.php') ?>" class="btn btn-secondary">
            🔍 <?= e(__('track_complaint')) ?>
        </a>
    </div>
</div>

<!-- Pending Feedback Notice (10-Day Window) -->
<?php if (!empty($stats['pending_feedback_count']) && $stats['pending_feedback_count'] > 0): ?>
    <div class="alert alert-warning" style="align-items: flex-start;">
        <div>
            <strong>Action Required: Satisfaction Feedback Window Open</strong>
            <p style="font-size: 0.9rem; margin-top: 0.25rem;">
                You have <?= (int)$stats['pending_feedback_count'] ?> issue(s) marked as <strong>Solved</strong> by the authority. Please inspect the resolution and confirm if you are satisfied or wish to reopen within the 10-day feedback period.
            </p>
        </div>
    </div>
<?php endif; ?>

<!-- Metrics Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: #eff6ff; color: var(--navy-blue);">📋</div>
        <div>
            <div class="stat-val"><?= (int)($stats['total'] ?? 0) ?></div>
            <div class="stat-label">Total Complaints Filed</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: #fef3c7; color: #b45309;">⏳</div>
        <div>
            <div class="stat-val"><?= (int)($stats['active_count'] ?? 0) ?></div>
            <div class="stat-label">Active / In Progress</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: #ecfdf5; color: #047857;">💬</div>
        <div>
            <div class="stat-val"><?= (int)($stats['pending_feedback_count'] ?? 0) ?></div>
            <div class="stat-label">Needs Your Feedback</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: #f1f5f9; color: #475569;">✅</div>
        <div>
            <div class="stat-val"><?= (int)($stats['closed_count'] ?? 0) ?></div>
            <div class="stat-label">Resolved & Closed</div>
        </div>
    </div>
</div>

<!-- My Complaints Section -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= e(__('my_complaints')) ?></h3>
        <span style="font-size: 0.85rem; color: var(--text-muted);"><?= count($complaints) ?> record(s)</span>
    </div>

    <?php if (empty($complaints)): ?>
        <div style="text-align: center; padding: 3rem 1rem;">
            <div style="font-size: 3rem; margin-bottom: 0.75rem;">🌿</div>
            <h4>No complaints submitted yet</h4>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem;">Have you noticed a civic issue in your locality? Report roads, water, waste, or public safety concerns directly to officials.</p>
            <a href="<?= url('report_issue.php') ?>" class="btn btn-primary">+ <?= e(__('report_issue')) ?></a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Complaint ID</th>
                        <th><?= e(__('category')) ?></th>
                        <th><?= e(__('title')) ?></th>
                        <th><?= e(__('location')) ?></th>
                        <th><?= e(__('date_submitted')) ?></th>
                        <th><?= e(__('status')) ?></th>
                        <th><?= e(__('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($complaints as $c): ?>
                        <tr>
                            <td>
                                <strong style="font-family: monospace; font-size: 0.95rem; color: var(--navy-blue);">
                                    <?= e($c['complaint_id']) ?>
                                </strong>
                            </td>
                            <td><?= e($c['category']) ?></td>
                            <td>
                                <strong><?= e($c['title']) ?></strong>
                            </td>
                            <td><?= e($c['location']) ?></td>
                            <td><?= format_date($c['created_at'], 'd M Y, h:i A') ?></td>
                            <td>
                                <?= render_status_badge($c['status']) ?>
                                <?php if ($c['status'] === 'Solved' && $c['satisfaction_status'] === 'Pending'): ?>
                                    <div style="font-size: 0.75rem; color: #b45309; font-weight: 700; margin-top: 4px;">
                                        Feedback Window Open
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.4rem;">
                                    <a href="<?= url('complaint_details.php?id=' . urlencode($c['complaint_id'])) ?>" class="btn btn-sm btn-outline">
                                        <?= e(__('view_details')) ?>
                                    </a>
                                    <?php if ($c['status'] === 'Solved' && $c['satisfaction_status'] === 'Pending'): ?>
                                        <a href="<?= url('feedback.php?id=' . urlencode($c['complaint_id'])) ?>" class="btn btn-sm btn-success">
                                            Feedback
                                        </a>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
