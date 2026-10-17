<?php
/**
 * CivicLens — Authority Login
 * Built-in credentials for all 6 municipal departments with 1-click auto-fill
 * and automatic routing redirection support.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/lang.php';

// Redirect if already logged in as authority
if (is_authority_logged_in()) {
    redirect('authority_dashboard.php');
}

$dept_id = (int)($_GET['dept'] ?? 0);
$ref_id = sanitize_input($_GET['ref'] ?? '');

$prefill_email = '';
$prefill_dept_name = '';

if ($dept_id > 0) {
    $db = get_db();
    $astmt = $db->prepare("SELECT email, department FROM authorities WHERE id = ?");
    $astmt->execute([$dept_id]);
    $pre_auth = $astmt->fetch();
    if ($pre_auth) {
        $prefill_email = $pre_auth['email'];
        $prefill_dept_name = $pre_auth['department'];
    }
}

$error = '';
$email = $prefill_email;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your official email and password.';
    } elseif (!check_rate_limit('auth_' . $email, 10, 900)) {
        $error = 'Too many failed login attempts. Please wait 15 minutes before trying again.';
    } else {
        $db = get_db();
        $stmt = $db->prepare("SELECT id, department, official_name, email, password_hash, is_active FROM authorities WHERE email = ?");
        $stmt->execute([$email]);
        $authority = $stmt->fetch();

        if ($authority && password_verify($password, $authority['password_hash'])) {
            if (!$authority['is_active']) {
                $error = 'Your official authority account has been deactivated. Please contact the administrator.';
            } else {
                clear_login_attempts('auth_' . $email);
                session_regenerate_id(true);

                $_SESSION['authority_id'] = $authority['id'];
                $_SESSION['authority_name'] = $authority['official_name'];
                $_SESSION['authority_dept'] = $authority['department'];

                flash('success', "Welcome, {$authority['official_name']} ({$authority['department']})");

                // Check subscription status
                if (!is_authority_subscription_active($authority['id'])) {
                    flash('warning', 'Notice: Your annual subscription (₹5,000/yr) requires activation or renewal.');
                    redirect('authority_subscription.php');
                }

                redirect('authority_dashboard.php');
            }
        } else {
            record_login_attempt('auth_' . $email);
            $error = 'Invalid official credentials or unauthorized department account.';
        }
    }
}

$page_title = 'Official Authority Login — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 520px; margin: 2rem auto;">
    <?php if (!empty($ref_id) && !empty($prefill_dept_name)): ?>
        <div class="alert alert-info" style="margin-bottom: 1.5rem;">
            <div>
                <strong>🚀 Automatic Complaint Routing:</strong>
                <p style="margin: 0.35rem 0 0 0; font-size: 0.9rem;">
                    Complaint <strong><?= e($ref_id) ?></strong> was automatically routed to <strong><?= e($prefill_dept_name) ?></strong>. Credentials have been pre-filled below—click <strong>Sign In</strong> to inspect and solve this issue!
                </p>
            </div>
        </div>
    <?php endif; ?>

    <div class="card" style="border-top: 4px solid var(--primary-navy);">
        <div class="card-header" style="text-align: center; display: block;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🏛️</div>
            <h2 class="card-title" style="font-size: 1.4rem; color: var(--primary-navy);">Official Authority Portal</h2>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem;">
                Authorized personnel and municipal department access
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span>⚠️ <?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="authority_login.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="email">Official Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" placeholder="officer@department.gov.in" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control" value="<?= !empty($prefill_email) ? 'Authority@2026' : '' ?>" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem;">
                Secure Authority Sign In &rarr;
            </button>
        </form>

        <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted);">
            Are you a system administrator? <a href="<?= url('admin_login.php') ?>">Administrator Login</a><br>
            Are you a citizen? <a href="<?= url('portal.php') ?>">Citizen Portal</a>
        </div>
    </div>

    <!-- Built-in Authority Credentials Quick-Fill Card -->
    <div class="card" style="margin-top: 1.5rem; background: var(--bg-surface-alt); border: 1px solid var(--border-color);">
        <h4 style="font-size: 0.95rem; color: var(--primary-navy); margin-bottom: 0.75rem; display: flex; align-items: center; justify-content: space-between;">
            <span>📋 Built-in Authority Credentials (Click to Fill)</span>
        </h4>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.75rem;">
            Default password for all departments: <code>Authority@2026</code>
        </p>
        <div style="display: grid; grid-template-columns: 1fr; gap: 0.5rem; font-size: 0.82rem;">
            <button type="button" class="btn btn-outline btn-sm" style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: #fff;" onclick="fillAuthCreds('kwa.officer@civiclens.local')">
                <span>💧 <strong>Kerala Water Authority (KWA)</strong></span>
                <span style="color: var(--text-muted);">kwa.officer@civiclens.local &rarr;</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: #fff;" onclick="fillAuthCreds('pwd.roads@civiclens.local')">
                <span>🛣️ <strong>PWD Roads Department</strong></span>
                <span style="color: var(--text-muted);">pwd.roads@civiclens.local &rarr;</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: #fff;" onclick="fillAuthCreds('waste.mgmt@civiclens.local')">
                <span>🗑️ <strong>Solid Waste (Haritha Karma)</strong></span>
                <span style="color: var(--text-muted);">waste.mgmt@civiclens.local &rarr;</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: #fff;" onclick="fillAuthCreds('food.safety@civiclens.local')">
                <span>🍽️ <strong>Food Safety & Hygiene Dept</strong></span>
                <span style="color: var(--text-muted);">food.safety@civiclens.local &rarr;</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: #fff;" onclick="fillAuthCreds('thoofan.squad@civiclens.local')">
                <span>🚨 <strong>Anti-Narcotics (Operation Thoofan)</strong></span>
                <span style="color: var(--text-muted);">thoofan.squad@civiclens.local &rarr;</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: #fff;" onclick="fillAuthCreds('general.officer@civiclens.local')">
                <span>🏛️ <strong>General Municipal Engineering</strong></span>
                <span style="color: var(--text-muted);">general.officer@civiclens.local &rarr;</span>
            </button>
        </div>
    </div>
</div>

<script>
function fillAuthCreds(email) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = 'Authority@2026';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
