<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
session_name('UTTC_KB_ADMIN');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),'httponly'=>true,'samesite'=>'Strict']);
ini_set('session.use_strict_mode','1');
session_start();

function cms_out(int $status,array $data): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function cms_input(): array { $v=json_decode(file_get_contents('php://input')?:'',true); return is_array($v)?$v:[]; }
function cms_require_admin(): void { if(empty($_SESSION['admin'])) cms_out(401,['ok'=>false,'message'=>'প্রথমে Admin Dashboard-এ লগইন করুন।']); }
function cms_csrf(array $d): void { if(empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'],(string)($d['csrf']??''))) cms_out(419,['ok'=>false,'message'=>'নিরাপত্তা যাচাই ব্যর্থ। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।']); }
function cms_dir(): string { $p=__DIR__.'/data'; if(!is_dir($p)&&!mkdir($p,0750,true)&&!is_dir($p)) cms_out(500,['ok'=>false,'message'=>'ডেটা ফোল্ডার তৈরি করা যায়নি।']); return $p; }
function cms_read(): array { $p=cms_dir().'/cms-content.json'; if(!is_file($p)) return []; $d=json_decode((string)file_get_contents($p),true); return is_array($d)&&is_array($d['items']??null)?$d['items']:[]; }
function cms_write(array $items): void { $p=cms_dir().'/cms-content.json'; $lock=fopen(cms_dir().'/cms.lock','c'); if(!$lock||!flock($lock,LOCK_EX)) cms_out(500,['ok'=>false,'message'=>'ডেটা সংরক্ষণে সমস্যা হয়েছে।']); $tmp=$p.'.'.bin2hex(random_bytes(6)).'.tmp'; $json=json_encode(['updated_at'=>gmdate('c'),'items'=>array_values($items)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT); if($json===false||file_put_contents($tmp,$json,LOCK_EX)===false||!rename($tmp,$p)){@unlink($tmp);flock($lock,LOCK_UN);fclose($lock);cms_out(500,['ok'=>false,'message'=>'ডেটা সংরক্ষণ করা যায়নি।']);} flock($lock,LOCK_UN);fclose($lock); }
function cms_text(mixed $v,int $max): string { if(!is_string($v)) return ''; $v=trim(str_replace("\0",'',$v)); if(mb_strlen($v,'UTF-8')>$max) cms_out(422,['ok'=>false,'message'=>'একটি ঘরে নির্ধারিত সীমার চেয়ে বেশি লেখা হয়েছে।']); return $v; }

$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET' && ($_GET['action']??'')==='token'){
 if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
 cms_out(200,['ok'=>true,'csrf'=>$_SESSION['csrf']]);
}
if($method!=='POST') cms_out(405,['ok'=>false,'message'=>'অনুমোদিত অনুরোধ নয়।']);
$d=cms_input(); cms_csrf($d); cms_require_admin();
$action=(string)($d['action']??'');
if($action==='list'){
 $items=cms_read(); usort($items,fn($a,$b)=>strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??'')));
 $kb=__DIR__.'/data/knowledge-base.json';$kd=is_file($kb)?json_decode((string)file_get_contents($kb),true):[];
 $published=count(array_filter((array)($kd['entries']??[]),fn($e)=>($e['status']??'')==='published'));
 $website=__DIR__.'/knowledge.json';$wd=is_file($website)?json_decode((string)file_get_contents($website),true):[];
 cms_out(200,['ok'=>true,'items'=>$items,'operations'=>['openai_configured'=>trim((string)getenv('OPENAI_API_KEY'))!=='','embedding_configured'=>trim((string)getenv('OPENAI_EMBEDDING_MODEL'))!=='','admin_password_configured'=>trim((string)getenv('UTTC_ADMIN_PASSWORD_HASH'))!=='','knowledge_published'=>$published,'website_indexed'=>count((array)($wd['pages']??[])),'php_version'=>PHP_VERSION,'last_cms_update'=>is_file($p=cms_dir().'/cms-content.json')?gmdate('c',filemtime($p)):null]]);
}
if($action==='save'){
 $id=cms_text($d['id']??'',48);$type=cms_text($d['type']??'',20);$title=cms_text($d['title']??'',180);$body=cms_text($d['body']??'',12000);$url=cms_text($d['url']??'',500);$image=cms_text($d['image_url']??'',500);$slug=cms_text($d['slug']??'',180);
 if(!in_array($type,['course','notice','gallery','page','contact'],true)||$title===''||$body==='') cms_out(422,['ok'=>false,'message'=>'ধরন, শিরোনাম ও বিবরণ অবশ্যই দিতে হবে।']);
 foreach(['url'=>$url,'image_url'=>$image] as $field=>$value){if($value!==''&&!filter_var($value,FILTER_VALIDATE_URL)) cms_out(422,['ok'=>false,'message'=>'লিংক ও ছবির ঠিকানা পূর্ণ URL হতে হবে।']); if($value!==''&&!in_array((string)parse_url($value,PHP_URL_SCHEME),['https'],true)) cms_out(422,['ok'=>false,'message'=>'নিরাপত্তার জন্য শুধু HTTPS লিংক গ্রহণ করা হয়।']);}
 $items=cms_read();$now=gmdate('c');$found=false;
 foreach($items as &$item){if($id!==''&&($item['id']??'')===$id){$item=array_merge($item,['type'=>$type,'title'=>$title,'body'=>$body,'url'=>$url,'image_url'=>$image,'slug'=>$slug,'status'=>'draft','updated_at'=>$now,'published_at'=>null]);$found=true;break;}}unset($item);
 if(!$found)$items[]=['id'=>bin2hex(random_bytes(12)),'type'=>$type,'title'=>$title,'body'=>$body,'url'=>$url,'image_url'=>$image,'slug'=>$slug,'status'=>'draft','created_at'=>$now,'updated_at'=>$now,'published_at'=>null];
 cms_write($items);cms_out(200,['ok'=>true,'message'=>'Draft সংরক্ষণ হয়েছে।']);
}
if($action==='publish'||$action==='delete'){
 $id=cms_text($d['id']??'',48);$items=cms_read();$found=false;
 foreach($items as $i=>&$item){if(($item['id']??'')===$id){$found=true;if($action==='publish'){$item['status']='published';$item['published_at']=gmdate('c');$item['updated_at']=$item['published_at'];}else unset($items[$i]);break;}}unset($item);
 if(!$found)cms_out(404,['ok'=>false,'message'=>'রেকর্ড পাওয়া যায়নি।']);cms_write($items);cms_out(200,['ok'=>true,'message'=>$action==='publish'?'তথ্য প্রকাশিত হয়েছে।':'তথ্য মুছে ফেলা হয়েছে।']);
}
cms_out(400,['ok'=>false,'message'=>'অজানা অনুরোধ।']);
