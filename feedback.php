<?php
/**
 * CivicLens — Complainant Satisfaction Feedback
 * Allows citizens and anonymous complainants to review resolved issues:
 * Option A: Satisfied — Close Complaint
 * Option B: Not Satisfied — Reopen Complaint
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/NotificationService.php';

$complaint_id_str = sanitize_input($_GET['id'] ?? ($_POST['complaint_id'] ?? ''));
$tracking_token = sanitize_input($_GET['token'] ?? ($_POST['tracking_token'] ?? ''));

if (empty($complaint_id_str)) {
    flash('error', 'Complaint ID missing.');
    redirect('index.php');
}

$db = get_db();
$stmt = $db->prepare("
    SELECT c.*, a.department as authority_dept, a.official_name as authority_official, a.id as authority_id
    FROM complaints c
    LEFT JOIN authorities a ON c.assigned_authority_id = a.id
    WHERE c.complaint_id = ?
");
$stmt->execute([$complaint_id_str]);
$complaint = $stmt->fetch();

if (!$complaint) {
    flash('error', 'Complaint not found.');
    redirect('index.php');
}

// Access Verification
$is_authorized = false;
$actor_type = 'citizen';
$actor_id = null;
$actor_name = 'Complainant';

if ($complaint['is_anonymous']) {
    $actor_type = 'anonymous_user';
    $actor_name = 'Anonymous Complainant';
    if (!empty($tracking_token) && verify_tracking_credential($tracking_token, $complaint['anonymous_tracking_hash'])) {
        $is_authorized = true;
    }
} else {
    if (is_citizen_logged_in() && $complaint['citizen_id'] == current_citizen_id()) {
        $is_authorized = true;
        $actor_id = current_citizen_id();
        $actor_name = $_SESSION['citizen_name'] ?? 'Citizen';
    }
}

if (!$is_authorized) {
    if ($complaint['is_anonymous']) {
        flash('error', 'Please provide a valid tracking credential to submit feedback for this anonymous complaint.');
        redirect('track_complaint.php?id=' . urlencode($complaint_id_str));
    } else {
        flash('error', 'You are not authorized to provide feedback for this complaint.');
        redirect('login.php');
    }
}

// Check if feedback can be submitted
// Status must be 'Solved' and satisfaction_status 'Pending'
if ($complaint['status'] !== 'Solved' || $complaint['satisfaction_status'] !== 'Pending') {
    flash('info', 'Feedback is only available for complaints currently in "Solved" status awaiting feedback.');
    if ($complaint['is_anonymous']) {
        redirect('track_complaint.php?id=' . urlencode($complaint_id_str) . '&token=' . urlencode($tracking_token));
    } else {
        redirect('complaint_details.php?id=' . urlencode($complaint_id_str));
    }
}

// Check if 10-day feedback window has expired
if (!empty($complaint['feedback_deadline']) && strtotime($complaint['feedback_deadline']) < time()) {
    flash('warning', 'The 10-day feedback window for this complaint has expired.');
    if ($complaint['is_anonymous']) {
        redirect('track_complaint.php?id=' . urlencode($complaint_id_str) . '&token=' . urlencode($tracking_token));
    } else {
        redirect('complaint_details.php?id=' . urlencode($complaint_id_str));
    }
}

// Process POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $decision = sanitize_input($_POST['decision'] ?? '');
    $feedback_notes = sanitize_input($_POST['feedback_notes'] ?? '');

    if ($decision === 'satisfied') {
        // Option A: Satisfied — Close Complaint
        $db->beginTransaction();
        try {
            $update_stmt = $db->prepare("
                UPDATE complaints 
                SET status = 'Closed',
                    satisfaction_status = 'Satisfied',
                    closure_reason = 'Satisfied',
                    feedback_text = ?,
                    feedback_submitted_at = NOW(),
                    closed_at = NOW()
                WHERE id = ? AND status = 'Solved' AND satisfaction_status = 'Pending'
            ");
            $update_stmt->execute([$feedback_notes, $complaint['id']]);

            // Status history
            $hist_stmt = $db->prepare("
                INSERT INTO complaint_status_history (complaint_id, from_status, to_status, actor_type, actor_id, actor_name, note)
                VALUES (?, 'Solved', 'Closed', ?, ?, ?, ?)
            ");
            $hist_note = 'Complainant confirmed satisfaction. Complaint closed. ' . ($feedback_notes ? 'Remarks: ' . $feedback_notes : '');
            $hist_stmt->execute([$complaint['id'], $actor_type, $actor_id, $actor_name, $hist_note]);

            // Notify assigned authority
            if (!empty($complaint['assigned_authority_id'])) {
                NotificationService::notifyAuthority(
                    $complaint['assigned_authority_id'],
                    $complaint['id'],
                    'Complaint Closed with Citizen Satisfaction',
                    "Complaint {$complaint['complaint_id']} was closed. Citizen expressed satisfaction.",
                    'feedback_received'
                );
            }

            // In-app notification for citizen if registered
            if (!empty($complaint['citizen_id'])) {
                NotificationService::notifyCitizen(
                    $complaint['citizen_id'],
                    $complaint['id'],
                    'Complaint Closed Successfully',
                    "Thank you for your feedback! Complaint {$complaint['complaint_id']} has been closed as Satisfied.",
                    'complaint_closed'
                );
            }

            $db->commit();
            flash('success', 'Thank you! Your feedback has been recorded and the complaint is now closed.');

            if ($complaint['is_anonymous']) {
                redirect('track_complaint.php?id=' . urlencode($complaint_id_str) . '&token=' . urlencode($tracking_token));
            } else {
                redirect('complaint_details.php?id=' . urlencode($complaint_id_str));
            }
        } catch (Exception $e) {
            $db->rollBack();
            flash('error', 'Failed to submit feedback: ' . $e->getMessage());
        }

    } elseif ($decision === 'not_satisfied') {
        // Option B: Not Satisfied — Reopen Complaint
        if (empty($feedback_notes)) {
            flash('error', 'Please provide a reason explaining why the issue is not resolved.');
        } else {
            $db->beginTransaction();
            try {
                $update_stmt = $db->prepare("
                    UPDATE complaints 
                    SET status = 'Reopened',
                        satisfaction_status = 'Not Satisfied',
                        feedback_text = ?,
                        feedback_submitted_at = NOW(),
                        reopen_count = reopen_count + 1
                    WHERE id = ? AND status = 'Solved' AND satisfaction_status = 'Pending'
                ");
                $update_stmt->execute([$feedback_notes, $complaint['id']]);

                // Status history
                $hist_stmt = $db->prepare("
                    INSERT INTO complaint_status_history (complaint_id, from_status, to_status, actor_type, actor_id, actor_name, note)
                    VALUES (?, 'Solved', 'Reopened', ?, ?, ?, ?)
                ");
                $hist_note = 'Complainant reported issue is unresolved. Reopened with feedback: ' . $feedback_notes;
                $hist_stmt->execute([$complaint['id'], $actor_type, $actor_id, $actor_name, $hist_note]);

                // Notify assigned authority that case was reopened
                if (!empty($complaint['assigned_authority_id'])) {
                    NotificationService::notifyAuthority(
                        $complaint['assigned_authority_id'],
                        $complaint['id'],
                        'Complaint Reopened by Citizen',
                        "Complaint {$complaint['complaint_id']} was reopened: \"{$feedback_notes}\". Immediate attention required.",
                        'complaint_reopened'
                    );
                }

                // Notify Administrators
                NotificationService::notifyAdmins(
                    $complaint['id'],
                    'Complaint Reopened',
                    "Complaint {$complaint['complaint_id']} was reopened by complainant.",
                    'complaint_reopened'
                );

                if (!empty($complaint['citizen_id'])) {
                    NotificationService::notifyCitizen(
                        $complaint['citizen_id'],
                        $complaint['id'],
                        'Complaint Reopened',
                        "Your feedback was received and complaint {$complaint['complaint_id']} has been reopened for further action.",
                        'complaint_reopened'
                    );
                }

                $db->commit();
                flash('warning', 'Complaint reopened. The responsible authority has been notified for corrective action.');

                if ($complaint['is_anonymous']) {
                    redirect('track_complaint.php?id=' . urlencode($complaint_id_str) . '&token=' . urlencode($tracking_token));
                } else {
                    redirect('complaint_details.php?id=' . urlencode($complaint_id_str));
                }
            } catch (Exception $e) {
                $db->rollBack();
                flash('error', 'Failed to reopen complaint: ' . $e->getMessage());
            }
        }
    } else {
        flash('error', 'Please choose either "Satisfied" or "Not Satisfied".');
    }
}

$page_title = 'Satisfaction Feedback — ' . $complaint['complaint_id'];
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 760px; margin: 1.5rem auto;">
    <div style="margin-bottom: 1.5rem;">
        <?php if ($complaint['is_anonymous']): ?>
            <a href="<?= url('track_complaint.php?id=' . urlencode($complaint_id_str) . '&token=' . urlencode($tracking_token)) ?>" style="font-size: 0.9rem; color: var(--text-muted);">&larr; Back to Tracking</a>
        <?php else: ?>
            <a href="<?= url('complaint_details.php?id=' . urlencode($complaint_id_str)) ?>" style="font-size: 0.9rem; color: var(--text-muted);">&larr; Back to Complaint Details</a>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Resolution Verification</span>
                <h3 style="font-size: 1.45rem; color: var(--navy-blue); margin-top: 0.2rem;">Citizen Satisfaction Feedback</h3>
            </div>
            <div>
                <span class="badge badge-success" style="font-size: 0.85rem; padding: 0.35rem 0.8rem;">Status: Solved</span>
            </div>
        </div>

        <!-- Issue Summary -->
        <div style="background: var(--bg-surface-alt); padding: 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
            <div style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Complaint ID: <?= e($complaint['complaint_id']) ?></div>
            <h4 style="font-size: 1.15rem; color: var(--primary-navy); margin: 0.3rem 0;"><?= e($complaint['title']) ?></h4>
            <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.5rem;"><?= e($complaint['location']) ?> &bull; Category: <?= e($complaint['category']) ?></p>
            
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-sm); padding: 1rem; margin-top: 1rem;">
                <strong style="color: #065f46; display: block; font-size: 0.9rem; margin-bottom: 0.25rem;">Official Resolution Notes:</strong>
                <p style="color: #047857; font-size: 0.95rem; margin: 0; line-height: 1.5;">
                    <?= nl2br(e($complaint['resolution_note'] ?? 'Work marked as completed by officials.')) ?>
                </p>
                <div style="font-size: 0.8rem; color: #059669; margin-top: 0.5rem;">
                    Resolved on <?= format_date($complaint['resolved_at']) ?>
                </div>
            </div>

            <div style="margin-top: 0.85rem; font-size: 0.85rem; color: #b45309; display: flex; align-items: center; gap: 0.5rem;">
                <span>⏳ Feedback Deadline:</span>
                <strong><?= format_date($complaint['feedback_deadline']) ?></strong>
                <span style="font-size: 0.8rem; color: var(--text-muted);">(Will automatically close after 10 days if no response)</span>
            </div>
        </div>

        <!-- Feedback Form -->
        <form method="POST" action="feedback.php" id="feedbackForm">
            <?= csrf_field() ?>
            <input type="hidden" name="complaint_id" value="<?= e($complaint['complaint_id']) ?>">
            <input type="hidden" name="tracking_token" value="<?= e($tracking_token) ?>">

            <div style="margin-bottom: 1.5rem;">
                <label class="form-label" style="font-size: 1.05rem; margin-bottom: 0.75rem;">Are you satisfied with how this issue was resolved? <span class="required">*</span></label>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem;">
                    <!-- Option A -->
                    <label style="border: 2px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; cursor: pointer; display: flex; gap: 1rem; align-items: flex-start; transition: all var(--transition);" id="labelSatisfied">
                        <input type="radio" name="decision" value="satisfied" style="margin-top: 0.25rem;" required onchange="toggleFeedbackMode('satisfied')">
                        <div>
                            <strong style="color: #047857; font-size: 1.05rem; display: block;">✓ Option A: Satisfied — Close Complaint</strong>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0.35rem 0 0 0;">
                                The issue has been completely fixed to your satisfaction. The complaint will be permanently closed.
                            </p>
                        </div>
                    </label>

                    <!-- Option B -->
                    <label style="border: 2px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; cursor: pointer; display: flex; gap: 1rem; align-items: flex-start; transition: all var(--transition);" id="labelNotSatisfied">
                        <input type="radio" name="decision" value="not_satisfied" style="margin-top: 0.25rem;" required onchange="toggleFeedbackMode('not_satisfied')">
                        <div>
                            <strong style="color: var(--danger); font-size: 1.05rem; display: block;">✗ Option B: Not Satisfied — Reopen Complaint</strong>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0.35rem 0 0 0;">
                                The issue persists, work is incomplete, or of poor quality. Reopens the case and notifies officials immediately.
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <div class="form-group" id="feedbackNotesGroup">
                <label class="form-label" id="feedbackNotesLabel">Feedback Remarks / Comments</label>
                <textarea name="feedback_notes" id="feedback_notes" class="form-control" rows="4" placeholder="Enter your comments or reasons..."></textarea>
                <span class="form-hint" id="feedbackHint">Optional for satisfied complaints. Mandatory if reopening.</span>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end; align-items: center; margin-top: 1.5rem;">
                <?php if ($complaint['is_anonymous']): ?>
                    <a href="<?= url('track_complaint.php?id=' . urlencode($complaint_id_str) . '&token=' . urlencode($tracking_token)) ?>" class="btn btn-secondary">Cancel</a>
                <?php else: ?>
                    <a href="<?= url('complaint_details.php?id=' . urlencode($complaint_id_str)) ?>" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    Submit Feedback &rarr;
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleFeedbackMode(mode) {
    const labelSatisfied = document.getElementById('labelSatisfied');
    const labelNotSatisfied = document.getElementById('labelNotSatisfied');
    const notesLabel = document.getElementById('feedbackNotesLabel');
    const notesInput = document.getElementById('feedback_notes');
    const hint = document.getElementById('feedbackHint');
    const submitBtn = document.getElementById('submitBtn');

    if (mode === 'satisfied') {
        labelSatisfied.style.borderColor = '#10b981';
        labelSatisfied.style.background = '#f0fdf4';
        labelNotSatisfied.style.borderColor = 'var(--border-color)';
        labelNotSatisfied.style.background = 'transparent';

        notesLabel.innerHTML = 'Feedback Remarks / Thank You Note <span style="font-size:0.8rem; color:var(--text-muted);">(Optional)</span>';
        notesInput.required = false;
        notesInput.placeholder = 'Any compliments or remarks on the resolution quality...';
        hint.textContent = 'Your confirmation will close this complaint cleanly.';
        submitBtn.className = 'btn btn-success';
        submitBtn.innerHTML = 'Confirm & Close Complaint &rarr;';
    } else {
        labelNotSatisfied.style.borderColor = '#ef4444';
        labelNotSatisfied.style.background = '#fef2f2';
        labelSatisfied.style.borderColor = 'var(--border-color)';
        labelSatisfied.style.background = 'transparent';

        notesLabel.innerHTML = 'Reason For Dissatisfaction <span class="required">*</span>';
        notesInput.required = true;
        notesInput.placeholder = 'Please explain specifically what was not fixed or why you are reopening this issue...';
        hint.textContent = 'Explanation is required so the department can take corrective action.';
        submitBtn.className = 'btn btn-danger';
        submitBtn.innerHTML = 'Reopen Complaint For Action &rarr;';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
