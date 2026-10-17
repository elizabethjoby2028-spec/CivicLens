<?php
/**
 * CivicLens — Secure Administrator Setup
 * Provides a secure method to provision the initial superadmin without hardcoded or seeded plaintext credentials.
 * Once an administrator account exists, further admin creation requires authenticated administrator session.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

flash('info', 'Administrator self-registration is disabled. Please use the built-in administrator credentials provided on the login page.');
redirect('admin_login.php');
exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $username = sanitize_input($_POST['username'] ?? '');
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($username) || empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are mandatory.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long for administrative security.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check uniqueness
        $check = $db->prepare("SELECT COUNT(*) FROM administrators WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);
        if ($check->fetchColumn() > 0) {
            $error = 'An administrator with this username or email already exists.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $role = ($admin_count === 0) ? 'superadmin' : 'admin';

            $stmt = $db->prepare("
                INSERT INTO administrators (username, email, name, password_hash, role, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$username, $email, $name, $password_hash, $role]);

            flash('success', "Administrator account '{$username}' successfully created. You can now log in.");
            redirect('admin_login.php');
        }
    }
}

$page_title = 'Administrator Setup — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 520px; margin: 2rem auto;">
    <div class="card" style="border-top: 4px solid var(--primary-navy);">
        <div class="card-header" style="text-align: center; display: block;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🛡️</div>
            <h2 class="card-title" style="font-size: 1.45rem; color: var(--primary-navy);">
                <?= ($admin_count === 0) ? 'Provision Initial Administrator' : 'Create Additional Administrator' ?>
            </h2>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem;">
                <?= ($admin_count === 0) 
                    ? 'No administrator accounts exist yet. Complete this one-time setup to initialize the system.'
                    : 'Authenticated session confirmed. Provisioning a secondary system administrator.' ?>
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span>⚠️ <?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="setup_admin.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="name">Full Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g. Chief Municipal Administrator" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="username">Username <span class="required">*</span></label>
                <input type="text" id="username" name="username" class="form-control" value="<?= e($_POST['username'] ?? '') ?>" placeholder="e.g. admin" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Admin Email <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" placeholder="admin@civiclens.gov" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 8 characters" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirm">Confirm Password <span class="required">*</span></label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Re-enter password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem;">
                <?= ($admin_count === 0) ? 'Create Superadmin Account &rarr;' : 'Add Administrator &rarr;' ?>
            </button>
        </form>

        <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted);">
            Already have an administrative account? <a href="<?= url('admin_login.php') ?>">Admin Sign In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
