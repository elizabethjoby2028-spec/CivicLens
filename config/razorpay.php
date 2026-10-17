<?php
/**
 * Razorpay Payment Gateway Configuration (Test Mode)
 */

$key_id = env('RAZORPAY_KEY_ID', '');
$key_secret = env('RAZORPAY_KEY_SECRET', '');
$webhook_secret = env('RAZORPAY_WEBHOOK_SECRET', '');

// Flag to check if real credentials are provided
$is_configured = (!empty($key_id) && !empty($key_secret) && $key_id !== 'rzp_test_your_key_id_here');

return [
    'key_id'         => $is_configured ? $key_id : 'rzp_test_sandbox_demo',
    'key_secret'     => $is_configured ? $key_secret : 'sandbox_secret_civiclens_2026',
    'webhook_secret' => $webhook_secret,
    'is_configured'  => $is_configured,
    'sandbox_mode'   => true,
    'currency'       => 'INR',
];
