<?php
declare(strict_types=1);

// Polled by the browser when the live progress stream is not available.

require dirname(__DIR__) . '/src/bootstrap.php';

$job = job_from_request($_GET['id'] ?? null);

// A job stuck in "generating" for 20 minutes means the PHP process was killed by the host.
if ($job['state'] === 'generating' && time() - (int) $job['updated_at'] > 1200) {
    $job = Jobs::set($job['id'], [
        'state' => 'error',
        'error' => 'Building took too long and was stopped. Please try again.',
    ]);
}

json_response(['ok' => true, 'job' => Jobs::publicView($job)]);
