<?php
declare(strict_types=1);

/**
 * Read-only verification of a Knowledge Base backup snapshot.
 * Usage: php scripts/verify-knowledge-base-backup.php <snapshot-id>
 * This utility never restores or modifies live data.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}
$id = $argv[1] ?? '';
if (!preg_match('/^20[0-9]{6}-[0-9]{6}-[a-f0-9]{8}$/D', $id)) {
    fwrite(STDERR, "Provide a valid snapshot ID (the directory name under api/data/backups).\n");
    exit(2);
}
$root = dirname(__DIR__);
$backupRoot = $root . '/api/data/backups';
if (!is_dir($backupRoot) || is_link($backupRoot) || is_link(dirname($backupRoot) . '/.htaccess') || !is_file(dirname($backupRoot) . '/.htaccess')) {
    fwrite(STDERR, "Private backup storage could not be verified.\n");
    exit(1);
}
$base = realpath($backupRoot);
$dir = $base === false ? false : realpath($base . '/' . $id);
if ($dir === false || dirname($dir) !== $base || is_link($base . '/' . $id)) {
    fwrite(STDERR, "Snapshot does not exist or is outside the backup directory.\n");
    exit(1);
}
$accessFile = $dir . '/.htaccess';
$indexFile = $dir . '/index.html';
if (is_link($accessFile) || !is_file($accessFile) || !is_readable($accessFile)) {
    fwrite(STDERR, "Snapshot access protection is missing or unsafe.\n");
    exit(1);
}
$accessRules = @file_get_contents($accessFile);
if ($accessRules === false || !preg_match('/^\s*Require\s+all\s+denied\s*$/mi', $accessRules)) {
    fwrite(STDERR, "Snapshot access-denial rule is missing or invalid.\n");
    exit(1);
}
if (is_link($indexFile) || !is_file($indexFile) || filesize($indexFile) !== 0) {
    fwrite(STDERR, "Snapshot index guard is missing, unsafe, or not empty.\n");
    exit(1);
}
$manifestPath = $dir . '/manifest.json';
if (is_link($manifestPath) || !is_file($manifestPath) || !is_readable($manifestPath)) {
    fwrite(STDERR, "Snapshot manifest is missing or unsafe.\n");
    exit(1);
}
$raw = file_get_contents($manifestPath);
$manifest = $raw === false ? null : json_decode($raw, true);
if ($raw === false || json_last_error() !== JSON_ERROR_NONE || !is_array($manifest)
    || ($manifest['format_version'] ?? null) !== 1
    || !is_array($manifest['files'] ?? null)
    || !isset($manifest['file_count'], $manifest['total_bytes'])
    || !is_int($manifest['file_count']) || !is_int($manifest['total_bytes'])
    || $manifest['file_count'] < 1 || $manifest['total_bytes'] < 0
    || count($manifest['files']) !== $manifest['file_count']) {
    fwrite(STDERR, "Snapshot manifest is invalid.\n");
    exit(1);
}
$seen = [];
$total = 0;
$verified = 0;
foreach ($manifest['files'] as $entry) {
    if (!is_array($entry) || !is_string($entry['path'] ?? null)
        || !is_int($entry['bytes'] ?? null) || $entry['bytes'] < 0
        || !is_string($entry['sha256'] ?? null)
        || !preg_match('/^[a-f0-9]{64}$/D', $entry['sha256'])) {
        fwrite(STDERR, "Manifest contains an invalid file record.\n");
        exit(1);
    }
    $relative = $entry['path'];
    if (isset($seen[$relative]) || !preg_match('~^(?:knowledge-base\.json|knowledge-history\.json|uploads/[A-Za-z0-9._/-]+)$~D', $relative)
        || str_contains($relative, '..') || str_contains($relative, '//') || str_ends_with($relative, '/')
        || preg_match('~(?:^|/)\\.(?:/|$)~', $relative)) {
        fwrite(STDERR, "Manifest contains a duplicate or unsafe path.\n");
        exit(1);
    }
    $seen[$relative] = true;
    $path = $dir . '/' . $relative;
    $cursor = $dir;
    foreach (explode('/', $relative) as $segment) {
        $cursor .= '/' . $segment;
        if (is_link($cursor)) {
            fwrite(STDERR, "A listed backup path contains a symbolic link: {$relative}\\n");
            exit(1);
        }
    }
    $real = realpath($path);
    if ($real === false || !str_starts_with($real, $dir . '/') || !is_file($path) || !is_readable($path)) {
        fwrite(STDERR, "A listed backup file is missing or unsafe: {$relative}\n");
        exit(1);
    }
    $size = filesize($path);
    $hash = hash_file('sha256', $path);
    if ($size !== $entry['bytes'] || $hash === false || !hash_equals($entry['sha256'], $hash)) {
        fwrite(STDERR, "Checksum or size mismatch: {$relative}\n");
        exit(1);
    }
    if ($relative === 'knowledge-base.json' || $relative === 'knowledge-history.json') {
        $json = file_get_contents($path);
        $decoded = $json === false ? null : json_decode($json, true);
        $key = $relative === 'knowledge-base.json' ? 'entries' : 'versions';
        if ($json === false || json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || !isset($decoded[$key]) || !is_array($decoded[$key])) {
            fwrite(STDERR, "Backup JSON structure is invalid: {$relative}\n");
            exit(1);
        }
    }
    $total += $size;
    $verified++;
}
if (!isset($seen['knowledge-base.json'])) {
    fwrite(STDERR, "Snapshot does not contain the required Knowledge Base file.\\n");
    exit(1);
}
if ($total !== $manifest['total_bytes']) {
    fwrite(STDERR, "Manifest total byte count does not match verified files.\n");
    exit(1);
}
echo "Backup verification successful (read-only).\n";
echo "Snapshot: {$id}\nFiles verified: {$verified}\nTotal bytes: {$total}\n";
echo "No live data was changed.\n";
