<?php
declare(strict_types=1);

/** Isolated end-to-end tests for Knowledge Base backup, verification and restore. */
$sourceRoot = dirname(__DIR__);
$temp = sys_get_temp_dir() . '/uttc-kb-restore-' . bin2hex(random_bytes(6));
$must = static function (bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException("FAIL: {$message}"); }
};
$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path) && !is_link($path)) return;
    if (is_dir($path) && !is_link($path)) {
        foreach (new FilesystemIterator($path) as $item) $removeTree($item->getPathname());
        @rmdir($path);
    } else @unlink($path);
};
$run = static function (string $script, array $args = []) use ($temp): array {
    $command = array_merge([PHP_BINARY, $temp . '/scripts/' . $script], $args);
    $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $temp);
    if (!is_resource($process)) throw new RuntimeException('Could not start ' . $script);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($process), (string)$stdout, (string)$stderr];
};
try {
    foreach (['scripts','api/data/uploads'] as $dir) {
        if (!@mkdir($temp . '/' . $dir, 0700, true) && !is_dir($temp . '/' . $dir)) {
            throw new RuntimeException('Could not create fixture directory');
        }
    }
    foreach (['backup-knowledge-base.php','verify-knowledge-base-backup.php','restore-knowledge-base-backup.php'] as $file) {
        if (!copy($sourceRoot . '/scripts/' . $file, $temp . '/scripts/' . $file)) {
            throw new RuntimeException('Could not copy ' . $file);
        }
    }
    file_put_contents($temp . '/api/data/.htaccess', "Require all denied\n");
    file_put_contents($temp . '/api/data/knowledge-base.json', json_encode(['entries'=>[['id'=>'old','answer'=>'snapshot value']]], JSON_PRETTY_PRINT));
    file_put_contents($temp . '/api/data/knowledge-history.json', json_encode(['versions'=>[['id'=>'v1']]], JSON_PRETTY_PRINT));
    file_put_contents($temp . '/api/data/uploads/original.txt', 'snapshot upload');
    [$status,$out,$err] = $run('backup-knowledge-base.php');
    $must($status === 0 && preg_match('/Backup created: ([0-9]{8}-[0-9]{6}-[a-f0-9]{8})/', $out, $m) === 1, 'initial backup should complete: ' . $err);
    $snapshot = $m[1];
    $snapshotDir = $temp . '/api/data/backups/' . $snapshot;
    $must(is_file($snapshotDir . '/.htaccess'), 'completed snapshot must include access denial');
    $must(is_file($snapshotDir . '/index.html') && filesize($snapshotDir . '/index.html') === 0, 'empty index guard file must be published successfully');
    $guardBackup = $snapshotDir . '/.htaccess.saved';
    $must(rename($snapshotDir . '/.htaccess', $guardBackup), 'test can temporarily remove snapshot access guard');
    [$status] = $run('verify-knowledge-base-backup.php', [$snapshot]);
    $must($status !== 0, 'verifier must reject a snapshot with a missing access guard');
    $must(rename($guardBackup, $snapshotDir . '/.htaccess'), 'test restores snapshot access guard');
    $indexBackup = $snapshotDir . '/index.html.saved';
    $must(rename($snapshotDir . '/index.html', $indexBackup), 'test can temporarily remove snapshot index guard');
    [$status] = $run('verify-knowledge-base-backup.php', [$snapshot]);
    $must($status !== 0, 'verifier must reject a snapshot with a missing index guard');
    $must(rename($indexBackup, $snapshotDir . '/index.html'), 'test restores snapshot index guard');
    [$status] = $run('verify-knowledge-base-backup.php', [$snapshot]);
    $must($status === 0, 'initial snapshot should verify');

    /* Every CLI operation must reject a data directory whose access guard is missing or ineffective. */
    $guardPath = $temp . '/api/data/.htaccess';
    $validGuard = file_get_contents($guardPath);
    $must(is_string($validGuard), 'fixture access guard should be readable');
    file_put_contents($guardPath, "Options -Indexes\\n");
    [$status] = $run('backup-knowledge-base.php');
    $must($status !== 0, 'backup must reject a data directory without Require all denied');
    [$status] = $run('verify-knowledge-base-backup.php', [$snapshot]);
    $must($status !== 0, 'verifier must reject unsafe root storage protection');
    $liveGuardProbe = hash_file('sha256', $temp . '/api/data/knowledge-base.json');
    [$status] = $run('restore-knowledge-base-backup.php', [$snapshot]);
    $must($status !== 0, 'restore must reject unsafe root storage protection');
    $must(hash_file('sha256', $temp . '/api/data/knowledge-base.json') === $liveGuardProbe, 'unsafe root guard must not permit restore to alter live data');
    file_put_contents($guardPath, $validGuard);

    file_put_contents($temp . '/api/data/knowledge-base.json', json_encode(['entries'=>[['id'=>'new','answer'=>'live changed']]]));
    file_put_contents($temp . '/api/data/knowledge-history.json', json_encode(['versions'=>[['id'=>'v2']]]));
    file_put_contents($temp . '/api/data/uploads/current.txt', 'upload created after snapshot');
    [$status,$out,$err] = $run('restore-knowledge-base-backup.php', [$snapshot]);
    $must($status === 0, 'restore should succeed: ' . $err . $out);
    $restored = json_decode((string)file_get_contents($temp . '/api/data/knowledge-base.json'), true);
    $history = json_decode((string)file_get_contents($temp . '/api/data/knowledge-history.json'), true);
    $must(($restored['entries'][0]['id'] ?? '') === 'old', 'Knowledge Base JSON should return to snapshot content');
    $must(($history['versions'][0]['id'] ?? '') === 'v1', 'revision history should return to snapshot content');
    $must(file_get_contents($temp . '/api/data/uploads/current.txt') === 'upload created after snapshot', 'post-snapshot uploads must remain untouched');
    $must(file_get_contents($temp . '/api/data/uploads/original.txt') === 'snapshot upload', 'existing upload must remain untouched');
    $must(preg_match('/Pre-restore safety snapshot: ([0-9]{8}-[0-9]{6}-[a-f0-9]{8})/', $out, $pre) === 1, 'restore must report pre-restore snapshot');
    [$status] = $run('verify-knowledge-base-backup.php', [$pre[1]]);
    $must($status === 0, 'pre-restore safety snapshot should verify');

    /* Snapshot without history restores KB and removes stale live history. */
    @unlink($temp . '/api/data/knowledge-history.json');
    [$status,$out,$err] = $run('backup-knowledge-base.php');
    $must($status === 0 && preg_match('/Backup created: ([0-9]{8}-[0-9]{6}-[a-f0-9]{8})/', $out, $noHistory) === 1, 'snapshot without history should be created');
    $noHistoryId = $noHistory[1];
    $historyPath = $temp . '/api/data/backups/' . $noHistoryId . '/knowledge-history.json';
    $must(!is_file($historyPath), 'fixture snapshot should omit absent history');
    file_put_contents($temp . '/api/data/knowledge-history.json', json_encode(['versions'=>[['id'=>'stale']]]));
    [$status,$out,$err] = $run('restore-knowledge-base-backup.php', [$noHistoryId]);
    $must($status === 0 && !is_file($temp . '/api/data/knowledge-history.json'), 'restore must remove stale history absent from snapshot: ' . $err . $out);

    /* A tampered selected snapshot must be rejected before any live data changes. */
    [$status,$out,$err] = $run('backup-knowledge-base.php');
    $must($status === 0 && preg_match('/Backup created: ([0-9]{8}-[0-9]{6}-[a-f0-9]{8})/', $out, $bad) === 1, 'second backup should complete');
    file_put_contents($temp . '/api/data/knowledge-base.json', '{"entries":[{"id":"must-stay"}]}');
    $before = hash_file('sha256', $temp . '/api/data/knowledge-base.json');
    file_put_contents($temp . '/api/data/backups/' . $bad[1] . '/knowledge-base.json', '{"entries":[{"id":"tampered"}]}');
    [$status] = $run('restore-knowledge-base-backup.php', [$bad[1]]);
    $after = hash_file('sha256', $temp . '/api/data/knowledge-base.json');
    $must($status !== 0 && $before === $after, 'tampered snapshot must be rejected without changing live data');

    echo "Knowledge Base backup/verify/restore integration tests passed.\n";
} finally {
    $removeTree($temp);
}
