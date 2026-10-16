<?php
/**
 * CivicLens — In-App Notifications Center
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/NotificationService.php';

// Determine user role and recipient
$recipient_type = null;
$recipient_id = null;

if (is_citizen_logged_in()) {
    $recipient_type = 'citizen';
    $recipient_id = current_citizen_id();
} elseif (is_authority_logged_in()) {
    $recipient_type = 'authority';
    $recipient_id = current_authority_id();
} elseif (is_admin_logged_in()) {
    $recipient_type = 'admin';
    $recipient_id = current_admin_id();
} else {
    flash('info', 'Please log in to view your notification center.');
    redirect('login.php');
}

// Handle Mark All As Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    verify_csrf();
    NotificationService::markAllAsRead($recipient_type, $recipient_id);
    flash('success', 'All notifications marked as read.');
    redirect('notifications.php');
}

// Handle Mark Single As Read
if (isset($_GET['read_id'])) {
    $read_id = (int)$_GET['read_id'];
    NotificationService::markAsRead($read_id, $recipient_type, $recipient_id);
    if (!empty($_GET['redirect'])) {
        redirect($_GET['redirect']);
    }
    redirect('notifications.php');
}

$notifications = NotificationService::getAll($recipient_type, $recipient_id, 50);

$page_title = __('notifications') . ' — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 800px; margin: 1.5rem auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.85rem; color: var(--primary-navy); margin-bottom: 0.25rem;">
                🔔 <?= e(__('notifications')) ?>
            </h2>
            <p style="color: var(--text-secondary); font-size: 0.95rem;">
                Real-time updates regarding your submitted complaints, assignments, and resolution milestones.
            </p>
        </div>

        <?php if (!empty($notifications)): ?>
            <form method="POST" action="notifications.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-secondary btn-sm">
                    ✓ Mark All as Read
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
            <h3 style="font-size: 1.25rem; color: var(--navy-blue); margin-bottom: 0.5rem;">No notifications yet</h3>
            <p style="color: var(--text-secondary); max-width: 400px; margin: 0 auto;">
                You're all caught up! Updates about your complaints and official responses will appear right here.
            </p>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 0.85rem;">
            <?php foreach ($notifications as $n): ?>
                <div class="card" style="padding: 1.15rem 1.35rem; border-left: 4px solid <?= $n['is_read'] ? 'var(--border-color)' : 'var(--primary-light)' ?>; background: <?= $n['is_read'] ? '#fff' : '#f8fafc' ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                                <?php if (!$n['is_read']): ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; background: var(--primary-light); border-radius: 50%;"></span>
                                <?php endif; ?>
                                <strong style="font-size: 1rem; color: var(--primary-navy);"><?= e($n['title']) ?></strong>
                                <?php if (!empty($n['event_type'])): ?>
                                    <span class="badge badge-secondary" style="font-size: 0.72rem; text-transform: uppercase;">
                                        <?= e(str_replace('_', ' ', $n['event_type'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <p style="color: var(--text-secondary); font-size: 0.92rem; margin-bottom: 0.5rem; line-height: 1.5;">
                                <?= e($n['message']) ?>
                            </p>

                            <div style="display: flex; align-items: center; gap: 1rem; font-size: 0.8rem; color: var(--text-muted);">
                                <span>🕒 <?= format_date($n['created_at']) ?></span>
                                <?php if (!empty($n['ref_code'])): ?>
                                    <span>Ref: <strong><?= e($n['ref_code']) ?></strong></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="text-align: right; display: flex; flex-direction: column; gap: 0.5rem;">
                            <?php if (!empty($n['ref_code'])): ?>
                                <a href="complaint_details.php?id=<?= urlencode($n['ref_code']) ?>" class="btn btn-outline btn-sm">
                                    View &rarr;
                                </a>
                            <?php endif; ?>
                            <?php if (!$n['is_read']): ?>
                                <a href="notifications.php?read_id=<?= (int)$n['id'] ?>" style="font-size: 0.78rem; color: var(--text-muted); text-decoration: underline;">
                                    Mark Read
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
