<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60, stale-while-revalidate=120');
header('X-Content-Type-Options: nosniff');

function cms_public_string(mixed $value): string {
    return is_string($value) || is_numeric($value) ? (string)$value : '';
}
function cms_public_response(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$path = __DIR__ . '/data/cms-content.json';
$data = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
$items = is_array($data) && is_array($data['items'] ?? null) ? $data['items'] : [];
$type = trim((string)($_GET['type'] ?? ''));
$allowed = ['course', 'notice', 'gallery', 'page', 'contact', 'menu'];

if ($type !== '' && !in_array($type, $allowed, true)) {
    cms_public_response(400, ['ok' => false, 'message' => 'Invalid type']);
}

$items = array_values(array_filter(
    $items,
    static fn($item): bool => is_array($item)
        && ($item['status'] ?? null) === 'published'
        && in_array(($item['type'] ?? null), $allowed, true)
        && ($type === '' || ($item['type'] ?? null) === $type)
));

$items = array_map(static function (array $item): array {
    $publishedAt = $item['published_at'] ?? null;
    return [
        'id' => cms_public_string($item['id'] ?? ''),
        'type' => cms_public_string($item['type'] ?? ''),
        'title' => cms_public_string($item['title'] ?? ''),
        'body' => cms_public_string($item['body'] ?? ''),
        'url' => cms_public_string($item['url'] ?? ''),
        'image_url' => cms_public_string($item['image_url'] ?? ''),
        'slug' => cms_public_string($item['slug'] ?? ''),
        'page_key' => cms_public_string($item['page_key'] ?? ''),
        'meta_title' => cms_public_string($item['meta_title'] ?? ''),
        'meta_description' => cms_public_string($item['meta_description'] ?? ''),
        'canonical_url' => cms_public_string($item['canonical_url'] ?? ''),
        'published_at' => is_string($publishedAt) ? $publishedAt : null,
    ];
}, $items);

cms_public_response(200, ['ok' => true, 'items' => $items]);
