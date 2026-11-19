<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/login-throttle.php';

$dir = sys_get_temp_dir() . '/uttc-login-throttle-' . bin2hex(random_bytes(6));
if (!mkdir($dir, 0700, true)) throw new RuntimeException('Could not create test directory');
$file = $dir . '/login-attempts.json';
$ip = hash('sha256', 'fixture-ip');
$other = hash('sha256', 'other-ip');
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
};

try {
    $assert(uttc_login_attempt_throttled($file, $ip, 1000, 900, 2, 1000) === false, 'first attempt should be allowed');
    $assert(uttc_login_attempt_throttled($file, $ip, 1001, 900, 2, 1000) === false, 'second attempt should be allowed');
    $assert(uttc_login_attempt_throttled($file, $ip, 1002, 900, 2, 1000) === true, 'attempt after threshold should be throttled');

    $assert(uttc_login_attempt_throttled($file, $ip, 1900, 900, 2, 1000) === false, 'expired window should reset');

    $valid = [
        $ip => ['start' => 1900, 'hits' => 1],
        $other => ['start' => 1900, 'hits' => 2],
    ];
    file_put_contents($file, json_encode($valid));
    $assert(uttc_clear_login_attempts($file, $ip), 'successful login should clear its own bucket');
    $afterClear = json_decode((string)file_get_contents($file), true);
    $assert(!isset($afterClear[$ip]) && isset($afterClear[$other]), 'clear must preserve other IP buckets');

    $corrupt = '{invalid-json';
    file_put_contents($file, $corrupt);
    $assert(uttc_login_attempt_throttled($file, $ip, 2000) === true, 'corrupt JSON must fail closed');
    $assert(file_get_contents($file) === $corrupt, 'corrupt JSON bytes must remain untouched');
    $assert(uttc_clear_login_attempts($file, $ip) === false, 'clear must not rewrite corrupt JSON');
    $assert(file_get_contents($file) === $corrupt, 'clear must preserve corrupt JSON bytes');

    $invalid = json_encode([$ip => ['start' => '1900', 'hits' => 1]]);
    file_put_contents($file, $invalid);
    $assert(uttc_login_attempt_throttled($file, $ip, 2001) === true, 'invalid counter types must fail closed');
    $assert(file_get_contents($file) === $invalid, 'invalid counter data must remain untouched');

    $future = json_encode([$ip => ['start' => 99999, 'hits' => 1]]);
    file_put_contents($file, $future);
    $assert(uttc_login_attempt_throttled($file, $ip, 2001) === true, 'future timestamps must fail closed');

    echo "OK: admin login throttle threshold, expiry, successful clear, corrupt and invalid storage checks passed.\n";
} finally {
    if (is_file($file)) unlink($file);
    rmdir($dir);
}
