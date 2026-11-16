<?php
declare(strict_types=1);

// Step 3: create a Razorpay order for the download fee.

require dirname(__DIR__) . '/src/bootstrap.php';
require_method('POST');

$input = request_input();
$job = job_from_request(isset($input['id']) ? (string) $input['id'] : null);

if ($job['state'] !== 'done') {
    json_error('Your website is not ready yet.');
}
if (!empty($job['paid'])) {
    json_response(['ok' => true, 'paid' => true]);
}

$amount = (int) config('price_paise');
$orderId = $job['order_id'] ?? null;

// Reuse the existing order if the customer reopens checkout (same amount only).
if (!$orderId || (int) ($job['order_amount'] ?? 0) !== $amount) {
    try {
        $order = Razorpay::createOrder($amount, $job['id'], ['job_id' => $job['id']]);
    } catch (Throwable $e) {
        error_log('[design2html] ' . $e->getMessage());
        json_error('Could not start the payment. Please try again.', 502);
    }
    $orderId = (string) $order['id'];
    Jobs::set($job['id'], ['order_id' => $orderId, 'order_amount' => $amount]);
}

json_response([
    'ok' => true,
    'paid' => false,
    'key_id' => config('razorpay_key_id'),
    'order_id' => $orderId,
    'amount' => $amount,
    'currency' => 'INR',
    'name' => config('site_name'),
    'description' => 'HTML website download' . (!empty($job['title']) ? ': ' . $job['title'] : ''),
]);
