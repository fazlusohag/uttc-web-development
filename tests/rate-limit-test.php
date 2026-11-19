<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/rate-limit.php';

$dir = sys_get_temp_dir() . '/uttc-rate-limit-' . bin2hex(random_bytes(6));
if (!mkdir($dir, 0700, true)) throw new RuntimeException('Could not create test directory');
$file = $dir . '/counter.json';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
};

try {
    $assert(uttc_rate_limit_attempt($file, 1000, 600, 2) === true, 'first request should be allowed');
    $assert(uttc_rate_limit_attempt($file, 1001, 600, 2) === true, 'second request should be allowed');
    $assert(uttc_rate_limit_attempt($file, 1002, 600, 2) === false, 'request at limit should be rejected');
    $assert(uttc_rate_limit_attempt($file, 1600, 600, 2) === true, 'expired window should reset');

    $corrupt = '{invalid-json';
    file_put_contents($file, $corrupt);
    $assert(uttc_rate_limit_attempt($file, 1601, 600, 2) === null, 'corrupt JSON must fail closed');
    $assert(file_get_contents($file) === $corrupt, 'corrupt counter bytes must remain untouched');

    $invalidShape = json_encode(['start' => '1000', 'hits' => 1]);
    file_put_contents($file, $invalidShape);
    $assert(uttc_rate_limit_attempt($file, 1602, 600, 2) === null, 'invalid counter types must fail closed');
    $assert(file_get_contents($file) === $invalidShape, 'invalid counter data must remain untouched');

    $victim = $dir . '/victim.json';
    $link = $dir . '/linked-counter.json';
    $victimBytes = '{"keep":"untouched"}';
    file_put_contents($victim, $victimBytes);
    if (@symlink($victim, $link)) {
        $assert(uttc_rate_limit_attempt($link, 1603, 600, 2) === null, 'symlinked counter path must fail closed');
        $assert(file_get_contents($victim) === $victimBytes, 'symlink target must remain untouched');
        unlink($link);
    }

    echo "OK: rate-limit increment, threshold, expiry, corrupt, invalid and symlink storage checks passed.\n";
} finally {
    if (is_file($file)) unlink($file);
    rmdir($dir);
}
