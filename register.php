<?php
/**
 * CivicLens — Citizen Registration
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

$errors = [];
$name = '';
$email = '';
$phone = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validation
    if (empty($name)) {
        $errors[] = 'Full name is required.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (empty($phone) || strlen($phone) < 10) {
        $errors[] = 'A valid phone number (at least 10 digits) is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $db = get_db();
        // Check if email already registered
        $stmt = $db->prepare("SELECT id FROM citizens WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email address already exists. Please log in.';
        } else {
            // Hash password securely
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $insert = $db->prepare("
                INSERT INTO citizens (name, email, phone, address, password_hash)
                VALUES (?, ?, ?, ?, ?)
            ");
            $success = $insert->execute([$name, $email, $phone, $address, $password_hash]);

            if ($success) {
                flash('success', 'Registration successful! You can now log in.');
                redirect('login.php');
            } else {
                $errors[] = 'Failed to register citizen account. Please try again.';
            }
        }
    }
}

$page_title = __('register') . ' — ' . __('citizen_portal');
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 540px; margin: 1rem auto;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= e(__('register')) ?> — <?= e(__('citizen_portal')) ?></h2>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin-left: 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="name">Full Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="<?= e($name) ?>" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">Phone Number <span class="required">*</span></label>
                <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($phone) ?>" placeholder="e.g. 9876543210" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="address">Residential Address / Locality</label>
                <textarea id="address" name="address" class="form-control" rows="2"><?= e($address) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control" minlength="8" required>
                <div class="form-hint">Minimum 8 characters.</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirm">Confirm Password <span class="required">*</span></label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" minlength="8" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem;">
                Complete Registration &rarr;
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--text-secondary);">
            Already have a citizen account? <a href="<?= url('login.php') ?>" style="font-weight: 600;">Log In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
