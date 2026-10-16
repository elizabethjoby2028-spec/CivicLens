<?php
/**
 * CivicLens — Anonymous Issue Submission (₹9 Fee)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/PaymentService.php';

$app_config = require __DIR__ . '/config/app.php';
$categories = $app_config['categories'];
$is_razorpay_ready = PaymentService::isConfigured();

$errors = [];
$category = '';
$title = '';
$description = '';
$location = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    if (!$is_razorpay_ready) {
        $errors[] = 'Payment gateway is not currently configured. Paid submissions are safely disabled.';
    } else {
        $category = sanitize_input($_POST['category'] ?? '');
        $title = sanitize_input($_POST['title'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $location = sanitize_input($_POST['location'] ?? '');

        // Validation
        if (empty($category) || !in_array($category, $categories)) {
            $errors[] = 'Please select a valid complaint category.';
        }
        if (empty($title) || strlen($title) < 5 || strlen($title) > 200) {
            $errors[] = 'Title must be between 5 and 200 characters.';
        }
        if (empty($description) || strlen($description) < 15) {
            $errors[] = 'Description must be at least 15 characters.';
        }
        if (empty($location)) {
            $errors[] = 'Location or landmark is required.';
        }

        // Handle optional photo
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = validate_and_save_upload($_FILES['photo'], 'complaints', 5);
            if (!$uploadResult['success']) {
                $errors[] = $uploadResult['error'];
            } else {
                $photo_path = $uploadResult['path'];
            }
        }

        if (empty($errors)) {
            // Store pending anonymous complaint draft in session
            $_SESSION['pending_anon_complaint'] = [
                'category'    => $category,
                'title'       => $title,
                'description' => $description,
                'location'    => $location,
                'photo_path'  => $photo_path,
                'created_at'  => time(),
                'idempotency' => bin2hex(random_bytes(16))
            ];

            // Proceed to backend payment order creation
            redirect('payment.php?type=anonymous');
        }
    }
}

$page_title = __('anonymous_complaint') . ' — ' . __('app_name');
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 740px; margin: 1rem auto;">
    <div style="margin-bottom: 1.5rem;">
        <a href="<?= url('portal.php') ?>" style="font-size: 0.9rem; color: var(--text-muted);">&larr; Back to Citizen Portal</a>
    </div>

    <div class="card" style="border-top: 4px solid var(--navy-blue);">
        <div class="card-header">
            <div>
                <h2 class="card-title">🕵️ <?= e(__('anonymous_complaint')) ?></h2>
                <span style="font-size: 0.85rem; color: var(--text-muted);">Zero registration required &bull; 100% Identity Protected</span>
            </div>
            <div style="text-align: right;">
                <span style="background: var(--navy-blue); color: #fff; font-weight: 800; font-size: 1rem; padding: 0.35rem 0.85rem; border-radius: 9999px;">
                    ₹<?= (int)ANONYMOUS_COMPLAINT_FEE ?> Fee
                </span>
            </div>
        </div>

        <!-- Configuration Banner if Gateway Credentials Missing -->
        <?php if (!$is_razorpay_ready): ?>
            <div class="alert alert-warning" style="display: block;">
                <strong>⚠️ Razorpay Test Credentials Not Configured</strong>
                <p style="font-size: 0.9rem; margin-top: 0.35rem; line-height: 1.5;">
                    To protect users and prevent spam, anonymous complaints require a verified ₹9 payment via Razorpay. Currently, test mode API credentials are missing in the server's <code>.env</code> file.
                </p>
                <div style="font-size: 0.85rem; background: rgba(0,0,0,0.04); padding: 0.65rem 0.85rem; border-radius: var(--radius-sm); margin-top: 0.5rem; font-family: monospace;">
                    RAZORPAY_KEY_ID=rzp_test_...<br>
                    RAZORPAY_KEY_SECRET=...
                </div>
                <p style="font-size: 0.85rem; margin-top: 0.5rem; color: #92400e;">
                    <em>Paid submissions are currently disabled to prevent unverified entries.</em>
                </p>
            </div>
        <?php else: ?>
            <div class="alert alert-info" style="display: block;">
                <span>ℹ️ <strong>How Anonymous Reporting Works:</strong> You pay a nominal anti-spam fee of ₹9.00 via Razorpay. Once verified on our server, your report is logged without your name or IP. You will receive a unique Complaint ID and a random 32-character tracking credential.</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin-left: 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="anonymous.php" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="category"><?= e(__('category')) ?> <span class="required">*</span></label>
                <select id="category" name="category" class="form-control" required <?= !$is_razorpay_ready ? 'disabled' : '' ?>>
                    <option value="">-- Select Issue Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= ($category === $cat) ? 'selected' : '' ?>>
                            <?= e($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="title"><?= e(__('title')) ?> <span class="required">*</span></label>
                <input type="text" id="title" name="title" class="form-control" value="<?= e($title) ?>" placeholder="Brief title of incident or violation" minlength="5" maxlength="200" required <?= !$is_razorpay_ready ? 'disabled' : '' ?>>
            </div>

            <div class="form-group">
                <label class="form-label" for="location"><?= e(__('location')) ?> <span class="required">*</span></label>
                <input type="text" id="location" name="location" class="form-control" value="<?= e($location) ?>" placeholder="Location, street, or landmark" required <?= !$is_razorpay_ready ? 'disabled' : '' ?>>
            </div>

            <div class="form-group">
                <label class="form-label" for="description"><?= e(__('description')) ?> <span class="required">*</span></label>
                <textarea id="description" name="description" class="form-control" rows="5" placeholder="Provide complete facts without including personal identifying details..." minlength="15" required <?= !$is_razorpay_ready ? 'disabled' : '' ?>><?= e($description) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="photo"><?= e(__('photo')) ?></label>
                <input type="file" id="photo" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp" data-preview-target="anon_photo_preview" <?= !$is_razorpay_ready ? 'disabled' : '' ?>>
                <div class="form-hint">Accepted formats: JPG, PNG, WebP (Max 5MB). EXIF location metadata is stripped for safety.</div>
                <img id="anon_photo_preview" src="" alt="Preview" style="display: none; max-width: 200px; max-height: 200px; margin-top: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
            </div>

            <div style="background: var(--bg-surface-alt); padding: 1.25rem; border-radius: var(--radius-md); margin: 1.75rem 0; border: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.95rem;">
                    <span>Anonymous Anti-Spam Security Fee:</span>
                    <strong>₹<?= number_format(ANONYMOUS_COMPLAINT_FEE, 2) ?></strong>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">
                    Secured via Razorpay Test Gateway. Backend signature verification required before issue generation.
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block" <?= !$is_razorpay_ready ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
                Proceed to Secure Verification (₹<?= (int)ANONYMOUS_COMPLAINT_FEE ?>) &rarr;
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
