<?php
declare(strict_types=1);

/**
 * Accept only bounded prior user turns from browser-supplied conversation history.
 * Assistant turns are intentionally excluded because clients can forge them.
 */
function uttc_normalize_assistant_history(mixed $raw): array
{
    if (!is_array($raw)) return [];

    $history = [];
    foreach ($raw as $turn) {
        if (!is_array($turn) || ($turn['role'] ?? null) !== 'user') continue;
        $content = $turn['content'] ?? null;
        if (!is_string($content)) continue;
        $content = trim($content);
        if ($content === '' || mb_strlen($content, 'UTF-8') > 1200) continue;
        if (preg_match('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/', $content)) continue;
        $history[] = ['role' => 'user', 'content' => $content];
    }

    return array_slice($history, -10);
}
