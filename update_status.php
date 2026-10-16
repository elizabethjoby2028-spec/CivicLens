<?php
/**
 * CivicLens — Status Update & Authority Assignment Controller
 * Enforces role-based permissions, CSRF, and workflow rules.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/NotificationService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Invalid request method.');
    redirect('index.php');
}

verify_csrf();

$complaint_id_str = sanitize_input($_POST['complaint_id'] ?? '');
$action_type = sanitize_input($_POST['action_type'] ?? '');

if (empty($complaint_id_str)) {
    flash('error', 'Missing complaint identifier.');
    redirect('index.php');
}

$db = get_db();
$stmt = $db->prepare("
    SELECT c.*, a.department as authority_dept, a.official_name as authority_official
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

$is_admin = is_admin_logged_in();
$is_assigned_authority = (is_authority_logged_in() && $complaint['assigned_authority_id'] == current_authority_id());

// Action 1: Administrator Authority Assignment
if ($action_type === 'assign') {
    if (!$is_admin) {
        flash('error', 'Only administrators can assign authorities.');
        redirect('complaint_details.php?id=' . urlencode($complaint_id_str));
    }

    $authority_id = (int)($_POST['authority_id'] ?? 0);
    $priority = sanitize_input($_POST['priority'] ?? $complaint['priority']);

    // Validate authority exists
    $astmt = $db->prepare("SELECT id, department, official_name FROM authorities WHERE id = ? AND is_active = 1");
    $astmt->execute([$authority_id]);
    $authority = $astmt->fetch();

    if (!$authority) {
        flash('error', 'Selected authority is invalid or inactive.');
        redirect('complaint_details.php?id=' . urlencode($complaint_id_str));
    }

    $db->beginTransaction();
    try {
        $upd = $db->prepare("
            UPDATE complaints 
            SET assigned_authority_id = ?,
                assigned_at = NOW(),
                priority = ?
            WHERE id = ?
        ");
        $upd->execute([$authority_id, $priority, $complaint['id']]);

        // History entry
        $hstmt = $db->prepare("
            INSERT INTO complaint_status_history (complaint_id, from_status, to_status, actor_type, actor_id, actor_name, note)
            VALUES (?, ?, ?, 'admin', ?, ?, ?)
        ");
        $admin_info = get_current_admin();
        $admin_name = $admin_info['name'] ?? 'Administrator';
        $note = "Assigned to {$authority['department']} ({$authority['official_name']}) with {$priority} priority.";
        $hstmt->execute([$complaint['id'], $complaint['status'], $complaint['status'], current_admin_id(), $admin_name, $note]);

        // Notification to assigned authority
        NotificationService::notifyAuthority(
            $authority_id,
            $complaint['id'],
            'New Complaint Assignment',
            "Complaint {$complaint['complaint_id']} ({$complaint['category']}) has been assigned to your department. Priority: {$priority}.",
            'complaint_assigned'
        );

        // Notification to citizen if registered
        if (!empty($complaint['citizen_id'])) {
            NotificationService::notifyCitizen(
                $complaint['citizen_id'],
                $complaint['id'],
                'Complaint Assigned to Official',
                "Your complaint {$complaint['complaint_id']} has been assigned to {$authority['department']} for resolution.",
                'complaint_assigned'
            );
        }

        $db->commit();
        flash('success', 'Complaint assigned to ' . htmlspecialchars($authority['department']) . ' successfully.');
    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Failed to assign authority: ' . $e->getMessage());
    }

    redirect('complaint_details.php?id=' . urlencode($complaint_id_str));
}

// Action 2: Status Update by Authority or Admin
if ($action_type === 'status_update') {
    if (!$is_admin && !$is_assigned_authority) {
        flash('error', 'You are not authorized to update this complaint status.');
        redirect('complaint_details.php?id=' . urlencode($complaint_id_str));
    }

    // Check authority subscription if authority
    if (!$is_admin && !is_authority_subscription_active(current_authority_id())) {
        flash('error', 'Your official subscription has expired. Please renew your access to update complaints.');
        redirect('authority_subscription.php');
    }

    $new_status = sanitize_input($_POST['new_status'] ?? '');
    $official_note = sanitize_input($_POST['official_note'] ?? '');

    $allowed_statuses = ['In Progress', 'Solved', 'Closed'];
    if (!in_array($new_status, $allowed_statuses, true)) {
        flash('error', 'Invalid status selection.');
        redirect('complaint_details.php?id=' . urlencode($complaint_id_str));
    }

    if (empty($official_note)) {
        flash('error', 'An official resolution note / action explanation is required.');
        redirect('complaint_details.php?id=' . urlencode($complaint_id_str));
    }

    $actor_type = $is_admin ? 'admin' : 'authority';
    $actor_id = $is_admin ? current_admin_id() : current_authority_id();
    $actor_name = $is_admin ? ($_SESSION['admin_name'] ?? 'Administrator') : ($_SESSION['authority_name'] ?? 'Authority');

    $db->beginTransaction();
    try {
        if ($new_status === 'Solved') {
            // Setting to Solved starts the 10-day satisfaction feedback period!
            $upd = $db->prepare("
                UPDATE complaints 
                SET status = 'Solved',
                    resolution_note = ?,
                    resolved_at = NOW(),
                    feedback_deadline = DATE_ADD(NOW(), INTERVAL 10 DAY),
                    satisfaction_status = 'Pending'
                WHERE id = ?
            ");
            $upd->execute([$official_note, $complaint['id']]);

            // Notify citizen about resolution & 10-day feedback window
            if (!empty($complaint['citizen_id'])) {
                NotificationService::notifyCitizen(
                    $complaint['citizen_id'],
                    $complaint['id'],
                    'Issue Resolved — Feedback Requested',
                    "Your complaint {$complaint['complaint_id']} has been marked as Solved. Please review the resolution and submit your satisfaction feedback within 10 days.",
                    'complaint_solved'
                );
            }
        } elseif ($new_status === 'Closed') {
            $upd = $db->prepare("
                UPDATE complaints 
                SET status = 'Closed',
                    closed_at = NOW(),
                    closure_reason = 'Administrative Closure',
                    resolution_note = COALESCE(?, resolution_note)
                WHERE id = ?
            ");
            $upd->execute([$official_note, $complaint['id']]);
        } else {
            // In Progress
            $upd = $db->prepare("
                UPDATE complaints 
                SET status = 'In Progress'
                WHERE id = ?
            ");
            $upd->execute([$complaint['id']]);

            if (!empty($complaint['citizen_id'])) {
                NotificationService::notifyCitizen(
                    $complaint['citizen_id'],
                    $complaint['id'],
                    'Complaint Status: In Progress',
                    "Authorities have initiated action on complaint {$complaint['complaint_id']}: {$official_note}",
                    'status_update'
                );
            }
        }

        // Insert Status History
        $hist = $db->prepare("
            INSERT INTO complaint_status_history (complaint_id, from_status, to_status, actor_type, actor_id, actor_name, note)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $hist->execute([$complaint['id'], $complaint['status'], $new_status, $actor_type, $actor_id, $actor_name, $official_note]);

        $db->commit();
        flash('success', "Status successfully updated to {$new_status}.");
    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Status update failed: ' . $e->getMessage());
    }

    redirect('complaint_details.php?id=' . urlencode($complaint_id_str));
}

flash('error', 'Unknown action request.');
redirect('complaint_details.php?id=' . urlencode($complaint_id_str));
