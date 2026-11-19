<?php
declare(strict_types=1);

/**
 * Record one login attempt for an IP bucket.
 * Returns true when throttled or when storage is invalid/unavailable.
 */
function uttc_login_attempt_throttled(
    string $file,
    string $ip,
    int $now,
    int $window = 900,
    int $limit = 5,
    int $maxBuckets = 1000
): bool {
    // Do not follow attacker-planted links in the protected storage directory.
    if (is_link($file) || (file_exists($file) && !is_file($file))) return true;
    $fp = @fopen($file, 'c+');
    if (!$fp) return true;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return true; }

    $raw = stream_get_contents($fp);
    if ($raw === false) { flock($fp, LOCK_UN); fclose($fp); return true; }
    $all = [];
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            flock($fp, LOCK_UN); fclose($fp);
            return true;
        }
        foreach ($decoded as $key => $bucket) {
            if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key)
                || !is_array($bucket)
                || !isset($bucket['start'], $bucket['hits'])
                || !is_int($bucket['start']) || !is_int($bucket['hits'])
                || $bucket['start'] < 0 || $bucket['start'] > $now
                || $bucket['hits'] < 0) {
                flock($fp, LOCK_UN); fclose($fp);
                return true;
            }
            if ($now - $bucket['start'] < $window) $all[$key] = $bucket;
        }
    }

    if (!isset($all[$ip]) && count($all) >= $maxBuckets) {
        uasort($all, static fn(array $a, array $b): int => $a['start'] <=> $b['start']);
        $all = array_slice($all, -($maxBuckets - 1), null, true);
    }
    $row = $all[$ip] ?? ['start' => $now, 'hits' => 0];
    if ($row['hits'] >= $limit) {
        flock($fp, LOCK_UN); fclose($fp);
        return true;
    }
    $all[$ip] = ['start' => $row['start'], 'hits' => $row['hits'] + 1];
    $json = json_encode($all);
    if ($json === false || !ftruncate($fp, 0) || !rewind($fp)) {
        flock($fp, LOCK_UN); fclose($fp);
        return true;
    }
    $length = strlen($json);
    $written = 0;
    while ($written < $length) {
        $n = fwrite($fp, substr($json, $written));
        if ($n === false || $n === 0) {
            flock($fp, LOCK_UN); fclose($fp);
            return true;
        }
        $written += $n;
    }
    $ok = fflush($fp);
    flock($fp, LOCK_UN); fclose($fp);
    return !$ok;
}

/** Clear a successful login's bucket; returns false on any storage failure. */
function uttc_clear_login_attempts(string $file, string $ip): bool {
    if (is_link($file) || (file_exists($file) && !is_file($file))) return false;
    $fp = @fopen($file, 'c+');
    if (!$fp) return false;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
    $raw = stream_get_contents($fp);
    if ($raw === false) { flock($fp, LOCK_UN); fclose($fp); return false; }
    $all = [];
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            flock($fp, LOCK_UN); fclose($fp);
            return false;
        }
        foreach ($decoded as $key => $bucket) {
            if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key)
                || !is_array($bucket)
                || !isset($bucket['start'], $bucket['hits'])
                || !is_int($bucket['start']) || !is_int($bucket['hits'])
                || $bucket['start'] < 0 || $bucket['hits'] < 0) {
                flock($fp, LOCK_UN); fclose($fp);
                return false;
            }
        }
        $all = $decoded;
    }
    unset($all[$ip]);
    $json = json_encode($all);
    if ($json === false || !ftruncate($fp, 0) || !rewind($fp)) {
        flock($fp, LOCK_UN); fclose($fp);
        return false;
    }
    $length = strlen($json);
    $written = 0;
    while ($written < $length) {
        $n = fwrite($fp, substr($json, $written));
        if ($n === false || $n === 0) {
            flock($fp, LOCK_UN); fclose($fp);
            return false;
        }
        $written += $n;
    }
    $ok = fflush($fp);
    flock($fp, LOCK_UN); fclose($fp);
    return $ok;
}
