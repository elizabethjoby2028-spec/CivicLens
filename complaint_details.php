<?php
/**
 * CivicLens — Complaint Details (Role-Based Access)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';

$complaint_id_str = sanitize_input($_GET['id'] ?? '');

if (empty($complaint_id_str)) {
    flash('error', 'Complaint ID missing.');
    redirect('index.php');
}

$db = get_db();
$stmt = $db->prepare("
    SELECT c.*, 
           cit.name as citizen_name, cit.email as citizen_email, cit.phone as citizen_phone,
           a.department as authority_dept, a.official_name as authority_official, a.id as authority_id
    FROM complaints c
    LEFT JOIN citizens cit ON c.citizen_id = cit.id
    LEFT JOIN authorities a ON c.assigned_authority_id = a.id
    WHERE c.complaint_id = ?
");
$stmt->execute([$complaint_id_str]);
$complaint = $stmt->fetch();

if (!$complaint) {
    flash('error', 'Complaint not found.');
    redirect('index.php');
}

// Access Control
$is_admin = is_admin_logged_in();
$is_authority = (is_authority_logged_in() && $complaint['assigned_authority_id'] == current_authority_id());
$is_owner_citizen = (is_citizen_logged_in() && $complaint['citizen_id'] == current_citizen_id());

if (!$is_admin && !$is_authority && !$is_owner_citizen) {
    // If anonymous, forward to track_complaint with token requirement
    if ($complaint['is_anonymous']) {
        redirect('track_complaint.php?id=' . urlencode($complaint_id_str));
    }
    flash('error', 'You are not authorized to view this complaint.');
    redirect('login.php');
}

// Fetch status history
$hstmt = $db->prepare("SELECT * FROM complaint_status_history WHERE complaint_id = ? ORDER BY created_at ASC");
$hstmt->execute([$complaint['id']]);
$history = $hstmt->fetchAll();

// List of all authorities for assignment (Admin only)
$authorities = [];
if ($is_admin) {
    $auth_stmt = $db->query("SELECT id, department, official_name FROM authorities WHERE is_active = 1 ORDER BY department ASC");
    $authorities = $auth_stmt->fetchAll();
}

$page_title = 'Complaint ' . $complaint['complaint_id'] . ' — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 900px; margin: 1rem auto;">
    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <?php if ($is_admin): ?>
                <a href="<?= url('admin_dashboard.php') ?>" style="font-size: 0.9rem; color: var(--text-muted);">&larr; Back to Admin Dashboard</a>
            <?php elseif ($is_authority): ?>
                <a href="<?= url('authority_dashboard.php') ?>" style="font-size: 0.9rem; color: var(--text-muted);">&larr; Back to Authority Dashboard</a>
            <?php else: ?>
                <a href="<?= url('dashboard.php') ?>" style="font-size: 0.9rem; color: var(--text-muted);">&larr; Back to Citizen Dashboard</a>
            <?php endif; ?>
        </div>
        <div>
            <?= render_status_badge($complaint['status']) ?>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Reference Code</span>
                <h3 style="font-size: 1.5rem; color: var(--navy-blue); font-family: monospace;"><?= e($complaint['complaint_id']) ?></h3>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 0.85rem; color: var(--text-muted);">Priority:</span>
                <strong style="color: <?= ($complaint['priority'] === 'Urgent' || $complaint['priority'] === 'High') ? 'var(--danger)' : 'var(--text-primary)' ?>;">
                    <?= e($complaint['priority']) ?>
                </strong>
            </div>
        </div>

        <!-- Details Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; background: var(--bg-surface-alt); padding: 1.25rem; border-radius: var(--radius-md);">
            <div>
                <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; display: block;">Category</span>
                <strong><?= e($complaint['category']) ?></strong>
            </div>
            <div>
                <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; display: block;">Location / Landmark</span>
                <strong><?= e($complaint['location']) ?></strong>
            </div>
            <div>
                <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; display: block;">Filing Type</span>
                <strong><?= $complaint['is_anonymous'] ? '🕵️ Anonymous (₹9 Paid)' : '👤 Verified Citizen' ?></strong>
            </div>
            <div>
                <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; display: block;">Submitted Date</span>
                <strong><?= format_date($complaint['created_at']) ?></strong>
            </div>
        </div>

        <?php if (!$complaint['is_anonymous'] && ($is_admin || $is_authority)): ?>
            <!-- Complainant Identity for Admins/Authorities -->
            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.5rem; font-size: 0.9rem;">
                <strong>Complainant:</strong> <?= e($complaint['citizen_name']) ?> &bull; 
                <strong>Email:</strong> <?= e($complaint['citizen_email']) ?> &bull; 
                <strong>Phone:</strong> <?= e($complaint['citizen_phone']) ?>
            </div>
        <?php endif; ?>

        <div style="margin-bottom: 1.5rem;">
            <h4 style="font-size: 1.25rem; margin-bottom: 0.5rem; color: var(--primary-navy);"><?= e($complaint['title']) ?></h4>
            <div style="line-height: 1.6; font-size: 0.95rem; color: var(--text-secondary); white-space: pre-line;">
                <?= e($complaint['description']) ?>
            </div>
        </div>

        <?php if (!empty($complaint['photo_path']) && file_exists(ROOT_PATH . '/' . $complaint['photo_path'])): ?>
            <div style="margin-bottom: 2rem;">
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 0.5rem;">Attached Evidence</span>
                <a href="<?= url($complaint['photo_path']) ?>" target="_blank">
                    <img src="<?= url($complaint['photo_path']) ?>" alt="Evidence" style="max-width: 100%; max-height: 380px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                </a>
            </div>
        <?php endif; ?>

        <!-- Resolution Details (if solved or closed) -->
        <?php if (!empty($complaint['resolution_note'])): ?>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem;">
                <div style="color: #065f46; font-weight: 700; margin-bottom: 0.4rem;">
                    Official Resolution Note (Resolved: <?= format_date($complaint['resolved_at']) ?>):
                </div>
                <div style="color: #064e3b; font-size: 0.95rem; line-height: 1.5;">
                    <?= nl2br(e($complaint['resolution_note'])) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Citizen Feedback Link if pending -->
        <?php if ($is_owner_citizen && $complaint['status'] === 'Solved' && $complaint['satisfaction_status'] === 'Pending'): ?>
            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <strong style="color: #92400e;">Satisfaction Feedback Window Open (10 Days)</strong>
                    <p style="font-size: 0.85rem; color: #b45309; margin-top: 0.2rem;">
                        Deadline: <?= format_date($complaint['feedback_deadline']) ?>. Let officials know if the issue is completely resolved.
                    </p>
                </div>
                <a href="<?= url('feedback.php?id=' . urlencode($complaint['complaint_id'])) ?>" class="btn btn-success">
                    Submit Satisfaction Feedback &rarr;
                </a>
            </div>
        <?php endif; ?>

        <!-- Admin Authority Assignment Box -->
        <?php if ($is_admin): ?>
            <div style="background: #f1f5f9; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--primary-navy);">🛡️ Administrator Controls</h4>
                <form method="POST" action="update_status.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; align-items: flex-end;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="complaint_id" value="<?= e($complaint['complaint_id']) ?>">
                    <input type="hidden" name="action_type" value="assign">

                    <div>
                        <label class="form-label">Assign To Authority Department</label>
                        <select name="authority_id" class="form-control" required>
                            <option value="">-- Select Authority --</option>
                            <?php foreach ($authorities as $a): ?>
                                <option value="<?= (int)$a['id'] ?>" <?= ($complaint['assigned_authority_id'] == $a['id']) ? 'selected' : '' ?>>
                                    <?= e($a['department']) ?> (<?= e($a['official_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Update Priority</label>
                        <select name="priority" class="form-control">
                            <option value="Low" <?= ($complaint['priority'] === 'Low') ? 'selected' : '' ?>>Low</option>
                            <option value="Medium" <?= ($complaint['priority'] === 'Medium') ? 'selected' : '' ?>>Medium</option>
                            <option value="High" <?= ($complaint['priority'] === 'High') ? 'selected' : '' ?>>High</option>
                            <option value="Urgent" <?= ($complaint['priority'] === 'Urgent') ? 'selected' : '' ?>>Urgent</option>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary btn-block">Assign Authority</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Status Update & Solve Box (Admin & Authority) -->
        <?php if (($is_admin || $is_authority) && $complaint['status'] !== 'Closed'): ?>
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--navy-blue);">
                    <?= $is_admin ? '🛡️ Administrative Action: Resolve / Update Status' : '🏛️ Authority Action: Update Status' ?>
                </h4>
                <form method="POST" action="update_status.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="complaint_id" value="<?= e($complaint['complaint_id']) ?>">
                    <input type="hidden" name="action_type" value="status_update">

                    <div class="form-group">
                        <label class="form-label">Select Status</label>
                        <select name="new_status" class="form-control" required>
                            <option value="In Progress" <?= ($complaint['status'] === 'In Progress') ? 'selected' : '' ?>>In Progress (Investigation Underway)</option>
                            <option value="Solved" <?= ($complaint['status'] === 'Solved') ? 'selected' : '' ?>>Solved (Work Completed - Starts 10-Day Feedback)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Official Resolution Note / Action Taken <span class="required">*</span></label>
                        <textarea name="official_note" class="form-control" rows="3" placeholder="Explain the actions taken, teams dispatched, or completed repairs..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        Save Status & Notify Complainant &rarr;
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Status History Timeline -->
        <div style="border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
            <h4 style="font-size: 1.15rem; margin-bottom: 1rem; color: var(--primary-navy);">📜 Status History Timeline</h4>
            <div class="timeline">
                <?php foreach ($history as $h): ?>
                    <div class="timeline-item">
                        <div class="timeline-bullet"></div>
                        <div class="timeline-date"><?= format_date($h['created_at'], 'M d, Y h:i A') ?></div>
                        <div class="timeline-content">
                            <strong>Status: <?= e($h['to_status']) ?></strong>
                            <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: 0.5rem;">
                                by <?= e($h['actor_name']) ?> (<?= e(ucfirst($h['actor_type'])) ?>)
                            </span>
                            <?php if (!empty($h['note'])): ?>
                                <div style="margin-top: 0.35rem; font-size: 0.9rem; color: var(--text-secondary);">
                                    <?= nl2br(e($h['note'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
