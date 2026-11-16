<?php
declare(strict_types=1);

// Optional Razorpay webhook. Unlocks the download even if the customer closed the tab
// before the browser could confirm the payment. Configure it in Razorpay Dashboard → Webhooks
// with events payment.captured and order.paid.

require dirname(__DIR__) . '/src/bootstrap.php';
require_method('POST');

$body = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');

if (!Razorpay::verifyWebhookSignature($body, $signature)) {
    json_error('Invalid signature', 401);
}

$event = json_decode($body, true);
if (!is_array($event)) {
    json_error('Invalid body');
}

$type = (string) ($event['event'] ?? '');
if (!in_array($type, ['payment.captured', 'order.paid'], true)) {
    json_response(['ok' => true, 'ignored' => $type]);
}

$payment = $event['payload']['payment']['entity'] ?? [];
$orderId = (string) ($payment['order_id'] ?? ($event['payload']['order']['entity']['id'] ?? ''));
if ($orderId === '') {
    json_response(['ok' => true, 'ignored' => 'no order']);
}

// The order's receipt is the job id (set in create-order.php).
$receipt = (string) ($event['payload']['order']['entity']['receipt'] ?? '');
if ($receipt === '') {
    try {
        $receipt = (string) (Razorpay::fetchOrder($orderId)['receipt'] ?? '');
    } catch (Throwable $e) {
        error_log('[design2html] webhook: ' . $e->getMessage());
        json_error('Could not look up order', 500); // Razorpay will retry
    }
}

if (!Jobs::isValidId($receipt) || ($job = Jobs::load($receipt)) === null) {
    json_response(['ok' => true, 'ignored' => 'unknown job']);
}
if (($job['order_id'] ?? '') !== $orderId) {
    json_response(['ok' => true, 'ignored' => 'order mismatch']);
}
if ((int) ($payment['amount'] ?? $job['order_amount'] ?? 0) < (int) ($job['order_amount'] ?? 0)) {
    json_response(['ok' => true, 'ignored' => 'amount too low']);
}

if (empty($job['paid'])) {
    Jobs::set($job['id'], [
        'paid' => true,
        'payment_id' => (string) ($payment['id'] ?? ''),
        'paid_at' => time(),
        'paid_via' => 'webhook',
    ]);
}

json_response(['ok' => true]);
