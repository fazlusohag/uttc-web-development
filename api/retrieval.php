<?php
declare(strict_types=1);

/**
 * Local BM25-style retrieval for the approved UTTC corpus.
 * This is lexical ranking, not vector embeddings or semantic understanding.
 */
function uttc_tokens(string $value): array {
    $value = mb_strtolower($value, 'UTF-8');
    preg_match_all('/[\\p{L}\\p{N}]{2,}/u', $value, $matches);
    $stop = ['কি','কী','কেন','কোথায়','কোথায়','কিভাবে','কীভাবে','আমি','আমার','আপনি','আপনার','হবে','হয়','হয়','জন্য','এবং','the','what','when','where','which','how','is','are','was','were','for','to','in','on','of','and','a','an'];
    return array_values(array_filter($matches[0] ?? [], static fn($term) => !in_array($term, $stop, true)));
}

function uttc_retrieve(string $question, array $pages, int $limit = 5): array {
    $queryTerms = array_values(array_unique(uttc_tokens($question)));
    if (!$queryTerms || !$pages) return [];
    $documents = [];
    $documentFrequency = array_fill_keys($queryTerms, 0);
    $totalLength = 0;
    foreach ($pages as $page) {
        $title = (string)($page['title'] ?? '');
        $text = (string)($page['text'] ?? '');
        $tokens = uttc_tokens($title . ' ' . $text);
        $titleTokens = uttc_tokens($title);
        $frequencies = array_count_values($tokens);
        $titleFrequencies = array_count_values($titleTokens);
        foreach ($queryTerms as $term) if (($frequencies[$term] ?? 0) > 0) $documentFrequency[$term]++;
        $totalLength += count($tokens);
        $documents[] = ['page'=>$page,'frequencies'=>$frequencies,'title_frequencies'=>$titleFrequencies,'length'=>count($tokens),'hay'=>mb_strtolower($title . ' ' . $text,'UTF-8')];
    }
    $n = count($documents);
    $averageLength = max(1.0, $totalLength / max(1,$n));
    $ranked = [];
    foreach ($documents as $doc) {
        $score = 0.0;
        foreach ($queryTerms as $term) {
            $tf = (int)($doc['frequencies'][$term] ?? 0);
            if ($tf < 1) continue;
            $df = (int)($documentFrequency[$term] ?? 0);
            $idf = log(1 + (($n - $df + 0.5) / ($df + 0.5)));
            $lengthNorm = 1.2 * (0.25 + 0.75 * ($doc['length'] / $averageLength));
            $score += $idf * (($tf * 2.2) / ($tf + $lengthNorm));
            $score += min(2, (int)($doc['title_frequencies'][$term] ?? 0)) * $idf * 0.8;
        }
        if ($score > 0) {
            $page = $doc['page'];
            $ranked[] = [
                'score'=>round($score,3),
                'title'=>(string)($page['title'] ?? ''),
                'url'=>(string)($page['url'] ?? ''),
                'text'=>mb_substr((string)($page['text'] ?? ''),0,6500,'UTF-8'),
                'source_type'=>(string)($page['_source_type'] ?? 'website')
            ];
        }
    }
    usort($ranked, static function($a,$b) {
        $byScore = $b['score'] <=> $a['score'];
        if ($byScore !== 0) return $byScore;
        return (($b['source_type'] === 'admin') <=> ($a['source_type'] === 'admin'));
    });
    return array_slice($ranked,0,max(1,min(10,$limit)));
}
