<?php
declare(strict_types=1);

// The CI image may not include mbstring. These ASCII-only test polyfills do not
// replace the production requirement for mbstring and are used only by this test.
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string {
        return $length === null ? substr($value, $start) : substr($value, $start, $length);
    }
}
require_once __DIR__ . '/../api/retrieval.php';

function check(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$pages = [
    ['title'=>'Driving License Training','url'=>'https://example.test/driving','text'=>'Professional driving course and road safety lessons.','_source_type'=>'website'],
    ['title'=>'Computer Office Application','url'=>'https://example.test/computer','text'=>'Word Excel PowerPoint computer training.','_source_type'=>'website'],
    ['title'=>'Approved Driving Fees','url'=>'','text'=>'Driving course fee and payment details.','_source_type'=>'admin']
];
$matches = uttc_retrieve('driving license', $pages);
check(count($matches) === 2, 'retrieval should return only documents with query terms');
check($matches[0]['title'] === 'Driving License Training', 'title matches should rank first');
check($matches[0]['score'] > $matches[1]['score'], 'BM25 scores should order relevant matches');
check(uttc_retrieve('welding certification', $pages) === [], 'unmatched question should return no results');
$limited = uttc_retrieve('driving', $pages, 1);
check(count($limited) === 1, 'result limit should be respected');
echo "OK: 5 retrieval assertions passed.\n";
