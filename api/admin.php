<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('UTTC_KB_ADMIN');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);
ini_set('session.use_strict_mode','1');
session_start();

require_once __DIR__ . '/storage.php';

function out(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function input_data(): array {
    if (isset($_POST['action'])) return $_POST;
    $maxBytes = 64 * 1024;
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBytes) out(413, ['ok'=>false,'message'=>'অনুরোধটি খুব বড়।']);
    $stream = fopen('php://input', 'rb');
    $raw = $stream ? stream_get_contents($stream, $maxBytes + 1) : false;
    if (is_resource($stream)) fclose($stream);
    if ($raw === false || strlen($raw) > $maxBytes) out(413, ['ok'=>false,'message'=>'অনুরোধটি খুব বড়।']);
    $v = json_decode($raw, true);
    return is_array($v) ? $v : [];
}
function storage_dir(): string {
    $dir = __DIR__ . '/data';
    if (is_link($dir) || (file_exists($dir) && !is_dir($dir))) {
        out(500,['ok'=>false,'message'=>'তথ্য সংরক্ষণের ফোল্ডারের পথ নিরাপদ নয়।']);
    }
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        out(500,['ok'=>false,'message'=>'তথ্য সংরক্ষণের ফোল্ডার তৈরি করা যায়নি।']);
    }
    if (!uttc_private_storage_directory_is_safe($dir)) {
        out(500,['ok'=>false,'message'=>'ব্যক্তিগত তথ্যভান্ডারের access protection যাচাই করা যায়নি।']);
    }
    $upload = $dir . '/uploads';
    if (!is_dir($upload) && !@mkdir($upload, 0750, true) && !is_dir($upload)) {
        out(500,['ok'=>false,'message'=>'আপলোড সংরক্ষণের ফোল্ডার তৈরি করা যায়নি।']);
    }
    if (is_link($upload) || !is_dir($upload)) {
        out(500,['ok'=>false,'message'=>'আপলোড সংরক্ষণের ফোল্ডারের পথ নিরাপদ নয়।']);
    }
    return $dir;
}
$entries_lock_handle = null;
function load_entries(): array {
    global $entries_lock_handle;
    $dir = storage_dir();
    if (!is_resource($entries_lock_handle)) {
        $entries_lock_handle = @fopen($dir . '/write.lock', 'c');
        if (!$entries_lock_handle || !flock($entries_lock_handle, LOCK_EX)) {
            if (is_resource($entries_lock_handle)) fclose($entries_lock_handle);
            $entries_lock_handle = null;
            out(500, ['ok'=>false,'message'=>'তথ্যভান্ডার সাময়িকভাবে ব্যবহার করা যাচ্ছে না।']);
        }
    }
    $file = $dir . '/knowledge-base.json';
    if (is_link($file) || (file_exists($file) && !is_file($file))) {
        out(500, ['ok'=>false,'message'=>'Knowledge Base ফাইলের storage path নিরাপদ নয়।']);
    }
    if (!is_file($file)) return [];
    $raw = @file_get_contents($file);
    $data = $raw === false ? null : json_decode($raw, true);
    if ($raw === false || json_last_error() !== JSON_ERROR_NONE || !is_array($data) || !isset($data['entries']) || !is_array($data['entries'])) {
        out(500, ['ok'=>false,'message'=>'Knowledge Base ডেটা পড়া যাচ্ছে না বা JSON অবৈধ। মূল ফাইল অক্ষত রাখা হয়েছে; যাচাইকৃত Backup থেকে Restore প্রয়োজন।']);
    }
    foreach ($data['entries'] as $entry) {
        if (!is_array($entry)) {
            out(500, ['ok'=>false,'message'=>'Knowledge Base-এ অবৈধ রেকর্ড পাওয়া গেছে। মূল ফাইল অক্ষত রাখা হয়েছে; যাচাই ছাড়া কোনো রেকর্ড বাদ দেওয়া হবে না।']);
        }
    }
    return array_values($data['entries']);
}
function save_entries(array $entries): void {
    global $entries_lock_handle;
    $dir = storage_dir();
    if (!is_resource($entries_lock_handle)) {
        $entries_lock_handle = @fopen($dir . '/write.lock', 'c');
        if (!$entries_lock_handle || !flock($entries_lock_handle, LOCK_EX)) {
            if (is_resource($entries_lock_handle)) fclose($entries_lock_handle);
            $entries_lock_handle = null;
            out(500,['ok'=>false,'message'=>'তথ্য সংরক্ষণ করা যাচ্ছে না।']);
        }
    }
    $file = $dir . '/knowledge-base.json';
    if (is_link($file) || (file_exists($file) && !is_file($file))) {
        flock($entries_lock_handle, LOCK_UN); fclose($entries_lock_handle); $entries_lock_handle = null;
        out(500,['ok'=>false,'message'=>'Knowledge Base ফাইলের storage path নিরাপদ নয়; পরিবর্তন বন্ধ রাখা হয়েছে।']);
    }
    // Preserve the previous published/draft state before each successful change.
    if (is_file($file)) {
        $oldRaw = @file_get_contents($file);
        $old = $oldRaw === false ? null : json_decode($oldRaw, true);
        if ($oldRaw === false || json_last_error() !== JSON_ERROR_NONE || !is_array($old)
            || !isset($old['entries']) || !is_array($old['entries'])) {
            flock($entries_lock_handle, LOCK_UN); fclose($entries_lock_handle); $entries_lock_handle = null;
            out(500,['ok'=>false,'message'=>'Knowledge Base ডেটা পড়া যাচ্ছে না বা JSON অবৈধ; পরিবর্তন বন্ধ রাখা হয়েছে।']);
        }
        $historyFile = $dir . '/knowledge-history.json';
        $historyData = null;
        if (is_link($historyFile)) {
            flock($entries_lock_handle, LOCK_UN); fclose($entries_lock_handle); $entries_lock_handle = null;
            out(500,['ok'=>false,'message'=>'Knowledge Base History-এর storage path নিরাপদ নয়; পরিবর্তন বন্ধ রাখা হয়েছে।']);
        }
        if (is_file($historyFile)) {
            $historyRaw = @file_get_contents($historyFile);
            $historyData = $historyRaw === false ? null : json_decode($historyRaw, true);
            if ($historyRaw === false || json_last_error() !== JSON_ERROR_NONE || !is_array($historyData)
                || !isset($historyData['versions']) || !is_array($historyData['versions'])) {
                flock($entries_lock_handle, LOCK_UN); fclose($entries_lock_handle); $entries_lock_handle = null;
                out(500,['ok'=>false,'message'=>'Knowledge Base History নষ্ট বা পড়া যাচ্ছে না; নিরাপত্তার জন্য পরিবর্তন বন্ধ রাখা হয়েছে।']);
            }
        } elseif (file_exists($historyFile)) {
            flock($entries_lock_handle, LOCK_UN); fclose($entries_lock_handle); $entries_lock_handle = null;
            out(500,['ok'=>false,'message'=>'Knowledge Base History-এর storage path নিরাপদ নয়; পরিবর্তন বন্ধ রাখা হয়েছে।']);
        }
        $versions = is_array($historyData) ? $historyData['versions'] : [];
        $versions[] = ['saved_at'=>gmdate('c'),'entry_count'=>count($old['entries']),'entries'=>$old['entries']];
        $versions = array_slice($versions, -30);
        $historyJson = json_encode(['versions'=>$versions], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($historyJson === false || !uttc_atomic_write_file($historyFile, $historyJson)) {
            flock($entries_lock_handle, LOCK_UN); fclose($entries_lock_handle); $entries_lock_handle = null;
            out(500,['ok'=>false,'message'=>'তথ্য পরিবর্তনের ইতিহাস সম্পূর্ণভাবে সংরক্ষণ করা যায়নি; পরিবর্তনটি করা হয়নি।']);
        }
    }
    $payload = ['updated_at'=>gmdate('c'),'entries'=>array_values($entries)];
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($encoded === false || !uttc_atomic_write_file($file, $encoded)) {
        flock($entries_lock_handle, LOCK_UN); fclose($entries_lock_handle); $entries_lock_handle = null;
        out(500,['ok'=>false,'message'=>'তথ্য সম্পূর্ণভাবে সংরক্ষণ ব্যর্থ হয়েছে; আগের ফাইল অক্ষত রাখা হয়েছে।']);
    }
    flock($entries_lock_handle, LOCK_UN); fclose($entries_lock_handle); $entries_lock_handle = null;
}
function csrf_check(array $d): void {
    $given = (string)($d['csrf'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'],$given)) out(419,['ok'=>false,'message'=>'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।']);
}
function require_admin(): void {
    if (empty($_SESSION['admin'])) out(401,['ok'=>false,'message'=>'অ্যাডমিন হিসেবে প্রবেশ করুন।']);
}
function clean_text(string $s, int $max): string {
    $s = trim(str_replace("\0",'',$s));
    if (mb_strlen($s,'UTF-8') > $max) out(422,['ok'=>false,'message'=>'লেখা নির্ধারিত সীমার চেয়ে বড়।']);
    return $s;
}
function clear_login_attempts(): void {
    $path = storage_dir() . '/login-attempts.json';
    $ip = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $fp = @fopen($path, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) { if (is_resource($fp)) fclose($fp); return; }
    rewind($fp);
    $all = json_decode(stream_get_contents($fp) ?: '{}', true);
    if (!is_array($all)) $all = [];
    unset($all[$ip]);
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($all));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}
function login_throttled(): bool {
    $dir = storage_dir();
    $path = $dir . '/login-attempts.json';
    $ip = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $fp = @fopen($path, 'c+');
    if (!$fp) return true;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return true; }
    rewind($fp);
    $raw = stream_get_contents($fp);
    $all = json_decode($raw ?: '{}', true);
    if (!is_array($all)) $all = [];
    $now = time();
    // Expire stale IP buckets and cap the file so distributed probing cannot grow it indefinitely.
    foreach ($all as $key => $bucket) {
        if (!is_array($bucket) || $now - (int)($bucket['start'] ?? 0) >= 900) unset($all[$key]);
    }
    if (!isset($all[$ip]) && count($all) >= 1000) {
        uasort($all, static fn($a, $b) => (int)($a['start'] ?? 0) <=> (int)($b['start'] ?? 0));
        $all = array_slice($all, -999, null, true);
    }
    $row = $all[$ip] ?? ['start'=>$now,'hits'=>0];
    if ($now - (int)($row['start'] ?? 0) >= 900) $row = ['start'=>$now,'hits'=>0];
    if ((int)($row['hits'] ?? 0) >= 5) {
        flock($fp, LOCK_UN); fclose($fp);
        return true;
    }
    $row['hits'] = (int)($row['hits'] ?? 0) + 1;
    $all[$ip] = $row;
    rewind($fp);
    $written = ftruncate($fp, 0) && fwrite($fp, json_encode($all)) !== false && fflush($fp);
    flock($fp, LOCK_UN); fclose($fp);
    return !$written;
}
require_once __DIR__ . '/document-extract.php';
require_once __DIR__ . '/retrieval.php';
require_once __DIR__ . '/semantic-retrieval.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && ($_GET['action'] ?? '') === 'token') {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    out(200,['ok'=>true,'csrf'=>$_SESSION['csrf']]);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(405,['ok'=>false,'message'=>'অনুমোদিত অনুরোধ নয়।']);
$d = input_data(); $action = (string)($d['action'] ?? '');
csrf_check($d);

if ($action === 'login') {
    $hash = trim((string)getenv('UTTC_ADMIN_PASSWORD_HASH'));
    if ($hash === '') out(503,['ok'=>false,'message'=>'অ্যাডমিন লগইন এখনো সার্ভারে কনফিগার করা হয়নি। Hostinger-এ UTTC_ADMIN_PASSWORD_HASH সেট করতে হবে।']);
    if (login_throttled()) out(429,['ok'=>false,'message'=>'অনেকবার ভুল চেষ্টা হয়েছে। ১৫ মিনিট পর আবার চেষ্টা করুন।']);
    $password = (string)($d['password'] ?? '');
    if (!password_verify($password,$hash)) out(401,['ok'=>false,'message'=>'পাসওয়ার্ড সঠিক নয়।']);
    clear_login_attempts();
    session_regenerate_id(true); $_SESSION['admin']=true;
    out(200,['ok'=>true,'message'=>'লগইন সফল।']);
}
if ($action === 'logout') { require_admin(); $_SESSION=[]; session_regenerate_id(true); out(200,['ok'=>true]); }
require_admin();

if ($action === 'history') {
    $dir = storage_dir();
    $historyFile = $dir . '/knowledge-history.json';
    $versions = [];
    if (is_link($historyFile) || (file_exists($historyFile) && !is_file($historyFile))) {
        out(500,['ok'=>false,'message'=>'Knowledge Base History-এর storage path নিরাপদ নয়।']);
    }
    if (is_file($historyFile)) {
        $historyRaw = @file_get_contents($historyFile);
        $historyData = $historyRaw === false ? null : json_decode($historyRaw, true);
        if ($historyRaw === false || json_last_error() !== JSON_ERROR_NONE || !is_array($historyData)
            || !isset($historyData['versions']) || !is_array($historyData['versions'])) {
            out(500,['ok'=>false,'message'=>'Knowledge Base History নষ্ট বা পড়া যাচ্ছে না; কোনো তথ্য গোপন করে খালি তালিকা দেখানো হয়নি।']);
        }
        $versions = $historyData['versions'];
    }
    $summary = [];
    foreach (array_reverse($versions) as $version) {
        $titles = [];
        foreach (array_slice((array)($version['entries'] ?? []), 0, 12) as $entry) $titles[] = (string)($entry['title'] ?? '');
        $summary[] = ['saved_at'=>(string)($version['saved_at'] ?? ''),'entry_count'=>(int)($version['entry_count'] ?? 0),'titles'=>$titles];
    }
    out(200,['ok'=>true,'versions'=>$summary]);
}
if ($action === 'test_retrieval') {
    $question = clean_text((string)($d['question'] ?? ''), 1200);
    if ($question === '') out(422,['ok'=>false,'message'=>'পরীক্ষার জন্য একটি প্রশ্ন লিখুন।']);
    $pages = [];
    $websiteFile = __DIR__ . '/knowledge.json';
    $websiteData = is_file($websiteFile) ? json_decode((string)file_get_contents($websiteFile), true) : null;
    if (is_array($websiteData) && is_array($websiteData['pages'] ?? null)) {
        foreach ($websiteData['pages'] as $page) {
            $pages[] = ['title'=>(string)($page['title'] ?? ''),'url'=>(string)($page['url'] ?? ''),'text'=>(string)($page['text'] ?? ''),'_source_type'=>'website'];
        }
    }
    foreach (load_entries() as $entry) {
        if (($entry['status'] ?? '') !== 'published') continue;
        $title = trim((string)($entry['title'] ?? ''));
        $answer = trim((string)($entry['answer'] ?? ''));
        if ($title === '' || $answer === '') continue;
        $q = trim((string)($entry['question'] ?? ''));
        $pages[] = ['title'=>'[অনুমোদিত UTTC তথ্য] '.$title,'url'=>'','text'=>($q !== '' ? 'প্রশ্ন: '.$q.' ' : '').'উত্তর: '.$answer,'_source_type'=>'admin'];
    }
    $embeddingModel = trim((string)getenv('OPENAI_EMBEDDING_MODEL'));
    $retrievalMode = 'bm25';
    $ranked = [];
    if ($embeddingModel !== '' && trim((string)getenv('OPENAI_API_KEY')) !== '') {
        try { $ranked = uttc_semantic_retrieve($question, $pages, trim((string)getenv('OPENAI_API_KEY')), $embeddingModel); $retrievalMode = 'semantic'; }
        catch (Throwable $e) { error_log('UTTC admin semantic preview fallback: '.$e->getMessage()); }
    }
    if ($retrievalMode === 'semantic' && (!$ranked || (float)$ranked[0]['score'] < 0.30)) { $ranked = uttc_retrieve($question, $pages); $retrievalMode = 'bm25_fallback'; }
    if (!$ranked) $ranked = uttc_retrieve($question, $pages);
    $matches = array_map(static fn($item) => ['score'=>$item['score'],'title'=>$item['title'],'url'=>$item['url'],'source_type'=>$item['source_type'],'excerpt'=>mb_substr($item['text'],0,700,'UTF-8')], $ranked);
    out(200,['ok'=>true,'retrieval_mode'=>$retrievalMode,'matches'=>$matches,'match_count'=>count($matches),'website_indexed'=>count($websiteData['pages'] ?? []),'published_admin_entries'=>count(array_filter(load_entries(),fn($e)=>($e['status'] ?? '')==='published'))]);
}
if ($action === 'list') {
    $entries=load_entries();
    usort($entries,fn($a,$b)=>strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??'')));
    out(200,['ok'=>true,'entries'=>$entries]);
}
if ($action === 'save') {
    $id=trim((string)($d['id']??'')); $title=clean_text((string)($d['title']??''),180);
    $category=clean_text((string)($d['category']??'অন্যান্য'),60);
    $question=clean_text((string)($d['question']??''),3000); $answer=clean_text((string)($d['answer']??''),30000);
    if ($title==='' || $answer==='') out(422,['ok'=>false,'message'=>'শিরোনাম ও উত্তর লিখুন।']);
    $entries=load_entries(); $found=false; $now=gmdate('c');
    foreach ($entries as &$entry) if ($id!=='' && ($entry['id']??'')===$id) {
        $entry['title']=$title;$entry['category']=$category;$entry['question']=$question;$entry['answer']=$answer;
        $entry['status']='draft';$entry['updated_at']=$now;$entry['published_at']=null;$found=true;break;
    } unset($entry);
    if (!$found) $entries[]=['id'=>bin2hex(random_bytes(12)),'title'=>$title,'category'=>$category,'question'=>$question,'answer'=>$answer,'status'=>'draft','created_at'=>$now,'updated_at'=>$now,'published_at'=>null,'file_name'=>null,'storage_name'=>null];
    save_entries($entries); out(200,['ok'=>true,'message'=>'Draft সংরক্ষণ হয়েছে।']);
}
if ($action === 'publish') {
    $id=(string)($d['id']??'');$entries=load_entries();$found=false;
    foreach($entries as &$entry) if (($entry['id']??'')===$id) {
        if (trim((string)($entry['title']??''))==='' || trim((string)($entry['answer']??''))==='') out(422,['ok'=>false,'message'=>'শিরোনাম ও উত্তর ছাড়া Publish করা যাবে না।']);
        $entry['status']='published';$entry['published_at']=gmdate('c');$entry['updated_at']=$entry['published_at'];$found=true;break;
    } unset($entry);
    if(!$found) out(404,['ok'=>false,'message'=>'তথ্যটি পাওয়া যায়নি।']);
    save_entries($entries);out(200,['ok'=>true]);
}
if ($action === 'delete') {
    $id=(string)($d['id']??'');$entries=load_entries();$found=false;$deletedStorageName=null;
    foreach($entries as $i=>$entry) if(($entry['id']??'')===$id){$found=true;if(!empty($entry['storage_name']))$deletedStorageName=basename((string)$entry['storage_name']);unset($entries[$i]);break;}
    if(!$found) out(404,['ok'=>false,'message'=>'তথ্যটি পাওয়া যায়নি।']);
    save_entries($entries);
    // Remove the private upload only after the knowledge-base update succeeds.
    if($deletedStorageName!==null&&$deletedStorageName!==''&&$deletedStorageName!=='.'&&$deletedStorageName!=='..') @unlink(storage_dir().'/uploads/'.$deletedStorageName);
    out(200,['ok'=>true]);
}
if ($action === 'upload') {
    if (empty($_FILES['document']) || ($_FILES['document']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) out(422,['ok'=>false,'message'=>'ফাইল আপলোড করা যায়নি। আবার চেষ্টা করুন।']);
    $f=$_FILES['document']; if ((int)$f['size']>8*1024*1024) out(422,['ok'=>false,'message'=>'ফাইলের আকার ৮ MB-এর বেশি হতে পারবে না।']);
    $original=(string)$f['name'];$ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
    if(!in_array($ext,['pdf','docx','txt','md'],true)) out(422,['ok'=>false,'message'=>'শুধু PDF, DOCX, TXT বা MD ফাইল আপলোড করুন।']);
    $tmp=(string)$f['tmp_name'];if(!is_uploaded_file($tmp)) out(422,['ok'=>false,'message'=>'অবৈধ আপলোড শনাক্ত হয়েছে।']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
    $allowedMimes = ['pdf'=>['application/pdf'],'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'],'txt'=>['text/plain'],'md'=>['text/plain','text/markdown']];
    if (!in_array($mime, $allowedMimes[$ext] ?? [], true)) out(422,['ok'=>false,'message'=>'ফাইলের প্রকৃত ধরন ও extension মিলছে না।']);
    try { $text=uttc_extract_document($tmp,$ext); } catch (Throwable $e) { out(422,['ok'=>false,'message'=>$e->getMessage()]); }
    if(trim($text)==='') out(422,['ok'=>false,'message'=>'ফাইল থেকে কোনো পাঠযোগ্য লেখা পাওয়া যায়নি।']);
    $text=mb_substr($text,0,100000,'UTF-8');
    $dir=storage_dir();$stored=bin2hex(random_bytes(16)).'.'.$ext;$target=$dir.'/uploads/'.$stored;
    if(!move_uploaded_file($tmp,$target)) out(500,['ok'=>false,'message'=>'ফাইল নিরাপদে সংরক্ষণ করা যায়নি।']);
    // save_entries() terminates the request on storage errors; clean up the moved upload on every unsuccessful exit.
    $uploadCommitted=false;
    register_shutdown_function(static function() use ($target, &$uploadCommitted): void {
        if (!$uploadCommitted && is_file($target)) @unlink($target);
    });
    $now=gmdate('c');$entries=load_entries();
    $entries[]=['id'=>bin2hex(random_bytes(12)),'title'=>clean_text(pathinfo($original,PATHINFO_FILENAME),180),'category'=>'অন্যান্য','question'=>'','answer'=>$text,'status'=>'draft','created_at'=>$now,'updated_at'=>$now,'published_at'=>null,'file_name'=>basename($original),'storage_name'=>$stored];
    save_entries($entries);
    $uploadCommitted=true;
    out(200,['ok'=>true,'message'=>'ফাইল থেকে Draft তৈরি হয়েছে।']);
}
out(400,['ok'=>false,'message'=>'অজানা অনুরোধ।']);