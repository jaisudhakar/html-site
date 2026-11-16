<?php
declare(strict_types=1);

/** Minimal Razorpay client: create orders and verify signatures. */
final class Razorpay
{
    private const API = 'https://api.razorpay.com/v1';

    /** Creates an order and returns Razorpay's order object. */
    public static function createOrder(int $amountPaise, string $receipt, array $notes = []): array
    {
        return self::request('POST', '/orders', [
            'amount' => $amountPaise,
            'currency' => 'INR',
            'receipt' => substr($receipt, 0, 40),
            'payment_capture' => 1,
            'notes' => $notes,
        ]);
    }

    public static function fetchOrder(string $orderId): array
    {
        return self::request('GET', '/orders/' . rawurlencode($orderId));
    }

    /** Checks the signature Razorpay Checkout returns to the browser after a successful payment. */
    public static function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, (string) config('razorpay_key_secret'));
        return hash_equals($expected, $signature);
    }

    /** Checks the X-Razorpay-Signature header on webhook calls. */
    public static function verifyWebhookSignature(string $body, string $signature): bool
    {
        $secret = (string) config('razorpay_webhook_secret');
        if ($secret === '' || $signature === '') {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    private static function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init(self::API . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => config('razorpay_key_id') . ':' . config('razorpay_key_secret'),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Could not reach Razorpay: ' . $err);
        }
        $data = json_decode((string) $response, true);
        if ($status >= 400 || !is_array($data)) {
            $msg = is_array($data) ? ($data['error']['description'] ?? 'Unknown error') : 'Invalid response';
            throw new RuntimeException('Razorpay error: ' . $msg);
        }
        return $data;
    }
}
