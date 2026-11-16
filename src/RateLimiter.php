<?php
declare(strict_types=1);

/**
 * Simple per-IP limit on free generations, stored in files.
 * Each generation costs you Claude API money, so this stops one visitor from running up a bill.
 */
final class RateLimiter
{
    private const WINDOW = 86400; // 24 hours

    /** Records a hit and returns true if the IP is still within its limit. */
    public static function hit(string $ip): bool
    {
        $limit = (int) config('generations_per_ip_per_day');
        if ($limit <= 0) {
            return true; // 0 = unlimited
        }
        $dir = storage_path('ratelimit');
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $file = $dir . '/' . hash('sha256', $ip . '|' . (string) config('razorpay_key_secret')) . '.json';
        $fp = fopen($file, 'c+b');
        if ($fp === false) {
            return true; // fail open rather than block real customers
        }
        try {
            flock($fp, LOCK_EX);
            $hits = json_decode((string) stream_get_contents($fp), true);
            $now = time();
            $hits = array_values(array_filter(
                is_array($hits) ? $hits : [],
                static fn ($t): bool => is_int($t) && $t > $now - self::WINDOW
            ));
            if (count($hits) >= $limit) {
                return false;
            }
            $hits[] = $now;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($hits));
            return true;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
