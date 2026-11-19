<?php
declare(strict_types=1);

/**
 * Write a private file through a complete, flushed temporary file and atomic rename.
 * The destination is never opened/truncated in place.
 */
function uttc_atomic_write_file(string $path, string $contents, int $mode = 0600): bool
{
    $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
    $handle = @fopen($temporary, 'xb');
    if (!$handle) return false;

    $ok = @chmod($temporary, $mode);
    $length = strlen($contents);
    $written = 0;
    while ($ok && $written < $length) {
        $count = @fwrite($handle, substr($contents, $written));
        if ($count === false || $count === 0) {
            $ok = false;
            break;
        }
        $written += $count;
    }
    if ($ok) $ok = @fflush($handle);
    if (function_exists('fsync') && $ok) $ok = @fsync($handle);
    if (!@fclose($handle)) $ok = false;

    if (!$ok || $written !== $length || !@rename($temporary, $path)) {
        @unlink($temporary);
        return false;
    }
    return true;
}
