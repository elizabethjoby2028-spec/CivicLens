<?php
/**
 * CivicLens — Track Complaint Status & History
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';

$complaint_id_input = sanitize_input($_GET['id'] ?? ($_POST['complaint_id'] ?? ''));
$tracking_token_input = sanitize_input($_GET['token'] ?? ($_POST['tracking_token'] ?? ''));

$complaint = null;
$history = [];
$error_message = '';
$requires_token = false;

if (!empty($complaint_id_input)) {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT c.*, a.department as authority_dept, a.official_name as authority_official
        FROM complaints c
        LEFT JOIN authorities a ON c.assigned_authority_id = a.id
        WHERE c.complaint_id = ?
    ");
    $stmt->execute([$complaint_id_input]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        $error_message = 'No complaint found matching ID: ' . htmlspecialchars($complaint_id_input);
    } else {
        // Enforce access control
        if ($complaint['is_anonymous'] == 1) {
            // Anonymous complaint requires token verification
            if (empty($tracking_token_input)) {
                $requires_token = true;
                $complaint = null; // Do not display until token is provided
            } else {
                $valid_token = verify_tracking_credential($tracking_token_input, $complaint['anonymous_tracking_hash']);
                if (!$valid_token) {
                    $error_message = 'Invalid secret tracking credential for anonymous complaint ' . htmlspecialchars($complaint_id_input);
                    $complaint = null;
                }
            }
        } else {
            // Regular complaint: Only the owner citizen, admin, or authority can view
            $can_view = false;
            if (is_admin_logged_in()) {
                $can_view = true;
            } elseif (is_authority_logged_in() && $complaint['assigned_authority_id'] == current_authority_id()) {
                $can_view = true;
            } elseif (is_citizen_logged_in() && $complaint['citizen_id'] == current_citizen_id()) {
                $can_view = true;
            }

            if (!$can_view) {
                $complaint = null;
                $error_message = 'Access restricted: You must be logged in as the citizen who submitted this complaint, or an authorized official, to view these details.';
            }
        }

        // Fetch history if authorized
        if ($complaint) {
            $hstmt = $db->prepare("
                SELECT * FROM complaint_status_history 
                WHERE complaint_id = ? 
                ORDER BY created_at ASC
            ");
            $hstmt->execute([$complaint['id']]);
            $history = $hstmt->fetchAll();
        }
    }
}

$page_title = __('track_complaint') . ' — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 850px; margin: 1rem auto;">
    <div style="margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.85rem; color: var(--primary-navy);">🔍 <?= e(__('track_complaint')) ?></h2>
        <p style="color: var(--text-secondary);">Enter your Complaint ID to track investigation progress, resolution notes, and status milestones.</p>
    </div>

    <!-- Search Form -->
    <div class="card" style="margin-bottom: 2rem;">
        <form method="GET" action="track_complaint.php">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; align-items: flex-end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="complaint_id">Complaint ID <span class="required">*</span></label>
                    <input type="text" id="complaint_id" name="id" class="form-control" value="<?= e($complaint_id_input) ?>" placeholder="e.g. CLN-20261017-XXXX" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="tracking_token">Anonymous Tracking Credential (If Anonymous)</label>
                    <input type="password" id="tracking_token" name="token" class="form-control" value="<?= e($tracking_token_input) ?>" placeholder="32-character secret token">
                </div>

                <div>
                    <button type="submit" class="btn btn-primary btn-block" style="height: 44px;">
                        Track Status &rarr;
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            <span>⚠️ <?= e($error_message) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($requires_token): ?>
        <div class="alert alert-warning">
            <strong>🔒 Anonymous Issue Protection:</strong> This report is marked anonymous. Please enter the 32-character tracking credential generated during your ₹9 submission to access its records.
        </div>
    <?php endif; ?>

    <?php if ($complaint): ?>
        <!-- Complaint Details Card -->
        <div class="card">
            <div class="card-header">
                <div>
                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                        Complaint Reference
                    </span>
                    <h3 style="font-size: 1.6rem; color: var(--navy-blue); font-family: monospace;">
                        <?= e($complaint['complaint_id']) ?>
                    </h3>
                </div>
                <div>
                    <?= render_status_badge($complaint['status']) ?>
                </div>
            </div>

            <!-- Resolution & 10-Day Feedback Banner if Solved -->
            <?php if ($complaint['status'] === 'Solved'): ?>
                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                        <span style="color: #065f46; font-weight: 700; font-size: 1.05rem;">
                            ✅ Issue Marked as Solved by Authority
                        </span>
                        <?php if (!empty($complaint['feedback_deadline'])): ?>
                            <span style="font-size: 0.85rem; color: #047857; font-weight: 600;">
                                Feedback Window Ends: <?= format_date($complaint['feedback_deadline'], 'M d, Y h:i A') ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($complaint['resolution_note'])): ?>
                        <div style="font-size: 0.95rem; color: #064e3b; margin-bottom: 1rem; line-height: 1.5;">
                            <strong>Official Resolution Notes:</strong><br>
                            <?= nl2br(e($complaint['resolution_note'])) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($complaint['satisfaction_status'] === 'Pending'): ?>
                        <div style="border-top: 1px solid #bbf7d0; padding-top: 1rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
                            <a href="<?= url('feedback.php?id=' . urlencode($complaint['complaint_id']) . ($complaint['is_anonymous'] ? '&token=' . urlencode($tracking_token_input) : '')) ?>" class="btn btn-success">
                                Submit Satisfaction Feedback &rarr;
                            </a>
                        </div>
                    <?php else: ?>
                        <div style="font-size: 0.85rem; color: var(--text-muted); border-top: 1px solid #bbf7d0; padding-top: 0.5rem;">
                            Satisfaction Feedback: <strong><?= e($complaint['satisfaction_status']) ?></strong>
                            <?php if (!empty($complaint['feedback_text'])): ?>
                                &mdash; "<?= e($complaint['feedback_text']) ?>"
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; font-size: 0.92rem;">
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Category</span>
                    <strong><?= e($complaint['category']) ?></strong>
                </div>

                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Location</span>
                    <strong><?= e($complaint['location']) ?></strong>
                </div>

                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Submitted On</span>
                    <strong><?= format_date($complaint['created_at']) ?></strong>
                </div>

                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Assigned Authority</span>
                    <strong><?= !empty($complaint['authority_dept']) ? e($complaint['authority_dept']) . ' (' . e($complaint['authority_official']) . ')' : 'Pending Assignment' ?></strong>
                </div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <span style="color: var(--text-muted); display: block; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; margin-bottom: 0.35rem;">Issue Title</span>
                <h4 style="font-size: 1.2rem; color: var(--primary-navy);"><?= e($complaint['title']) ?></h4>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <span style="color: var(--text-muted); display: block; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; margin-bottom: 0.35rem;">Detailed Description</span>
                <div style="background: var(--bg-surface-alt); padding: 1.25rem; border-radius: var(--radius-md); font-size: 0.95rem; line-height: 1.6;">
                    <?= nl2br(e($complaint['description'])) ?>
                </div>
            </div>

            <?php if (!empty($complaint['photo_path']) && file_exists(ROOT_PATH . '/' . $complaint['photo_path'])): ?>
                <div style="margin-bottom: 2rem;">
                    <span style="color: var(--text-muted); display: block; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">Uploaded Photograph</span>
                    <a href="<?= url($complaint['photo_path']) ?>" target="_blank">
                        <img src="<?= url($complaint['photo_path']) ?>" alt="Complaint Photo" style="max-width: 100%; max-height: 350px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    </a>
                </div>
            <?php endif; ?>

            <!-- Status History Timeline -->
            <div style="border-top: 1px solid var(--border-color); padding-top: 1.75rem;">
                <h4 style="font-size: 1.15rem; margin-bottom: 1.25rem; color: var(--primary-navy);">
                    📜 <?= e(__('status_history')) ?>
                </h4>

                <div class="timeline">
                    <?php foreach ($history as $h): ?>
                        <div class="timeline-item">
                            <div class="timeline-bullet"></div>
                            <div class="timeline-date"><?= format_date($h['created_at'], 'M d, Y h:i A') ?></div>
                            <div class="timeline-content">
                                <strong>Status changed to: <?= e($h['to_status']) ?></strong>
                                <span style="font-size: 0.82rem; color: var(--text-muted); margin-left: 0.5rem;">
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
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
