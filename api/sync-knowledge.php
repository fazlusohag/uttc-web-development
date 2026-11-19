<?php
declare(strict_types=1);
/**
 * Run from SSH/Hostinger cron: php /path/to/public_html/api/sync-knowledge.php
 * Reads the site's sitemap and builds a local searchable knowledge index.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
$site = rtrim(getenv('UTTC_SITE_URL') ?: 'https://uttc.edu.bd', '/');
$siteParts = parse_url($site);
if (!is_array($siteParts) || strtolower((string)($siteParts['scheme'] ?? '')) !== 'https' || empty($siteParts['host']) || isset($siteParts['user']) || isset($siteParts['pass']) || (isset($siteParts['port']) && (int)$siteParts['port'] !== 443)) {
    fwrite(STDERR, "UTTC_SITE_URL must be a valid HTTPS origin without credentials or a non-standard port.\\n");
    exit(1);
}
$siteHost = strtolower((string)$siteParts['host']);
$sitemap = $site . '/sitemap.xml';
function fetch_url(string $url): string {
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_USERAGENT=>'UTTC-Knowledge-Sync/1.0']);
    $body=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($body===false || $code<200 || $code>=300) throw new RuntimeException("Fetch failed: ".$url." HTTP ".$code);
    return (string)$body;
}
function page_text(string $html): array {
    libxml_use_internal_errors(true);
    $dom=new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING|LIBXML_NOERROR);
    $xp=new DOMXPath($dom);
    foreach ($xp->query('//script|//style|//noscript|//svg|//nav|//footer|//header|//form') as $node) $node->parentNode?->removeChild($node);
    $title=trim($xp->evaluate('string(//title)'));
    $main=$xp->query('//main');
    $root=$main->length ? $main->item(0) : $dom->documentElement;
    $text=preg_replace('/\s+/u',' ',html_entity_decode($root?->textContent ?? '',ENT_QUOTES|ENT_HTML5,'UTF-8')) ?? '';
    return [$title,trim($text)];
}
try {
    $xml=fetch_url($sitemap);
    $sx=simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET|LIBXML_NOBLANKS);
    if (!$sx) throw new RuntimeException('Invalid sitemap XML');
    $urls=[];
    foreach ($sx->url as $entry) {
        $loc=trim((string)$entry->loc);
        if ($loc !== '') {
            $parts = parse_url($loc);
            if (is_array($parts) && strtolower((string)($parts['scheme'] ?? '')) === 'https' && strtolower((string)($parts['host'] ?? '')) === $siteHost && !isset($parts['user']) && !isset($parts['pass']) && (!isset($parts['port']) || (int)$parts['port'] === 443)) {
                $urls[] = $loc;
            }
        }
    }
    if (!$urls) throw new RuntimeException('No same-domain URLs found in sitemap');
    $pages=[]; $failed=[];
    foreach (array_unique($urls) as $url) {
        try {
            $html=fetch_url($url); [$title,$text]=page_text($html);
            if ($text!=='') $pages[]=['url'=>$url,'title'=>$title ?: $url,'text'=>mb_substr($text,0,18000,'UTF-8')];
        } catch (Throwable $e) { $failed[]=['url'=>$url,'error'=>$e->getMessage()]; }
    }
    if (!$pages) throw new RuntimeException('No pages could be indexed');
    $out=['site'=>$site,'synced_at'=>gmdate('c'),'page_count'=>count($pages),'pages'=>$pages];
    $target=__DIR__.'/knowledge.json';
    $lock=fopen(__DIR__.'/knowledge-sync.lock','c');
    if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Cannot lock knowledge index for update');
    $tmp=$target.'.'.bin2hex(random_bytes(6)).'.tmp';
    $encoded=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    $written=$encoded!==false?file_put_contents($tmp,$encoded,LOCK_EX):false;
    if ($written===false || !rename($tmp,$target)) {
        @unlink($tmp);
        flock($lock,LOCK_UN); fclose($lock);
        throw new RuntimeException('Cannot atomically replace knowledge.json');
    }
    flock($lock,LOCK_UN); fclose($lock);
    echo json_encode(['ok'=>true,'indexed'=>count($pages),'failed'=>$failed,'synced_at'=>$out['synced_at']],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
} catch (Throwable $e) { fwrite(STDERR,'Sync failed: '.$e->getMessage()."\n"); exit(1); }
