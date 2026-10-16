<?php
/**
 * CivicLens — Authority Official Annual Subscription (₹5,000 / year)
 * Manages official access subscription, Razorpay order generation, signature verification,
 * and subscription period tracking.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/PaymentService.php';

if (!is_authority_logged_in()) {
    flash('error', 'Please log in to manage your official department subscription.');
    redirect('authority_login.php');
}

$authority_id = current_authority_id();
$authority = get_current_authority();
$db = get_db();

$subscription_price = 5000.00;
$plan_name = 'Annual Municipal Authority Access';

// Fetch current subscription record
$sub_stmt = $db->prepare("
    SELECT * FROM authority_subscriptions 
    WHERE authority_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$sub_stmt->execute([$authority_id]);
$current_sub = $sub_stmt->fetch();

$is_active = ($current_sub && $current_sub['payment_status'] === 'active' && (empty($current_sub['end_date']) || strtotime($current_sub['end_date']) >= time()));
$days_remaining = ($is_active && !empty($current_sub['end_date'])) ? ceil((strtotime($current_sub['end_date']) - time()) / 86400) : 0;

$rzp_config = PaymentService::getConfig();
$is_razorpay_ready = PaymentService::isConfigured();

// Handle Razorpay Payment Callback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_subscription_payment') {
    validate_csrf_or_die();

    $razorpay_payment_id = sanitize_input($_POST['razorpay_payment_id'] ?? '');
    $razorpay_order_id   = sanitize_input($_POST['razorpay_order_id'] ?? '');
    $razorpay_signature  = sanitize_input($_POST['razorpay_signature'] ?? '');
    $order_ref           = sanitize_input($_POST['order_ref'] ?? '');

    if (empty($razorpay_payment_id) || empty($razorpay_signature) || empty($razorpay_order_id)) {
        flash('error', 'Incomplete payment verification payload received.');
        redirect('authority_subscription.php');
    }

    $is_valid = PaymentService::verifySignature($razorpay_order_id, $razorpay_payment_id, $razorpay_signature);

    if (!$is_valid) {
        flash('error', 'Cryptographic signature verification failed. Official subscription not activated.');
        redirect('authority_subscription.php');
    }

    // Success: activate subscription for 1 year
    $db->beginTransaction();
    try {
        PaymentService::markOrderPaid($order_ref, $razorpay_payment_id, 1);

        $ins_sub = $db->prepare("
            INSERT INTO authority_subscriptions 
            (authority_id, plan_name, amount, payment_status, payment_method, transaction_reference, start_date, end_date, verified_by_admin)
            VALUES (?, ?, ?, 'active', 'Razorpay', ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 1)
        ");
        $ins_sub->execute([$authority_id, $plan_name, $subscription_price, $razorpay_payment_id]);

        $db->commit();
        flash('success', 'Official subscription successfully activated for 365 days! Access granted.');
        redirect('authority_dashboard.php');
    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Failed to record subscription: ' . $e->getMessage());
        redirect('authority_subscription.php');
    }
}

// Generate payment order if initiating renewal
$order_data = null;
$error_init = '';
if ($is_razorpay_ready) {
    $idempotency_key = 'auth_sub_' . $authority_id . '_' . date('YmdH');
    $order_res = PaymentService::createOrder(
        'authority_subscription',
        $subscription_price,
        $idempotency_key,
        $authority['email'],
        $authority['phone'],
        $authority_id
    );

    if ($order_res['success']) {
        $order_data = $order_res['order'];
    } else {
        $error_init = $order_res['error'];
    }
}

$page_title = 'Official Subscription — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 780px; margin: 1.5rem auto;">
    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <div>
            <a href="<?= url('authority_dashboard.php') ?>" style="font-size: 0.9rem; color: var(--text-muted);">&larr; Back to Dashboard</a>
            <h2 style="font-size: 1.85rem; color: var(--primary-navy); margin-top: 0.25rem;">
                🏛️ Official Department Subscription
            </h2>
        </div>
        <div>
            <span class="badge <?= $is_active ? 'badge-success' : 'badge-danger' ?>" style="font-size: 0.9rem; padding: 0.4rem 0.9rem;">
                <?= $is_active ? 'Status: Active' : 'Status: Inactive / Expired' ?>
            </span>
        </div>
    </div>

    <!-- Department Summary Card -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.92rem;">
            <div>
                <span style="color: var(--text-muted); display: block; font-size: 0.8rem; text-transform: uppercase;">Department</span>
                <strong style="color: var(--primary-navy);"><?= e($authority['department']) ?></strong>
            </div>
            <div>
                <span style="color: var(--text-muted); display: block; font-size: 0.8rem; text-transform: uppercase;">Authorized Official</span>
                <strong><?= e($authority['official_name']) ?> (<?= e($authority['designation']) ?>)</strong>
            </div>
            <div>
                <span style="color: var(--text-muted); display: block; font-size: 0.8rem; text-transform: uppercase;">Official Contact</span>
                <span><?= e($authority['email']) ?> &bull; <?= e($authority['phone']) ?></span>
            </div>
            <div>
                <span style="color: var(--text-muted); display: block; font-size: 0.8rem; text-transform: uppercase;">Current Expiration</span>
                <strong><?= $is_active ? format_date($current_sub['end_date'], 'M d, Y') . " ({$days_remaining} days left)" : 'None / Expired' ?></strong>
            </div>
        </div>
    </div>

    <!-- Subscription Plan Details -->
    <div class="card" style="border: 2px solid <?= $is_active ? '#10b981' : 'var(--primary-navy)' ?>; margin-bottom: 2rem;">
        <div class="card-header">
            <div>
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--primary-light); text-transform: uppercase;">Official Access Plan</span>
                <h3 style="font-size: 1.4rem; color: var(--navy-blue); margin-top: 0.2rem;"><?= e($plan_name) ?></h3>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 1.85rem; font-weight: 800; color: var(--primary-navy);">₹<?= number_format($subscription_price, 2) ?></div>
                <span style="font-size: 0.82rem; color: var(--text-muted);">per annual 365-day cycle</span>
            </div>
        </div>

        <div style="margin-bottom: 1.5rem;">
            <p style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6;">
                Authority subscriptions provide certified department representatives with full access to civic complaint management, direct status dispatching, citizen notification channels, and resolution tracking in adherence with municipal transparency standards.
            </p>
            <ul style="list-style: none; padding: 0; margin: 1rem 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 0.6rem; font-size: 0.9rem;">
                <li>✓ Full access to assigned civic complaints</li>
                <li>✓ Official status updating and resolution notes</li>
                <li>✓ Direct citizen satisfaction notification triggers</li>
                <li>✓ Reopened case alerts & history inspection</li>
                <li>✓ Priority administrative directory listing</li>
            </ul>
        </div>

        <div style="border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
            <?php if (!$is_razorpay_ready): ?>
                <div class="alert alert-warning" style="margin-bottom: 1.25rem;">
                    <div>
                        <strong>Razorpay Live/Test Credentials Unconfigured:</strong>
                        <p style="margin: 0.35rem 0 0 0; font-size: 0.88rem;">
                            To enable automatic online card/UPI payments, set <code>RAZORPAY_KEY_ID</code> and <code>RAZORPAY_KEY_SECRET</code> in your <code>.env</code> file.
                        </p>
                    </div>
                </div>

                <div style="background: var(--bg-surface-alt); padding: 1.25rem; border-radius: var(--radius-md); text-align: center;">
                    <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.75rem;">
                        <strong>Administrator Verification:</strong> Administrators can also activate or verify official authority subscriptions directly in the Administrator Dashboard under Authority Accounts.
                    </p>
                </div>
            <?php else: ?>
                <?php if (!empty($error_init)): ?>
                    <div class="alert alert-error">
                        <span>Failed to initialize Razorpay payment order: <?= e($error_init) ?></span>
                    </div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <span style="font-size: 0.85rem; color: var(--text-muted);">Order Reference:</span>
                            <strong style="font-family: monospace; color: var(--primary-navy);"><?= e($order_data['order_reference']) ?></strong>
                        </div>

                        <button id="rzp-sub-button" class="btn btn-primary btn-lg">
                            <?= $is_active ? 'Renew Subscription for ₹5,000 &rarr;' : 'Pay ₹5,000 & Activate Official Access &rarr;' ?>
                        </button>
                    </div>

                    <!-- Hidden callback form -->
                    <form id="rzp-sub-callback" method="POST" action="authority_subscription.php" style="display: none;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="verify_subscription_payment">
                        <input type="hidden" name="order_ref" value="<?= e($order_data['order_reference']) ?>">
                        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
                        <input type="hidden" name="razorpay_order_id" id="razorpay_order_id">
                        <input type="hidden" name="razorpay_signature" id="razorpay_signature">
                    </form>

                    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                    <script>
                    const options = {
                        key: "<?= e($rzp_config['key_id']) ?>",
                        amount: <?= (int)round($subscription_price * 100) ?>,
                        currency: "INR",
                        name: "CivicLens Official Portal",
                        description: "<?= e($plan_name) ?> (₹5,000/yr)",
                        order_id: "<?= e($order_data['razorpay_order_id']) ?>",
                        prefill: {
                            name: "<?= e($authority['official_name']) ?>",
                            email: "<?= e($authority['email']) ?>",
                            contact: "<?= e($authority['phone']) ?>"
                        },
                        theme: {
                            color: "#0a2540"
                        },
                        handler: function (response) {
                            document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
                            document.getElementById('razorpay_order_id').value = response.razorpay_order_id;
                            document.getElementById('razorpay_signature').value = response.razorpay_signature;
                            document.getElementById('rzp-sub-callback').submit();
                        }
                    };

                    document.getElementById('rzp-sub-button').onclick = function(e){
                        const rzp = new Razorpay(options);
                        rzp.open();
                        e.preventDefault();
                    }
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
