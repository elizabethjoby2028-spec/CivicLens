<?php
/**
 * CivicLens — Payment Service
 * Handles Razorpay Order Creation, Signature Verification, and Idempotency
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

class PaymentService {

    /**
     * Get Razorpay configuration
     */
    public static function getConfig() {
        return require ROOT_PATH . '/config/razorpay.php';
    }

    /**
     * Check if Razorpay credentials are fully configured
     */
    public static function isConfigured() {
        $config = self::getConfig();
        return !empty($config['is_configured']);
    }

    /**
     * Create a payment order record in the database and optionally on Razorpay
     */
    public static function createOrder($purpose, $amount, $idempotencyKey, $payerEmail = null, $payerPhone = null, $relatedEntityId = null) {
        $db = get_db();
        $config = self::getConfig();

        // Check if an order already exists for this idempotency key
        $stmt = $db->prepare("SELECT * FROM payment_orders WHERE idempotency_key = ?");
        $stmt->execute([$idempotencyKey]);
        $existing = $stmt->fetch();
        if ($existing) {
            return [
                'success' => true,
                'order'   => $existing,
                'message' => 'Existing order retrieved.'
            ];
        }

        $orderReference = 'ORD-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
        $razorpayOrderId = null;

        // If Razorpay is configured, create order via Razorpay REST API; otherwise create sandbox order ID
        if (self::isConfigured()) {
            $apiOrder = self::callRazorpayCreateOrder($amount, $orderReference);
            if ($apiOrder['success']) {
                $razorpayOrderId = $apiOrder['razorpay_order_id'];
            } else {
                return [
                    'success' => false,
                    'error'   => 'Razorpay Order Generation Failed: ' . $apiOrder['error']
                ];
            }
        } else {
            $razorpayOrderId = 'order_sandbox_' . substr(bin2hex(random_bytes(8)), 0, 14);
        }

        // Insert into database
        $stmt = $db->prepare("
            INSERT INTO payment_orders 
            (order_reference, razorpay_order_id, purpose, amount, currency, payer_email, payer_phone, status, signature_verified, related_entity_id, idempotency_key)
            VALUES (?, ?, ?, ?, 'INR', ?, ?, 'created', 0, ?, ?)
        ");
        $stmt->execute([
            $orderReference,
            $razorpayOrderId,
            $purpose,
            $amount,
            $payerEmail,
            $payerPhone,
            $relatedEntityId,
            $idempotencyKey
        ]);

        $orderId = $db->lastInsertId();

        $stmt = $db->prepare("SELECT * FROM payment_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        return [
            'success' => true,
            'order'   => $order
        ];
    }

    /**
     * Call Razorpay API to generate an order
     */
    protected static function callRazorpayCreateOrder($amountInRupees, $receipt) {
        $config = self::getConfig();
        $amountInPaise = (int)round($amountInRupees * 100);

        $url = 'https://api.razorpay.com/v1/orders';
        $data = [
            'amount'   => $amountInPaise,
            'currency' => 'INR',
            'receipt'  => $receipt,
            'payment_capture' => 1
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERPWD, $config['key_id'] . ':' . $config['key_secret']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => $err];
        }

        $body = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && !empty($body['id'])) {
            return ['success' => true, 'razorpay_order_id' => $body['id']];
        }

        $errorMsg = isset($body['error']['description']) ? $body['error']['description'] : 'HTTP ' . $httpCode;
        return ['success' => false, 'error' => $errorMsg];
    }

    /**
     * Verify payment signature
     */
    public static function verifySignature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature) {
        $config = self::getConfig();
        if (empty($config['key_secret'])) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $config['key_secret']);
        return hash_equals($expectedSignature, $razorpaySignature);
    }

    /**
     * Mark an order as paid with verified signature inside a database transaction
     */
    public static function markOrderPaid($orderReference, $razorpayPaymentId, $signatureVerified = 1) {
        $db = get_db();
        $stmt = $db->prepare("
            UPDATE payment_orders 
            SET status = 'paid',
                razorpay_payment_id = ?,
                signature_verified = ?,
                verified_at = NOW()
            WHERE order_reference = ? AND status != 'paid'
        ");
        $stmt->execute([$razorpayPaymentId, $signatureVerified ? 1 : 0, $orderReference]);
        return ($stmt->rowCount() > 0);
    }
}
