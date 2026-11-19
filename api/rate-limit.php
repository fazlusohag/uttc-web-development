<?php
declare(strict_types=1);

/**
 * Atomically checks and increments a per-client request counter.
 * Returns true when allowed, false when limited, and null on invalid storage/I/O.
 */
function uttc_rate_limit_attempt(string $file, int $now, int $window, int $limit): ?bool {
    // Never follow a pre-existing symlink or operate on a non-regular counter path.
    if (is_link($file) || (file_exists($file) && !is_file($file))) return null;
    $fp = @fopen($file, 'c+');
    if (!$fp) return null;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return null; }

    $raw = stream_get_contents($fp);
    $data = null;
    if ($raw === '') {
        $data = ['start' => $now, 'hits' => 0];
    } else {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)
            || !isset($decoded['start'], $decoded['hits'])
            || !is_int($decoded['start'])
            || !is_int($decoded['hits'])
            || $decoded['start'] < 0
            || $decoded['start'] > $now
            || $decoded['hits'] < 0) {
            flock($fp, LOCK_UN); fclose($fp);
            return null;
        }
        $data = $decoded;
        if ($now - $data['start'] >= $window) {
            $data = ['start' => $now, 'hits' => 0];
        }
    }

    if ($data['hits'] >= $limit) {
        flock($fp, LOCK_UN); fclose($fp);
        return false;
    }

    $encoded = json_encode(['start' => $data['start'], 'hits' => $data['hits'] + 1]);
    if ($encoded === false || !ftruncate($fp, 0) || !rewind($fp)) {
        flock($fp, LOCK_UN); fclose($fp);
        return null;
    }
    $length = strlen($encoded);
    $written = 0;
    while ($written < $length) {
        $count = fwrite($fp, substr($encoded, $written));
        if ($count === false || $count === 0) {
            flock($fp, LOCK_UN); fclose($fp);
            return null;
        }
        $written += $count;
    }
    $ok = fflush($fp);
    flock($fp, LOCK_UN); fclose($fp);
    return $ok ? true : null;
}
