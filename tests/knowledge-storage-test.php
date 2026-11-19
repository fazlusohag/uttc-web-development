<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/storage.php';

$root = sys_get_temp_dir() . '/uttc-storage-' . bin2hex(random_bytes(6));
if (!mkdir($root, 0700)) throw new RuntimeException('Could not create test directory');
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
};
try {
    $target = $root . '/knowledge-base.json';
    $initial = json_encode(['entries'=>[['id'=>'before']]], JSON_PRETTY_PRINT);
    $assert(is_string($initial) && uttc_atomic_write_file($target, $initial), 'initial atomic write succeeds');
    $large = json_encode(['entries'=>[['id'=>'after','answer'=>str_repeat('বাংলা তথ্য ', 50000)]]], JSON_UNESCAPED_UNICODE);
    $assert(is_string($large) && uttc_atomic_write_file($target, $large), 'large replacement completes');
    $assert(file_get_contents($target) === $large, 'destination contains every byte of replacement');
    $decoded = json_decode((string)file_get_contents($target), true);
    $assert(json_last_error() === JSON_ERROR_NONE && ($decoded['entries'][0]['id'] ?? '') === 'after', 'replacement remains valid JSON');

    $directoryTarget = $root . '/existing-directory';
    mkdir($directoryTarget, 0700);
    $assert(!uttc_atomic_write_file($directoryTarget, 'must not replace directory'), 'rename failure is reported');
    $assert(is_dir($directoryTarget), 'failed replacement leaves destination directory intact');

    $missingParentTarget = $root . '/missing-parent/file.json';
    $assert(!uttc_atomic_write_file($missingParentTarget, 'no parent'), 'unavailable storage path fails safely');
    $assert(file_get_contents($target) === $large, 'unrelated live file remains unchanged after failures');

    $api = (string)file_get_contents(dirname(__DIR__) . '/api/admin.php');
    $assert(str_contains($api, "require_once __DIR__ . '/storage.php';"), 'admin API loads atomic storage helper');
    $assert(substr_count($api, 'uttc_atomic_write_file(') >= 2, 'both history and Knowledge Base writes use atomic helper');
    $assert(str_contains($api, 'Knowledge Base History নষ্ট বা পড়া যাচ্ছে না'), 'history endpoint fails closed on invalid history');
    $assert(str_contains($api, 'Knowledge Base-এ অবৈধ রেকর্ড পাওয়া গেছে'), 'malformed individual entries fail closed');
    $assert(str_contains($api, 'foreach ($data[\'entries\'] as $entry)'), 'every stored entry is validated before any save can discard it');
    $assert(!str_contains($api, "array_filter(\$data['entries'], 'is_array')"), 'malformed entries are never silently discarded');
    echo "Knowledge Base atomic storage tests passed.\n";
} finally {
    foreach (glob($root . '/*') ?: [] as $path) {
        if (is_dir($path) && !is_link($path)) {
            foreach (glob($path . '/*') ?: [] as $child) @unlink($child);
            @rmdir($path);
        } else @unlink($path);
    }
    @rmdir($root);
}
