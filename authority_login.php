<?php
/**
 * CivicLens — Authority Login
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

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your official email and password.';
    } elseif (!check_rate_limit('auth_' . $email, 5, 900)) {
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

<div style="max-width: 480px; margin: 2rem auto;">
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
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem;">
                Secure Authority Sign In &rarr;
            </button>
        </form>

        <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted);">
            Need an official authority account created or reset?<br>
            Please contact the <a href="<?= url('admin_login.php') ?>" style="color: var(--navy-light);">CivicLens System Administrator</a>.
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
