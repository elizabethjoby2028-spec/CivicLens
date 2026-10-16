<?php
/**
 * CivicLens — Local Business Registration
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';

if (is_business_logged_in()) {
    redirect('business_dashboard.php');
}

$error = '';
$business_name = '';
$owner_name = '';
$email = '';
$phone = '';
$category = '';
$city = '';
$address = '';
$website_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $business_name = sanitize_input($_POST['business_name'] ?? '');
    $owner_name    = sanitize_input($_POST['owner_name'] ?? '');
    $email         = sanitize_input($_POST['email'] ?? '');
    $phone         = sanitize_input($_POST['phone'] ?? '');
    $category      = sanitize_input($_POST['category'] ?? '');
    $city          = sanitize_input($_POST['city'] ?? '');
    $address       = sanitize_input($_POST['address'] ?? '');
    $website_url   = sanitize_input($_POST['website_url'] ?? '');
    $password      = $_POST['password'] ?? '';
    $password_conf = $_POST['password_confirm'] ?? '';

    if (empty($business_name) || empty($owner_name) || empty($email) || empty($phone) || empty($city) || empty($password)) {
        $error = 'Please fill in all mandatory fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid business email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $password_conf) {
        $error = 'Passwords do not match.';
    } else {
        $db = get_db();
        $chk = $db->prepare("SELECT COUNT(*) FROM businesses WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetchColumn() > 0) {
            $error = 'A business account with this email address is already registered.';
        } else {
            $pass_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                INSERT INTO businesses (business_name, owner_name, email, phone, category, address, city, website_url, password_hash, is_verified)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$business_name, $owner_name, $email, $phone, $category, $address, $city, $website_url, $pass_hash]);

            $new_biz_id = $db->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['business_id'] = $new_biz_id;
            $_SESSION['business_name'] = $business_name;

            flash('success', 'Registration successful! Your business profile has been submitted for administrative verification.');
            redirect('business_dashboard.php');
        }
    }
}

$page_title = 'Register Business — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 600px; margin: 2rem auto;">
    <div class="card" style="border-top: 4px solid #059669;">
        <div class="card-header" style="text-align: center; display: block;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">💼</div>
            <h2 class="card-title" style="font-size: 1.45rem; color: var(--primary-navy);">Local Business Partner Registration</h2>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem;">
                Promote your local services to neighborhood residents and civic communities
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span>⚠️ <?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="business_register.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="business_name">Business / Commercial Name <span class="required">*</span></label>
                <input type="text" id="business_name" name="business_name" class="form-control" value="<?= e($business_name) ?>" placeholder="e.g. Malabar Fresh Organic Store" required autofocus>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="owner_name">Owner / Contact Person <span class="required">*</span></label>
                    <input type="text" id="owner_name" name="owner_name" class="form-control" value="<?= e($owner_name) ?>" placeholder="Owner Name" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="category">Business Category <span class="required">*</span></label>
                    <select id="category" name="category" class="form-control" required>
                        <option value="">-- Select Category --</option>
                        <option value="Plumbing & Electrical" <?= ($category === 'Plumbing & Electrical') ? 'selected' : '' ?>>Plumbing & Electrical</option>
                        <option value="Construction & Hardware" <?= ($category === 'Construction & Hardware') ? 'selected' : '' ?>>Construction & Hardware</option>
                        <option value="Waste & Recycling Services" <?= ($category === 'Waste & Recycling Services') ? 'selected' : '' ?>>Waste & Recycling Services</option>
                        <option value="Grocery & Organic Food" <?= ($category === 'Grocery & Organic Food') ? 'selected' : '' ?>>Grocery & Organic Food</option>
                        <option value="Healthcare & Pharmacy" <?= ($category === 'Healthcare & Pharmacy') ? 'selected' : '' ?>>Healthcare & Pharmacy</option>
                        <option value="Automotive & Transport" <?= ($category === 'Automotive & Transport') ? 'selected' : '' ?>>Automotive & Transport</option>
                        <option value="Other Local Services" <?= ($category === 'Other Local Services') ? 'selected' : '' ?>>Other Local Services</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="email">Business Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" placeholder="contact@business.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($phone) ?>" placeholder="+91 9876543210" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="city">City / Municipal Zone <span class="required">*</span></label>
                    <input type="text" id="city" name="city" class="form-control" value="<?= e($city) ?>" placeholder="e.g. Kozhikode" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="website_url">Website or Social URL</label>
                    <input type="url" id="website_url" name="website_url" class="form-control" value="<?= e($website_url) ?>" placeholder="https://example.com">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="address">Full Physical Address</label>
                <textarea id="address" name="address" class="form-control" rows="2" placeholder="Shop / Office address..."><?= e($address) ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Min 8 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password_confirm">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Re-enter password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem;">
                Complete Business Registration &rarr;
            </button>
        </form>

        <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted);">
            Already have a registered business? <a href="<?= url('business_login.php') ?>">Sign In to Business Portal</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
