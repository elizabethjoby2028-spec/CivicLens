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
            SELECT id, username, email, name, password_hash, role, category, is_active 
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
                $_SESSION['admin_category'] = $admin['category'];

                $catInfo = !empty($admin['category']) ? " [{$admin['category']} Department]" : " [City-Wide Superadmin]";
                flash('success', "Welcome, Administrator {$admin['name']}{$catInfo}");
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

    <!-- Category Admin Quick Credentials -->
    <div class="card" style="margin-top: 1.5rem; background: var(--bg-surface-alt); border: 1px solid var(--border-color);">
        <h4 style="font-size: 0.95rem; color: var(--primary-navy); margin-bottom: 0.75rem; display: flex; align-items: center; justify-content: space-between;">
            <span>📋 Category Admin Accounts (Click to Fill)</span>
        </h4>
        <div style="display: grid; grid-template-columns: 1fr; gap: 0.5rem; font-size: 0.82rem;">
            <button type="button" class="btn btn-outline btn-sm" style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: #fff;" onclick="fillCreds('admin_water', 'Water@admin2026')">
                <span>💧 <strong>Water Works Admin</strong> (`admin_water`)</span>
                <span style="color: var(--text-muted);">Water@admin2026 &rarr;</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: #fff;" onclick="fillCreds('admin_road', 'Road@admin2026')">
                <span>🛣️ <strong>Roads & Infrastructure</strong> (`admin_road`)</span>
                <span style="color: var(--text-muted);">Road@admin2026 &rarr;</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: #fff;" onclick="fillCreds('admin_food', 'Food@admin2026')">
                <span>🍽️ <strong>Food Safety Department</strong> (`admin_food`)</span>
                <span style="color: var(--text-muted);">Food@admin2026 &rarr;</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: #fff;" onclick="fillCreds('admin_waste', 'Waste@admin2026')">
                <span>🗑️ <strong>Solid Waste Management</strong> (`admin_waste`)</span>
                <span style="color: var(--text-muted);">Waste@admin2026 &rarr;</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: #fff;" onclick="fillCreds('admin_thoofan', 'Thoofan@admin2026')">
                <span>🚨 <strong>Anti-Narcotics (Thoofan)</strong> (`admin_thoofan`)</span>
                <span style="color: var(--text-muted);">Thoofan@admin2026 &rarr;</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: #fff;" onclick="fillCreds('admin_other', 'Other@admin2026')">
                <span>🏛️ <strong>General Civic Admin</strong> (`admin_other`)</span>
                <span style="color: var(--text-muted);">Other@admin2026 &rarr;</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: #fff; border-color: var(--primary-navy);" onclick="fillCreds('superadmin', 'Super@admin2026')">
                <span>🛡️ <strong>Chief Superadmin (All)</strong> (`superadmin`)</span>
                <span style="color: var(--primary-navy); font-weight: 700;">Super@admin2026 &rarr;</span>
            </button>
        </div>
    </div>
</div>

<script>
function fillCreds(u, p) {
    document.getElementById('username').value = u;
    document.getElementById('password').value = p;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
