<?php
/**
 * CivicLens — Administrator Secure Login
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/lang.php';

// Redirect if already logged in as admin
if (is_admin_logged_in()) {
    redirect('admin_dashboard.php');
}

$db = get_db();
$admin_count = (int)$db->query("SELECT COUNT(*) FROM administrators")->fetchColumn();

$error = '';
$username_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $username_input = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username_input) || empty($password)) {
        $error = 'Please provide both your administrator username/email and password.';
    } elseif (!check_rate_limit('admin_' . $username_input, 5, 900)) {
        $error = 'Too many failed administrative login attempts. Please wait 15 minutes before trying again.';
    } else {
        $stmt = $db->prepare("
            SELECT id, username, email, name, password_hash, role, is_active 
            FROM administrators 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$username_input, $username_input]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            if (!$admin['is_active']) {
                $error = 'This administrator account is currently suspended.';
            } else {
                clear_login_attempts('admin_' . $username_input);
                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_role'] = $admin['role'];

                flash('success', "Welcome, Administrator {$admin['name']} ({$admin['role']})");
                redirect('admin_dashboard.php');
            }
        } else {
            record_login_attempt('admin_' . $username_input);
            $error = 'Invalid administrator credentials.';
        }
    }
}

$page_title = 'Secure Administrator Login — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 480px; margin: 2rem auto;">
    <?php if ($admin_count === 0): ?>
        <div class="alert alert-info" style="margin-bottom: 1.5rem;">
            <div>
                <strong>First-time Setup Required:</strong>
                <p style="margin: 0.35rem 0 0.5rem 0; font-size: 0.9rem;">
                    No administrator accounts currently exist in the database.
                </p>
                <a href="<?= url('setup_admin.php') ?>" class="btn btn-primary btn-sm">
                    Provision Initial Superadmin &rarr;
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="card" style="border-top: 4px solid var(--primary-navy);">
        <div class="card-header" style="text-align: center; display: block;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🛡️</div>
            <h2 class="card-title" style="font-size: 1.4rem; color: var(--primary-navy);">CivicLens Administration</h2>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem;">
                Authorized System Administrator Access Only
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span>⚠️ <?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin_login.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="username">Username or Admin Email <span class="required">*</span></label>
                <input type="text" id="username" name="username" class="form-control" value="<?= e($username_input) ?>" placeholder="admin / officer@civiclens.gov" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem;">
                Authenticate Administrator &rarr;
            </button>
        </form>

        <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted);">
            Are you an authorized municipal officer? <a href="<?= url('authority_login.php') ?>">Authority Login</a><br>
            Are you a citizen? <a href="<?= url('portal.php') ?>">Citizen Portal</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
