<?php
declare(strict_types=1);

// Shared setup for every PHP entry point.

define('APP_ROOT', dirname(__DIR__));

if (!is_file(APP_ROOT . '/config.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Setup needed: copy config.sample.php to config.php and add your API keys.";
    exit;
}

require APP_ROOT . '/vendor/autoload.php';
require __DIR__ . '/Jobs.php';
require __DIR__ . '/RateLimiter.php';
require __DIR__ . '/Razorpay.php';
require __DIR__ . '/SiteGenerator.php';

/** @return mixed */
function config(string $key)
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config.php';
    }
    return $config[$key] ?? null;
}

function storage_path(string $sub = ''): string
{
    $base = rtrim((string) config('storage_dir'), '/');
    return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    json_response(['ok' => false, 'error' => $message], $status);
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        json_error('Method not allowed', 405);
    }
}

/** Reads a JSON request body (or falls back to form fields). */
function request_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return $_POST;
}

/** Validates a job id from user input and returns the loaded job, or sends a 404. */
function job_from_request(?string $id): array
{
    if ($id === null || !Jobs::isValidId($id)) {
        json_error('Invalid job id', 400);
    }
    $job = Jobs::load($id);
    if ($job === null) {
        json_error('Job not found. It may have expired.', 404);
    }
    return $job;
}

function client_ip(): string
{
    // REMOTE_ADDR is the only header the visitor cannot forge on a normal cPanel host.
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function format_price(): string
{
    $paise = (int) config('price_paise');
    $rupees = $paise / 100;
    return '₹' . (fmod($rupees, 1.0) === 0.0 ? number_format($rupees, 0) : number_format($rupees, 2));
}
