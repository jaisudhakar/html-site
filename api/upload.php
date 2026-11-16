<?php
declare(strict_types=1);

// Step 1: the customer uploads a design image. Returns a job id.

require dirname(__DIR__) . '/src/bootstrap.php';
require_method('POST');

$file = $_FILES['design'] ?? null;
if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
    // An empty $_FILES on a POST usually means the file was larger than post_max_size.
    json_error('Please choose an image to upload (it may be larger than the server allows).');
}
if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
    json_error('This image is too large.');
}
if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    json_error('Upload failed. Please try again.');
}

$maxBytes = (int) config('max_upload_mb') * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    json_error('This image is too large. The limit is ' . (int) config('max_upload_mb') . ' MB.');
}

$allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!isset($allowed[$mime])) {
    json_error('Please upload a PNG, JPG, WEBP or GIF image.');
}
if (@getimagesize($file['tmp_name']) === false) {
    json_error('This file does not look like a valid image.');
}

$notes = trim((string) ($_POST['notes'] ?? ''));
if (mb_strlen($notes) > 1500) {
    $notes = mb_substr($notes, 0, 1500);
}

if (!RateLimiter::hit(client_ip())) {
    json_error('You have reached today\'s limit of free generations. Please come back tomorrow.', 429);
}

// Occasionally remove expired jobs.
if (random_int(1, 50) === 1) {
    Jobs::cleanup();
}

try {
    $job = Jobs::create([
        'image_file' => 'design.' . $allowed[$mime],
        'image_mime' => $mime,
        'notes' => $notes,
        'attempts' => 0,
    ]);
} catch (Throwable $e) {
    error_log('[design2html] ' . $e->getMessage());
    json_error('Server storage is not writable. Please contact the site owner.', 500);
}

if (!move_uploaded_file($file['tmp_name'], Jobs::dir($job['id']) . '/' . $job['image_file'])) {
    json_error('Could not save the upload. Please try again.', 500);
}

json_response(['ok' => true, 'id' => $job['id']]);
