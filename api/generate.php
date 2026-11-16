<?php
declare(strict_types=1);

// Step 2: build the website with Claude. Streams progress to the browser as Server-Sent Events.
// The job keeps running even if the browser disconnects; the page then polls status.php.

require dirname(__DIR__) . '/src/bootstrap.php';

const MAX_ATTEMPTS = 3;

$job = job_from_request($_GET['id'] ?? null);
$id = $job['id'];

// Turn off every layer of output buffering so progress reaches the browser live.
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
    ob_end_flush();
}
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no');       // nginx
header('X-LiteSpeed-Cache-Control: no-cache');
echo ':' . str_repeat(' ', 2048) . "\n\n"; // padding defeats small proxy buffers
flush();

function send_event(string $event, array $data): void
{
    echo "event: {$event}\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    @flush();
}

if ($job['state'] === 'done') {
    send_event('done', Jobs::publicView($job));
    exit;
}

// Only one generation per job at a time.
$lock = fopen(Jobs::dir($id) . '/generate.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    send_event('running', Jobs::publicView($job));
    exit;
}

$job = Jobs::load($id) ?? $job; // re-read now that we hold the lock
if ($job['state'] === 'done') {
    send_event('done', Jobs::publicView($job));
    exit;
}
// Limit paid AI retries per upload. No limit when testing on localhost.
if (!is_local_request() && (int) ($job['attempts'] ?? 0) >= MAX_ATTEMPTS) {
    $last = (string) ($job['error'] ?? '');
    send_event('error', [
        'error' => 'This design failed ' . MAX_ATTEMPTS . ' times' . ($last !== '' ? ' (last error: ' . rtrim($last, '.') . ')' : '')
            . '. Please start fresh or try a different image.',
        'code' => 'attempts_exhausted',
    ]);
    exit;
}

ignore_user_abort(true);
@set_time_limit(0);

$job = Jobs::update($id, static function (array $j): array {
    $j['state'] = 'generating';
    $j['error'] = null;
    $j['progress_chars'] = 0;
    $j['attempts'] = (int) ($j['attempts'] ?? 0) + 1;
    return $j;
});
send_event('started', Jobs::publicView($job));

try {
    $html = SiteGenerator::generate(
        Jobs::dir($id) . '/' . $job['image_file'],
        (string) $job['image_mime'],
        (string) ($job['notes'] ?? ''),
        static function (int $chars) use ($id): void {
            Jobs::set($id, ['progress_chars' => $chars]);
            send_event('progress', ['progress_chars' => $chars]);
        }
    );

    file_put_contents(Jobs::htmlPath($id), $html, LOCK_EX);
    $job = Jobs::set($id, [
        'state' => 'done',
        'progress_chars' => strlen($html),
        'title' => SiteGenerator::extractTitle($html),
        'generated_at' => time(),
    ]);
    send_event('done', Jobs::publicView($job));
} catch (Throwable $e) {
    $message = $e instanceof RuntimeException ? $e->getMessage() : 'Something went wrong while building your website.';
    if (!$e instanceof RuntimeException) {
        error_log('[design2html] generate failed: ' . $e);
    }
    Jobs::update($id, static function (array $j) use ($e, $message): array {
        $j['state'] = 'error';
        $j['error'] = $message;
        // Setup problems (API key, credit, model name) are not the design's fault: don't count them.
        if ($e instanceof SetupException) {
            $j['attempts'] = max(0, (int) ($j['attempts'] ?? 1) - 1);
        }
        return $j;
    });
    send_event('error', ['error' => $message]);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
