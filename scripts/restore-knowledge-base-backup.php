<?php
declare(strict_types=1);

/**
 * Explicit CLI-only Knowledge Base restore.
 * Usage: php scripts/restore-knowledge-base-backup.php <snapshot-id>
 * Restores Knowledge Base and optional revision-history JSON only.
 * Uploaded source documents are deliberately left untouched.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}
$id = $argv[1] ?? '';
if (!preg_match('/^20[0-9]{6}-[0-9]{6}-[a-f0-9]{8}$/D', $id)) {
    fwrite(STDERR, "Provide a valid snapshot ID.\n");
    exit(2);
}
$root = dirname(__DIR__);
$data = $root . '/api/data';
$backupRoot = $data . '/backups';
if (!is_dir($data) || is_link($data) || !is_file($data . '/.htaccess')
    || !is_dir($backupRoot) || is_link($backupRoot)) {
    fwrite(STDERR, "Private Knowledge Base storage could not be verified.\n");
    exit(1);
}
$target = realpath($backupRoot . '/' . $id);
$base = realpath($backupRoot);
if ($target === false || $base === false || dirname($target) !== $base || is_link($backupRoot . '/' . $id)) {
    fwrite(STDERR, "Selected snapshot was not found.\n");
    exit(1);
}

/* Verify the chosen snapshot before any live operation. */
$verify = proc_open(
    [PHP_BINARY, $root . '/scripts/verify-knowledge-base-backup.php', $id],
    [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']],
    $pipes,
    $root
);
if (!is_resource($verify)) {
    fwrite(STDERR, "Could not start snapshot verification.\n");
    exit(1);
}
fclose($pipes[0]);
$verifyOut = stream_get_contents($pipes[1]);
$verifyErr = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
$verifyStatus = proc_close($verify);
if ($verifyStatus !== 0) {
    fwrite(STDERR, "Snapshot verification failed; live data was not changed.\n" . $verifyErr);
    exit(1);
}
$manifest = json_decode((string)file_get_contents($target . '/manifest.json'), true);
$restoreFiles = [];
foreach (['knowledge-base.json', 'knowledge-history.json'] as $name) {
    if ($name === 'knowledge-base.json' && !isset($manifest['files'])) {
        fwrite(STDERR, "Snapshot manifest is unavailable.\n"); exit(1);
    }
    foreach ($manifest['files'] as $entry) {
        if (($entry['path'] ?? null) === $name) {
            $restoreFiles[$name] = $target . '/' . $name;
        }
    }
}
if (!isset($restoreFiles['knowledge-base.json'])) {
    fwrite(STDERR, "Snapshot has no Knowledge Base JSON; live data was not changed.\n");
    exit(1);
}

/* Record the live state before the safety backup; compare again under exclusive lock. */
$liveNames = ['knowledge-base.json', 'knowledge-history.json'];
$before = [];
foreach ($liveNames as $name) {
    $path = $data . '/' . $name;
    if (is_link($path)) { fwrite(STDERR, "Unsafe live file: {$name}\n"); exit(1); }
    $before[$name] = is_file($path) ? hash_file('sha256', $path) : null;
}
$backup = proc_open(
    [PHP_BINARY, $root . '/scripts/backup-knowledge-base.php'],
    [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']],
    $bpipes,
    $root
);
if (!is_resource($backup)) { fwrite(STDERR, "Could not create pre-restore backup.\n"); exit(1); }
fclose($bpipes[0]);
$backupOut = stream_get_contents($bpipes[1]);
$backupErr = stream_get_contents($bpipes[2]);
fclose($bpipes[1]); fclose($bpipes[2]);
$backupStatus = proc_close($backup);
if ($backupStatus !== 0 || !preg_match('/Backup created: ([0-9]{8}-[0-9]{6}-[a-f0-9]{8})/', (string)$backupOut, $match)) {
    fwrite(STDERR, "Pre-restore backup failed; live data was not changed.\n" . $backupErr);
    exit(1);
}
$preRestoreId = $match[1];
$preVerify = proc_open(
    [PHP_BINARY, $root . '/scripts/verify-knowledge-base-backup.php', $preRestoreId],
    [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']],
    $vpipes,
    $root
);
if (!is_resource($preVerify)) { fwrite(STDERR, "Could not verify pre-restore backup.\n"); exit(1); }
fclose($vpipes[0]);
$preOut = stream_get_contents($vpipes[1]); $preErr = stream_get_contents($vpipes[2]);
fclose($vpipes[1]); fclose($vpipes[2]);
if (proc_close($preVerify) !== 0) {
    fwrite(STDERR, "Pre-restore backup verification failed; live data was not changed.\n" . $preErr);
    exit(1);
}

$lock = @fopen($data . '/write.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX)) {
    if (is_resource($lock)) fclose($lock);
    fwrite(STDERR, "Could not acquire exclusive Knowledge Base lock.\n");
    exit(1);
}
$changed = false;
foreach ($liveNames as $name) {
    $path = $data . '/' . $name;
    if (is_link($path)) { $changed = true; continue; }
    $now = is_file($path) ? hash_file('sha256', $path) : null;
    if ($now === false || $now !== $before[$name]) $changed = true;
}
if ($changed) {
    flock($lock, LOCK_UN); fclose($lock);
    fwrite(STDERR, "Live data changed while preparing safety backup. Retry restore; no files were replaced.\n");
    exit(1);
}

$stage = $data . '/.restore-' . bin2hex(random_bytes(8));
if (!@mkdir($stage, 0700)) {
    flock($lock, LOCK_UN); fclose($lock);
    fwrite(STDERR, "Could not create protected restore staging area.\n"); exit(1);
}
$ok = true;
foreach ($restoreFiles as $name => $source) {
    $raw = @file_get_contents($source);
    $decoded = $raw === false ? null : json_decode($raw, true);
    $key = $name === 'knowledge-base.json' ? 'entries' : 'versions';
    if ($raw === false || json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)
        || !isset($decoded[$key]) || !is_array($decoded[$key])
        || @file_put_contents($stage . '/' . $name, $raw, LOCK_EX) !== strlen($raw)
        || !@chmod($stage . '/' . $name, 0600)) {
        $ok = false; break;
    }
}
/* Stage the absent-history case as an explicit removal marker. */
$rollback = [];
$installed = [];
$replacementStarted = false;
if ($ok) {
    $replacementStarted = true;
    foreach ($liveNames as $name) {
        $live = $data . '/' . $name;
        if (is_file($live)) {
            $old = $stage . '/old-' . $name;
            if (!@rename($live, $old)) { $ok = false; break; }
            $rollback[$name] = $old;
        } elseif (is_link($live)) { $ok = false; break; }
    }
}
if ($ok) {
    foreach ($restoreFiles as $name => $_source) {
        if (!@rename($stage . '/' . $name, $data . '/' . $name)) { $ok = false; break; }
        $installed[$name] = true;
    }
}
if (!$ok && $replacementStarted) {
    foreach ($liveNames as $name) {
        $live = $data . '/' . $name;
        if (isset($rollback[$name])) {
            if (isset($installed[$name]) && (is_file($live) || is_link($live))) @unlink($live);
            if (is_file($rollback[$name])) @rename($rollback[$name], $live);
        } elseif (isset($installed[$name]) && (is_file($live) || is_link($live))) {
            @unlink($live);
        }
    }
}
flock($lock, LOCK_UN);
fclose($lock);

/* Remove the private staging directory and its rollback copies. */
foreach (new FilesystemIterator($stage) as $item) {
    if ($item->isFile() || $item->isLink()) @unlink($item->getPathname());
}
@rmdir($stage);
if (!$ok) {
    fwrite(STDERR, "Restore failed; rollback was attempted. Verify the pre-restore snapshot {$preRestoreId} before any retry.\n");
    exit(1);
}
echo "Restore completed.\nSnapshot restored: {$id}\nPre-restore safety snapshot: {$preRestoreId}\n";
echo "Restored: knowledge-base.json" . (isset($restoreFiles['knowledge-history.json']) ? ", knowledge-history.json" : "; revision history removed because it was absent from the selected snapshot") . "\n";
echo "Uploaded source documents were not changed.\n";
