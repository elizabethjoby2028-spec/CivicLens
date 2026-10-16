<?php
/**
 * CivicLens — Payment Processing & Backend Verification
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/PaymentService.php';
require_once __DIR__ . '/includes/NotificationService.php';

$type = sanitize_input($_GET['type'] ?? 'anonymous');
$is_razorpay_ready = PaymentService::isConfigured();
$rzp_config = PaymentService::getConfig();

$success_data = null;
$error_message = '';

// Check if we are handling Razorpay payment return callback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['razorpay_payment_id'])) {
    validate_csrf_or_die();

    $razorpay_payment_id = sanitize_input($_POST['razorpay_payment_id'] ?? '');
    $razorpay_order_id   = sanitize_input($_POST['razorpay_order_id'] ?? '');
    $razorpay_signature  = sanitize_input($_POST['razorpay_signature'] ?? '');
    $order_ref           = sanitize_input($_POST['order_ref'] ?? '');

    if (empty($razorpay_payment_id) || empty($razorpay_signature) || empty($razorpay_order_id)) {
        $error_message = 'Incomplete payment response parameters received.';
    } else {
        // Cryptographic backend signature verification
        $is_valid = PaymentService::verifySignature($razorpay_order_id, $razorpay_payment_id, $razorpay_signature);

        if (!$is_valid) {
            $error_message = 'Security Verification Failed: Invalid payment signature detected. Issue not recorded.';
        } else {
            // Signature verified successfully on backend!
            $db = get_db();

            if ($type === 'anonymous' && !empty($_SESSION['pending_anon_complaint'])) {
                $draft = $_SESSION['pending_anon_complaint'];

                try {
                    $db->beginTransaction();

                    // Mark order as paid idempotently
                    $paid = PaymentService::markOrderPaid($order_ref, $razorpay_payment_id, 1);

                    // Generate unique complaint ID and 32-char secret tracking credential
                    $complaint_id = generate_complaint_id();
                    $tracking_token = generate_tracking_credential();
                    $token_hash = hash_tracking_credential($tracking_token);

                    // Insert anonymous complaint
                    $stmt = $db->prepare("
                        INSERT INTO complaints 
                        (complaint_id, citizen_id, is_anonymous, anonymous_tracking_hash, payment_id, category, title, description, location, photo_path, status, priority)
                        VALUES (?, NULL, 1, ?, ?, ?, ?, ?, ?, ?, 'Opened', 'Medium')
                    ");
                    $stmt->execute([
                        $complaint_id,
                        $token_hash,
                        $razorpay_payment_id,
                        $draft['category'],
                        $draft['title'],
                        $draft['description'],
                        $draft['location'],
                        $draft['photo_path']
                    ]);
                    $complaint_db_id = $db->lastInsertId();

                    // Record initial status history
                    $hist = $db->prepare("
                        INSERT INTO complaint_status_history 
                        (complaint_id, from_status, to_status, actor_type, actor_id, actor_name, note)
                        VALUES (?, NULL, 'Opened', 'anonymous_user', NULL, 'Anonymous Complainant', 'Anonymous report submitted with verified ₹9 payment.')
                    ");
                    $hist->execute([$complaint_db_id]);

                    // Notify administrators
                    NotificationService::notifyAdmins(
                        $complaint_db_id,
                        'New Anonymous Report',
                        "New anonymous report {$complaint_id} in category '{$draft['category']}'.",
                        'submitted'
                    );

                    $db->commit();

                    // Clear draft from session
                    unset($_SESSION['pending_anon_complaint']);

                    // Set success data for display
                    $success_data = [
                        'complaint_id'   => $complaint_id,
                        'tracking_token' => $tracking_token,
                        'category'       => $draft['category'],
                        'title'          => $draft['title'],
                        'payment_id'     => $razorpay_payment_id,
                    ];
                } catch (Exception $e) {
                    $db->rollBack();
                    $error_message = 'Failed to process complaint record: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error.');
                }
            }
        }
    }
}

// Prepare order if not yet submitted
$order = null;
if (!$success_data && empty($error_message)) {
    if ($type === 'anonymous') {
        if (empty($_SESSION['pending_anon_complaint'])) {
            flash('error', 'No active complaint found to process payment.');
            redirect('anonymous.php');
        }

        if (!$is_razorpay_ready) {
            flash('error', 'Razorpay credentials not configured. Payment disabled.');
            redirect('anonymous.php');
        }

        $draft = $_SESSION['pending_anon_complaint'];
        $orderRes = PaymentService::createOrder(
            'anonymous_complaint',
            ANONYMOUS_COMPLAINT_FEE,
            $draft['idempotency']
        );

        if (!$orderRes['success']) {
            $error_message = $orderRes['error'];
        } else {
            $order = $orderRes['order'];
        }
    }
}

$page_title = 'Payment & Verification — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 650px; margin: 1.5rem auto;">

    <?php if ($success_data): ?>
        <!-- ONE-TIME SUCCESS RECEIPT -->
        <div class="card" style="border-top: 5px solid var(--success); text-align: center; padding: 2.5rem 2rem;">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">✅</div>
            <h2 style="color: #065f46; margin-bottom: 0.5rem;">Payment Verified & Issue Created!</h2>
            <p style="color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 1.75rem;">
                Your payment (ID: <code><?= e($success_data['payment_id']) ?></code>) has been cryptographically verified on our backend.
            </p>

            <div style="background: #f0fdf4; border: 2px dashed #86efac; border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 2rem; text-align: left;">
                <div style="margin-bottom: 1rem;">
                    <span style="font-size: 0.8rem; font-weight: 700; color: #166534; text-transform: uppercase;">Unique Complaint ID</span>
                    <div style="font-size: 1.4rem; font-weight: 800; font-family: monospace; color: var(--navy-blue);">
                        <?= e($success_data['complaint_id']) ?>
                    </div>
                </div>

                <div style="border-top: 1px solid #bbf7d0; padding-top: 1rem;">
                    <span style="font-size: 0.8rem; font-weight: 700; color: #991b1b; text-transform: uppercase;">
                        ⚠️ Secret Tracking Credential (SAVE THIS NOW)
                    </span>
                    <div style="font-size: 1.15rem; font-weight: 800; font-family: monospace; color: #991b1b; word-break: break-all; background: #fff; padding: 0.75rem 1rem; border-radius: 6px; border: 1px solid #fecaca; margin-top: 0.35rem;">
                        <?= e($success_data['tracking_token']) ?>
                    </div>
                    <p style="font-size: 0.8rem; color: #b91c1c; margin-top: 0.5rem; line-height: 1.4;">
                        <strong>Important Security Note:</strong> For your privacy, only a secure one-way hash of this credential is kept on the server. We CANNOT recover this token for you. You must save both your Complaint ID and Tracking Credential to check status, view resolution notes, and submit satisfaction feedback.
                    </p>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: center;">
                <a href="<?= url('track_complaint.php?id=' . urlencode($success_data['complaint_id'])) ?>" class="btn btn-primary btn-lg">
                    Track Complaint Now &rarr;
                </a>
                <a href="<?= url('portal.php') ?>" class="btn btn-secondary btn-lg">
                    Return to Portal
                </a>
            </div>
        </div>

    <?php elseif (!empty($error_message)): ?>
        <div class="card" style="border-top: 5px solid var(--danger); text-align: center; padding: 2.5rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">❌</div>
            <h2 style="color: var(--danger); margin-bottom: 0.5rem;">Payment Verification Failed</h2>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;"><?= e($error_message) ?></p>
            <a href="<?= url('anonymous.php') ?>" class="btn btn-primary">Try Again &rarr;</a>
        </div>

    <?php elseif ($order): ?>
        <!-- PAYMENT CHECKOUT SCREEN -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">💳 Secure Payment Verification</h3>
                <span style="font-weight: 700; font-size: 1.1rem; color: var(--navy-blue);">₹<?= number_format($order['amount'], 2) ?></span>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <table style="width: 100%; font-size: 0.95rem; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.6rem 0; color: var(--text-muted);">Purpose:</td>
                        <td style="padding: 0.6rem 0; text-align: right; font-weight: 600;">Anonymous Complaint Filing Fee</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.6rem 0; color: var(--text-muted);">Order Reference:</td>
                        <td style="padding: 0.6rem 0; text-align: right; font-family: monospace;"><?= e($order['order_reference']) ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 0.6rem 0; color: var(--text-muted);">Security Standard:</td>
                        <td style="padding: 0.6rem 0; text-align: right; color: var(--success); font-weight: 600;">Backend HMAC-SHA256 Signature</td>
                    </tr>
                </table>
            </div>

            <!-- Razorpay Standard Checkout Integration -->
            <button id="rzp-button" class="btn btn-primary btn-lg btn-block" style="margin-bottom: 1rem;">
                Pay ₹<?= number_format($order['amount'], 2) ?> via Razorpay (Test Mode) &rarr;
            </button>
            <a href="<?= url('anonymous.php') ?>" class="btn btn-secondary btn-block">Cancel Submission</a>

            <!-- Hidden Verification Form -->
            <form id="rzp-verify-form" method="POST" action="payment.php?type=anonymous" style="display: none;">
                <?= csrf_field() ?>
                <input type="hidden" name="order_ref" value="<?= e($order['order_reference']) ?>">
                <input type="hidden" name="razorpay_order_id" id="rzp_order_id" value="<?= e($order['razorpay_order_id']) ?>">
                <input type="hidden" name="razorpay_payment_id" id="rzp_payment_id" value="">
                <input type="hidden" name="razorpay_signature" id="rzp_signature" value="">
            </form>

            <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
            <script>
            document.getElementById('rzp-button').onclick = function(e){
                e.preventDefault();
                var options = {
                    "key": "<?= e($rzp_config['key_id']) ?>",
                    "amount": "<?= (int)round($order['amount'] * 100) ?>",
                    "currency": "INR",
                    "name": "<?= e(APP_NAME) ?>",
                    "description": "Anonymous Complaint Anti-Spam Verification",
                    "order_id": "<?= e($order['razorpay_order_id']) ?>",
                    "handler": function (response){
                        // Populate hidden form and submit to backend for cryptographic signature verification
                        document.getElementById('rzp_order_id').value = response.razorpay_order_id;
                        document.getElementById('rzp_payment_id').value = response.razorpay_payment_id;
                        document.getElementById('rzp_signature').value = response.razorpay_signature;
                        document.getElementById('rzp-verify-form').submit();
                    },
                    "theme": {
                        "color": "#1e3a8a"
                    }
                };
                var rzp1 = new Razorpay(options);
                rzp1.open();
            }
            </script>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
