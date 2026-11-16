<?php
declare(strict_types=1);

// Sends the clean HTML file, only after payment.

require dirname(__DIR__) . '/src/bootstrap.php';

$id = (string) ($_GET['id'] ?? '');
$job = Jobs::isValidId($id) ? Jobs::load($id) : null;
$file = $job ? Jobs::htmlPath($id) : '';

if ($job === null || $job['state'] !== 'done' || !is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'This website was not found. It may have expired.';
    exit;
}
if (empty($job['paid'])) {
    http_response_code(402);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Payment is required to download this website.';
    exit;
}

$slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', (string) ($job['title'] ?? 'website')), '-'));
$slug = substr($slug !== '' ? $slug : 'website', 0, 60);

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $slug . '.html"');
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($file);
