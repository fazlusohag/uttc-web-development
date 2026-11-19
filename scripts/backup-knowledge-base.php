<?php
declare(strict_types=1);

/**
 * Create a private, consistent snapshot of the AI Knowledge Base.
 * Run from CLI only: php scripts/backup-knowledge-base.php
 * Optional: UTTC_BACKUP_RETENTION=30 (minimum 1).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

$root = dirname(__DIR__);
$dataDir = $root . '/api/data';
if (!is_dir($dataDir) || is_link($dataDir)) {
    fwrite(STDERR, "Knowledge Base data directory is missing or unsafe.\n");
    exit(1);
}
$realData = realpath($dataDir);
if ($realData === false || is_link($realData . '/.htaccess') || !is_file($realData . '/.htaccess')) {
    fwrite(STDERR, "Private storage protection could not be verified (.htaccess missing).\n");
    exit(1);
}

$lock = @fopen($realData . '/write.lock', 'c');
if (!$lock || !flock($lock, LOCK_SH)) {
    if (is_resource($lock)) fclose($lock);
    fwrite(STDERR, "Could not acquire the Knowledge Base read lock.\n");
    exit(1);
}

$backupRoot = $realData . '/backups';
if (!is_dir($backupRoot) && !@mkdir($backupRoot, 0700, true) && !is_dir($backupRoot)) {
    flock($lock, LOCK_UN); fclose($lock);
    fwrite(STDERR, "Could not create private backup directory.\n");
    exit(1);
}
if (is_link($backupRoot) || !@chmod($backupRoot, 0700)) {
    flock($lock, LOCK_UN); fclose($lock);
    fwrite(STDERR, "Backup directory permissions or path are unsafe.\n");
    exit(1);
}

$stamp = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
$staging = $backupRoot . '/.tmp-' . $stamp;
$final = $backupRoot . '/' . $stamp;
if (!@mkdir($staging, 0700)) {
    flock($lock, LOCK_UN); fclose($lock);
    fwrite(STDERR, "Could not create backup staging directory.\n");
    exit(1);
}
@chmod($staging, 0700);

$files = [];
$totalBytes = 0;
$maxBytes = 100 * 1024 * 1024;
$failed = false;

$writeCompleteFile = static function (string $path, string $contents): bool {
    $handle = @fopen($path, 'xb');
    if (!$handle) return false;
    $length = strlen($contents);
    $written = 0;
    $ok = true;
    while ($written < $length) {
        $count = @fwrite($handle, substr($contents, $written));
        if ($count === false || $count === 0) { $ok = false; break; }
        $written += $count;
    }
    if ($ok) $ok = @fflush($handle);
    if (function_exists('fsync') && $ok) $ok = @fsync($handle);
    if (!@fclose($handle)) $ok = false;
    if (!$ok || $written !== $length || !@chmod($path, 0600)) {
        @unlink($path);
        return false;
    }
    return true;
};

$copyFile = static function (string $source, string $relative) use ($staging, &$files, &$totalBytes, $maxBytes): bool {
    if (is_link($source) || !is_file($source) || !is_readable($source)) return false;
    $size = filesize($source);
    if ($size === false || $size > $maxBytes || $totalBytes + $size > $maxBytes) return false;
    $destination = $staging . '/' . $relative;
    $parent = dirname($destination);
    if (!is_dir($parent) && !@mkdir($parent, 0700, true)) return false;
    $input = @fopen($source, 'rb');
    $output = @fopen($destination, 'xb');
    if (!$input || !$output) {
        if (is_resource($input)) fclose($input);
        if (is_resource($output)) fclose($output);
        return false;
    }
    $copied = stream_copy_to_stream($input, $output);
    $ok = $copied === $size && fflush($output);
    if (function_exists('fsync') && $ok) $ok = fsync($output);
    fclose($input);
    if (!fclose($output)) $ok = false;
    if (!$ok || !@chmod($destination, 0600)) {
        @unlink($destination);
        return false;
    }
    $hash = hash_file('sha256', $destination);
    if ($hash === false) return false;
    $files[] = ['path'=>$relative, 'bytes'=>$size, 'sha256'=>$hash];
    $totalBytes += $size;
    return true;
};

if (!is_file($realData . '/knowledge-base.json') || is_link($realData . '/knowledge-base.json')) {
    $failed = true;
    fwrite(STDERR, "Knowledge Base source file is missing or unsafe.\\n");
}
foreach (['knowledge-base.json', 'knowledge-history.json'] as $name) {
    $source = $realData . '/' . $name;
    if (!is_file($source)) continue;
    $raw = @file_get_contents($source);
    $decoded = $raw === false ? null : json_decode($raw, true);
    $key = $name === 'knowledge-base.json' ? 'entries' : 'versions';
    if ($raw === false || json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || !isset($decoded[$key]) || !is_array($decoded[$key])) {
        $failed = true;
        fwrite(STDERR, "Refusing to back up invalid {$name}.\n");
        break;
    }
    if (!$copyFile($source, $name)) {
        $failed = true;
        fwrite(STDERR, "Could not safely copy {$name}.\n");
        break;
    }
}
$uploads = $realData . '/uploads';
if (!$failed && is_dir($uploads)) {
    if (is_link($uploads)) {
        $failed = true;
    } else {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $relative = 'uploads/' . substr($path, strlen($uploads) + 1);
            if ($item->isLink()) { $failed = true; break; }
            if ($item->isDir()) {
                if (!is_dir($staging . '/' . $relative) && !@mkdir($staging . '/' . $relative, 0700, true)) { $failed = true; break; }
            } elseif (!$copyFile($path, $relative)) {
                $failed = true;
                break;
            }
        }
    }
}
if (!$files) $failed = true;

if (!$failed) {
    $manifest = json_encode([
        'created_at'=>gmdate('c'),
        'format_version'=>1,
        'file_count'=>count($files),
        'total_bytes'=>$totalBytes,
        'files'=>$files
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $manifestPath = $staging . '/manifest.json';
    if ($manifest === false || !$writeCompleteFile($manifestPath, $manifest)
        || !$writeCompleteFile($staging . '/.htaccess', "Require all denied\n")
        || !$writeCompleteFile($staging . '/index.html', '')
        || !@rename($staging, $final)) {
        $failed = true;
    }
}
flock($lock, LOCK_UN);
fclose($lock);

if ($failed) {
    $remove = static function (string $path) use (&$remove): void {
        if (is_dir($path) && !is_link($path)) {
            foreach (new FilesystemIterator($path) as $child) $remove($child->getPathname());
            @rmdir($path);
        } else {
            @unlink($path);
        }
    };
    $remove($staging);
    fwrite(STDERR, "Backup failed; no completed snapshot was published.\n");
    exit(1);
}

$retention = filter_var(getenv('UTTC_BACKUP_RETENTION') ?: '30', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>365]]);
if ($retention === false) $retention = 30;
$completed = glob($backupRoot . '/20[0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9][0-9]-*', GLOB_ONLYDIR) ?: [];
usort($completed, static fn(string $a, string $b): int => strcmp(basename($b), basename($a)));
foreach (array_slice($completed, $retention) as $old) {
    if (is_link($old)) continue;
    $remove = static function (string $path) use (&$remove): void {
        if (is_dir($path) && !is_link($path)) {
            foreach (new FilesystemIterator($path) as $child) $remove($child->getPathname());
            @rmdir($path);
        } else @unlink($path);
    };
    $remove($old);
}
echo "Backup created: " . basename($final) . " (" . count($files) . " files, {$totalBytes} bytes)\n";
