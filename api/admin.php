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

function out(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function input_data(): array {
    if (isset($_POST['action'])) return $_POST;
    $v = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($v) ? $v : [];
}
function storage_dir(): string {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) out(500,['ok'=>false,'message'=>'তথ্য সংরক্ষণের ফোল্ডার তৈরি করা যায়নি।']);
    $upload = $dir . '/uploads';
    if (!is_dir($upload)) @mkdir($upload, 0750, true);
    return $dir;
}
function load_entries(): array {
    $file = storage_dir() . '/knowledge-base.json';
    if (!is_file($file)) return [];
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) && isset($data['entries']) && is_array($data['entries']) ? $data['entries'] : [];
}
function save_entries(array $entries): void {
    $dir = storage_dir();
    $lock = fopen($dir . '/write.lock','c');
    if (!$lock) out(500,['ok'=>false,'message'=>'তথ্য সংরক্ষণ করা যাচ্ছে না।']);
    flock($lock, LOCK_EX);
    $file = $dir . '/knowledge-base.json';
    $payload = ['updated_at'=>gmdate('c'),'entries'=>array_values($entries)];
    $tmp = $file . '.tmp';
    $ok = file_put_contents($tmp, json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
    if ($ok === false || !rename($tmp,$file)) {
        @unlink($tmp); flock($lock,LOCK_UN); fclose($lock);
        out(500,['ok'=>false,'message'=>'তথ্য ফাইলে সংরক্ষণ ব্যর্থ হয়েছে।']);
    }
    flock($lock,LOCK_UN); fclose($lock);
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
function login_throttled(): bool {
    $dir = storage_dir();
    $path = $dir . '/login-attempts.json';
    $ip = hash('sha256',(string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $all = is_file($path) ? json_decode((string)file_get_contents($path),true) : [];
    if (!is_array($all)) $all = [];
    $row = $all[$ip] ?? ['start'=>time(),'hits'=>0];
    if (time() - (int)$row['start'] >= 900) $row = ['start'=>time(),'hits'=>0];
    if ((int)$row['hits'] >= 5) return true;
    $row['hits'] = (int)$row['hits'] + 1; $all[$ip] = $row;
    file_put_contents($path,json_encode($all),LOCK_EX);
    return false;
}
function extract_document(string $path, string $ext): string {
    if ($ext === 'txt' || $ext === 'md') {
        $text = file_get_contents($path);
        if ($text === false) return '';
        if (!mb_check_encoding($text,'UTF-8')) $text = mb_convert_encoding($text,'UTF-8','auto');
        return trim($text);
    }
    if ($ext === 'docx') {
        if (!class_exists('ZipArchive')) out(422,['ok'=>false,'message'=>'এই সার্ভারে DOCX পড়ার জন্য PHP ZipArchive extension চালু নেই।']);
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) out(422,['ok'=>false,'message'=>'DOCX ফাইলটি পড়া যাচ্ছে না।']);
        $xml = $zip->getFromName('word/document.xml'); $zip->close();
        if ($xml === false) out(422,['ok'=>false,'message'=>'DOCX ফাইলে মূল লেখা পাওয়া যায়নি।']);
        $dom = new DOMDocument(); if (!@$dom->loadXML($xml,LIBXML_NONET)) out(422,['ok'=>false,'message'=>'DOCX-এর লেখা পার্স করা যায়নি।']);
        $xp = new DOMXPath($dom); $xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $parts=[]; foreach ($xp->query('//w:p') as $p) { $line=''; foreach ($xp->query('.//w:t',$p) as $t) $line .= $t->textContent; if ($line!=='') $parts[]=$line; }
        return trim(implode("\n",$parts));
    }
    if ($ext === 'pdf') {
        $bin = null; foreach (['/usr/bin/pdftotext','/usr/local/bin/pdftotext'] as $candidate) if (is_executable($candidate)) {$bin=$candidate;break;}
        if (!$bin) out(422,['ok'=>false,'message'=>'এই সার্ভারে PDF থেকে লেখা পড়ার pdftotext সুবিধা পাওয়া যায়নি। TXT বা DOCX ফাইল দিন, অথবা সার্ভারে PDF parser সেটআপ করতে হবে।']);
        $pipes=[]; $proc=proc_open([$bin,'-layout',$path,'-'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($proc)) out(422,['ok'=>false,'message'=>'PDF parser চালু করা যায়নি।']);
        $text=stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]); $code=proc_close($proc);
        if ($code!==0 || $text===false) out(422,['ok'=>false,'message'=>'PDF থেকে লেখা বের করা যায়নি।']);
        return trim($text);
    }
    out(422,['ok'=>false,'message'=>'এই ফাইল ফরম্যাট সমর্থিত নয়।']);
}

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
    session_regenerate_id(true); $_SESSION['admin']=true;
    out(200,['ok'=>true,'message'=>'লগইন সফল।']);
}
if ($action === 'logout') { require_admin(); $_SESSION=[]; session_regenerate_id(true); out(200,['ok'=>true]); }
require_admin();

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
    $id=(string)($d['id']??'');$entries=load_entries();$found=false;
    foreach($entries as $i=>$entry) if(($entry['id']??'')===$id){$found=true;if(!empty($entry['storage_name'])) @unlink(storage_dir().'/uploads/'.$entry['storage_name']);unset($entries[$i]);break;}
    if(!$found) out(404,['ok'=>false,'message'=>'তথ্যটি পাওয়া যায়নি।']);
    save_entries($entries);out(200,['ok'=>true]);
}
if ($action === 'upload') {
    if (empty($_FILES['document']) || ($_FILES['document']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) out(422,['ok'=>false,'message'=>'ফাইল আপলোড করা যায়নি। আবার চেষ্টা করুন।']);
    $f=$_FILES['document']; if ((int)$f['size']>8*1024*1024) out(422,['ok'=>false,'message'=>'ফাইলের আকার ৮ MB-এর বেশি হতে পারবে না।']);
    $original=(string)$f['name'];$ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
    if(!in_array($ext,['pdf','docx','txt','md'],true)) out(422,['ok'=>false,'message'=>'শুধু PDF, DOCX, TXT বা MD ফাইল আপলোড করুন।']);
    $tmp=(string)$f['tmp_name'];if(!is_uploaded_file($tmp)) out(422,['ok'=>false,'message'=>'অবৈধ আপলোড শনাক্ত হয়েছে।']);
    $text=extract_document($tmp,$ext);if(trim($text)==='') out(422,['ok'=>false,'message'=>'ফাইল থেকে কোনো পাঠযোগ্য লেখা পাওয়া যায়নি।']);
    $text=mb_substr($text,0,100000,'UTF-8');
    $dir=storage_dir();$stored=bin2hex(random_bytes(16)).'.'.$ext;$target=$dir.'/uploads/'.$stored;
    if(!move_uploaded_file($tmp,$target)) out(500,['ok'=>false,'message'=>'ফাইল নিরাপদে সংরক্ষণ করা যায়নি।']);
    $now=gmdate('c');$entries=load_entries();
    $entries[]=['id'=>bin2hex(random_bytes(12)),'title'=>clean_text(pathinfo($original,PATHINFO_FILENAME),180),'category'=>'অন্যান্য','question'=>'','answer'=>$text,'status'=>'draft','created_at'=>$now,'updated_at'=>$now,'published_at'=>null,'file_name'=>basename($original),'storage_name'=>$stored];
    save_entries($entries);out(200,['ok'=>true,'message'=>'ফাইল থেকে Draft তৈরি হয়েছে।']);
}
out(400,['ok'=>false,'message'=>'অজানা অনুরোধ।']);
