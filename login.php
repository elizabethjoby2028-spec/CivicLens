<?php
/**
 * CivicLens — Citizen Login
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/lang.php';

// Redirect if already logged in
if (is_citizen_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } elseif (!check_rate_limit($email, 5, 900)) {
        $error = 'Too many failed login attempts. Please wait 15 minutes before trying again.';
    } else {
        $db = get_db();
        $stmt = $db->prepare("SELECT id, name, email, password_hash FROM citizens WHERE email = ?");
        $stmt->execute([$email]);
        $citizen = $stmt->fetch();

        if ($citizen && password_verify($password, $citizen['password_hash'])) {
            // Success: clear rate limiting and regenerate session
            clear_login_attempts($email);
            session_regenerate_id(true);

            $_SESSION['citizen_id'] = $citizen['id'];
            $_SESSION['citizen_name'] = $citizen['name'];
            $_SESSION['citizen_email'] = $citizen['email'];

            flash('success', 'Welcome back, ' . $citizen['name'] . '!');
            redirect('dashboard.php');
        } else {
            record_login_attempt($email);
            $error = 'Invalid email address or password.';
        }
    }
}

$page_title = __('login') . ' — ' . __('citizen_portal');
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 480px; margin: 2rem auto;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= e(__('login')) ?> — <?= e(__('citizen_portal')) ?></h2>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span>⚠️ <?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem;">
                Sign In &rarr;
            </button>
        </form>

        <div style="margin-top: 1rem; text-align: center;">
            <button type="button" class="btn btn-outline btn-sm" style="font-size: 0.8rem; width: 100%; border-style: dashed;" onclick="document.getElementById('email').value='citizen@civiclens.local'; document.getElementById('password').value='Citizen@2026';">
                👤 Demo Citizen (Click to Fill: citizen@civiclens.local)
            </button>
        </div>

        <div style="margin-top: 1.25rem; text-align: center; font-size: 0.9rem; color: var(--text-secondary);">
            Don't have an account yet? <a href="<?= url('register.php') ?>" style="font-weight: 600;">Register Here</a>
        </div>

        <div style="margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem; text-align: center;">
            <a href="<?= url('anonymous.php') ?>" style="font-size: 0.85rem; color: var(--text-muted);">
                Need to submit without logging in? <strong>File Anonymous Report (₹9) &rarr;</strong>
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
