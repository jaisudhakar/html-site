<?php
declare(strict_types=1);

// Step 4: Razorpay Checkout calls back here (through the browser) after a successful payment.
// The signature proves the payment is genuine; only then is the download unlocked.

require dirname(__DIR__) . '/src/bootstrap.php';
require_method('POST');

$input = request_input();
$job = job_from_request(isset($input['id']) ? (string) $input['id'] : null);

$orderId = (string) ($input['razorpay_order_id'] ?? '');
$paymentId = (string) ($input['razorpay_payment_id'] ?? '');
$signature = (string) ($input['razorpay_signature'] ?? '');

if (!empty($job['paid'])) {
    json_response(['ok' => true, 'paid' => true]);
}
if ($orderId === '' || $paymentId === '' || $signature === '') {
    json_error('Payment details are missing.');
}
if (empty($job['order_id']) || !hash_equals((string) $job['order_id'], $orderId)) {
    json_error('This payment does not belong to this website.', 403);
}
if (!Razorpay::verifyPaymentSignature($orderId, $paymentId, $signature)) {
    json_error('Payment could not be verified. If money was deducted, contact us with your payment ID: ' . $paymentId, 403);
}

Jobs::set($job['id'], ['paid' => true, 'payment_id' => $paymentId, 'paid_at' => time(), 'paid_via' => 'checkout']);

json_response(['ok' => true, 'paid' => true]);
