<?php
/**
 * CivicLens — Local Business Login
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/lang.php';

if (is_business_logged_in()) {
    redirect('business_dashboard.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your business email and password.';
    } elseif (!check_rate_limit('biz_' . $email, 5, 900)) {
        $error = 'Too many failed login attempts. Please wait 15 minutes before trying again.';
    } else {
        $db = get_db();
        $stmt = $db->prepare("SELECT id, business_name, email, password_hash, is_verified FROM businesses WHERE email = ?");
        $stmt->execute([$email]);
        $biz = $stmt->fetch();

        if ($biz && password_verify($password, $biz['password_hash'])) {
            clear_login_attempts('biz_' . $email);
            session_regenerate_id(true);

            $_SESSION['business_id'] = $biz['id'];
            $_SESSION['business_name'] = $biz['business_name'];

            flash('success', "Welcome back, {$biz['business_name']}!");
            redirect('business_dashboard.php');
        } else {
            record_login_attempt('biz_' . $email);
            $error = 'Invalid email or password.';
        }
    }
}

$page_title = 'Business Login — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 480px; margin: 2rem auto;">
    <div class="card" style="border-top: 4px solid #059669;">
        <div class="card-header" style="text-align: center; display: block;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">💼</div>
            <h2 class="card-title" style="font-size: 1.4rem; color: var(--primary-navy);">Business Partner Portal</h2>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem;">
                Manage local advertising campaigns and civic reach
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span>⚠️ <?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="business_login.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="email">Business Email <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" placeholder="contact@business.com" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem; background: #059669; border-color: #059669;">
                Sign In to Business Portal &rarr;
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--text-secondary);">
            Don't have a business partner account? <a href="<?= url('business_register.php') ?>" style="font-weight: 600; color: #059669;">Register Your Business</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
