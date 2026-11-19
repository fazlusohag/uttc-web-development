<?php
declare(strict_types=1);

/**
 * Cached semantic retrieval over the approved UTTC corpus.
 * Requires OPENAI_API_KEY and OPENAI_EMBEDDING_MODEL in the server environment.
 * Embedding vectors are private operational cache data under api/runtime/.
 */
function uttc_embedding_request(string $apiKey, array $inputs, string $model): array {
    $ch = curl_init('https://api.openai.com/v1/embeddings');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode(['model'=>$model, 'input'=>$inputs, 'encoding_format'=>'float'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ]);
    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $http < 200 || $http >= 300) {
        error_log('UTTC embedding API error: HTTP '.$http.' '.$error.' '.substr((string)$raw,0,300));
        throw new RuntimeException('Semantic Search-এর Embedding API এখন ব্যবহার করা যাচ্ছে না।');
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !is_array($data['data'] ?? null)) throw new RuntimeException('Embedding API থেকে সঠিক তথ্য পাওয়া যায়নি।');
    $vectors = [];
    foreach ($data['data'] as $item) {
        $vector = $item['embedding'] ?? null;
        if (!is_array($vector) || !$vector) throw new RuntimeException('Embedding vector পাওয়া যায়নি।');
        $vectors[(int)($item['index'] ?? count($vectors))] = array_map('floatval', $vector);
    }
    ksort($vectors);
    return array_values($vectors);
}

function uttc_cosine_similarity(array $a, array $b): float {
    if (count($a) !== count($b) || !$a) return 0.0;
    $dot = 0.0; $normA = 0.0; $normB = 0.0;
    foreach ($a as $i=>$value) {
        $dot += $value * $b[$i];
        $normA += $value * $value;
        $normB += $b[$i] * $b[$i];
    }
    if ($normA <= 0 || $normB <= 0) return 0.0;
    return $dot / (sqrt($normA) * sqrt($normB));
}

function uttc_semantic_retrieve(string $question, array $pages, string $apiKey, string $model, int $limit = 5): array {
    $question = trim($question);
    if ($question === '' || !$pages) return [];
    $runtime = __DIR__ . '/runtime';
    if (!is_dir($runtime) && !@mkdir($runtime, 0750, true) && !is_dir($runtime)) {
        throw new RuntimeException('Semantic Search cache ফোল্ডার তৈরি করা যায়নি।');
    }

    $adminDocuments = [];
    $websiteDocuments = [];
    foreach ($pages as $page) {
        $title = trim((string)($page['title'] ?? ''));
        $text = trim((string)($page['text'] ?? ''));
        if ($title === '' || $text === '') continue;
        $document = [
            'title'=>$title,
            'url'=>(string)($page['url'] ?? ''),
            'text'=>mb_substr($text, 0, 6000, 'UTF-8'),
            'source_type'=>(string)($page['_source_type'] ?? 'website')
        ];
        if ($document['source_type'] === 'admin') $adminDocuments[] = $document;
        else $websiteDocuments[] = $document;
    }
    // Keep approved admin knowledge inside the bounded index before website pages.
    $documents = array_slice(array_merge($adminDocuments, $websiteDocuments), 0, 500);
    if (!$documents) return [];
    $fingerprint = hash('sha256', json_encode($documents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    $cacheFile = $runtime . '/semantic-index.json';
    $lock = @fopen($runtime . '/semantic-index.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        if (is_resource($lock)) fclose($lock);
        throw new RuntimeException('Semantic Search cache সাময়িকভাবে ব্যবহার করা যাচ্ছে না।');
    }
    try {
        $cache = is_file($cacheFile) ? json_decode((string)file_get_contents($cacheFile), true) : null;
        $valid = is_array($cache)
            && ($cache['model'] ?? '') === $model
            && ($cache['fingerprint'] ?? '') === $fingerprint
            && is_array($cache['vectors'] ?? null)
            && count($cache['vectors']) === count($documents);

        if (!$valid) {
            $vectors = [];
            foreach (array_chunk($documents, 64) as $chunk) {
                $inputs = array_map(static fn($doc) => $doc['title'] . "\n" . $doc['text'], $chunk);
                $batch = uttc_embedding_request($apiKey, $inputs, $model);
                if (count($batch) !== count($chunk)) throw new RuntimeException('সব UTTC তথ্যের Embedding তৈরি করা যায়নি।');
                foreach ($batch as $vector) $vectors[] = $vector;
            }
            $cachePayload = ['model'=>$model, 'fingerprint'=>$fingerprint, 'vectors'=>$vectors, 'updated_at'=>gmdate('c')];
            $encoded = json_encode($cachePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $tmp = $cacheFile . '.' . bin2hex(random_bytes(6)) . '.tmp';
            if ($encoded === false || file_put_contents($tmp, $encoded, LOCK_EX) === false || !rename($tmp, $cacheFile)) {
                @unlink($tmp);
                throw new RuntimeException('Semantic Search index সংরক্ষণ করা যায়নি।');
            }
            $cache = $cachePayload;
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    $queryVectors = uttc_embedding_request($apiKey, [$question], $model);
    $queryVector = $queryVectors[0] ?? [];
    if (!$queryVector) throw new RuntimeException('প্রশ্নের Embedding তৈরি করা যায়নি.');
    $ranked = [];
    foreach ($documents as $i=>$doc) {
        $vector = $cache['vectors'][$i] ?? [];
        if (!$vector) continue;
        $score = uttc_cosine_similarity($queryVector, $vector);
        $ranked[] = [
            'score'=>round($score, 5),
            'title'=>$doc['title'],
            'url'=>$doc['url'],
            'text'=>$doc['text'],
            'source_type'=>$doc['source_type']
        ];
    }
    usort($ranked, static function($a,$b) {
        $byScore = $b['score'] <=> $a['score'];
        if ($byScore !== 0) return $byScore;
        return (($b['source_type'] === 'admin') <=> ($a['source_type'] === 'admin'));
    });
    return array_slice($ranked, 0, max(1, min(10, $limit)));
}
