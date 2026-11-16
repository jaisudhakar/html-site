<?php
declare(strict_types=1);

/**
 * File-based job store (no database needed on shared hosting).
 *
 * storage/jobs/<id>/meta.json   job state
 * storage/jobs/<id>/design.<ext> uploaded design image
 * storage/jobs/<id>/site.html   generated website
 */
final class Jobs
{
    public static function isValidId(string $id): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $id);
    }

    public static function dir(string $id): string
    {
        return storage_path('jobs/' . $id);
    }

    public static function create(array $data): array
    {
        $id = bin2hex(random_bytes(16));
        $dir = self::dir($id);
        if (!mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create job folder. Check that the storage folder is writable.');
        }
        $job = array_merge([
            'id' => $id,
            'state' => 'uploaded', // uploaded | generating | done | error
            'progress_chars' => 0,
            'error' => null,
            'paid' => false,
            'order_id' => null,
            'payment_id' => null,
            'created_at' => time(),
            'updated_at' => time(),
        ], $data);
        self::write($id, $job);
        return $job;
    }

    public static function load(string $id): ?array
    {
        $file = self::dir($id) . '/meta.json';
        if (!is_file($file)) {
            return null;
        }
        $fp = fopen($file, 'rb');
        if ($fp === false) {
            return null;
        }
        flock($fp, LOCK_SH);
        $json = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $job = json_decode((string) $json, true);
        return is_array($job) ? $job : null;
    }

    /**
     * Atomically read-modify-write a job. The callback receives the job and returns the new job.
     */
    public static function update(string $id, callable $fn): array
    {
        $file = self::dir($id) . '/meta.json';
        $fp = fopen($file, 'c+b');
        if ($fp === false) {
            throw new RuntimeException('Job not found');
        }
        try {
            flock($fp, LOCK_EX);
            $job = json_decode((string) stream_get_contents($fp), true);
            if (!is_array($job)) {
                throw new RuntimeException('Corrupt job file');
            }
            $job = $fn($job);
            $job['updated_at'] = time();
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($job, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            fflush($fp);
            return $job;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public static function set(string $id, array $fields): array
    {
        return self::update($id, static fn (array $job): array => array_merge($job, $fields));
    }

    private static function write(string $id, array $job): void
    {
        file_put_contents(
            self::dir($id) . '/meta.json',
            json_encode($job, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    public static function htmlPath(string $id): string
    {
        return self::dir($id) . '/site.html';
    }

    /** Public view of a job for the browser (never includes file paths or IP data). */
    public static function publicView(array $job): array
    {
        return [
            'id' => $job['id'],
            'state' => $job['state'],
            'progress_chars' => (int) ($job['progress_chars'] ?? 0),
            'error' => $job['error'] ?? null,
            'title' => $job['title'] ?? null,
            'paid' => (bool) ($job['paid'] ?? false),
        ];
    }

    /** Deletes jobs older than keep_jobs_days. Called occasionally, not on every request. */
    public static function cleanup(): void
    {
        $days = (int) (config('keep_jobs_days') ?: 30);
        $cutoff = time() - $days * 86400;
        $root = storage_path('jobs');
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $id = basename($dir);
            if (!self::isValidId($id)) {
                continue;
            }
            $meta = $dir . '/meta.json';
            $mtime = is_file($meta) ? (int) filemtime($meta) : 0;
            if ($mtime < $cutoff) {
                foreach (glob($dir . '/*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($dir);
            }
        }
    }
}
