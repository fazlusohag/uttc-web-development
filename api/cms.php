<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
session_name('UTTC_KB_ADMIN');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'httponly'=>true,'samesite'=>'Strict']);
ini_set('session.use_strict_mode','1');
session_start();
function cms_out(int $s,array $d): never {http_response_code($s);echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function cms_input(): array {$v=json_decode(file_get_contents('php://input')?:'',true);return is_array($v)?$v:[];}
function cms_admin(): void {if(empty($_SESSION['admin']))cms_out(401,['ok'=>false,'message'=>'প্রথমে Admin Dashboard-এ লগইন করুন।']);}
function cms_csrf(array $d): void {if(empty($_SESSION['csrf'])||!hash_equals((string)$_SESSION['csrf'],(string)($d['csrf']??'')))cms_out(419,['ok'=>false,'message'=>'নিরাপত্তা যাচাই ব্যর্থ। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।']);}
function cms_dir(): string {$p=__DIR__.'/data';if(!is_dir($p)&&!mkdir($p,0750,true)&&!is_dir($p))cms_out(500,['ok'=>false,'message'=>'ডেটা ফোল্ডার তৈরি করা যায়নি।']);return $p;}
function cms_lock(){ $h=@fopen(cms_dir().'/cms.lock','c');if(!$h||!flock($h,LOCK_EX))cms_out(500,['ok'=>false,'message'=>'ডেটা লক করা যাচ্ছে না।']);return $h;}
function cms_read(): array {$p=cms_dir().'/cms-content.json';if(!is_file($p))return [];$d=json_decode((string)file_get_contents($p),true);return is_array($d)&&is_array($d['items']??null)?$d['items']:[];}
function cms_write(array $items): void {$p=cms_dir().'/cms-content.json';$tmp=$p.'.'.bin2hex(random_bytes(6)).'.tmp';$json=json_encode(['updated_at'=>gmdate('c'),'items'=>array_values($items)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if($json===false||file_put_contents($tmp,$json,LOCK_EX)===false||!rename($tmp,$p)){@unlink($tmp);cms_out(500,['ok'=>false,'message'=>'তথ্য সংরক্ষণ করা যায়নি।']);}}
function cms_audit(string $action,array $item): void {$line=json_encode(['at'=>gmdate('c'),'action'=>$action,'id'=>$item['id']??null,'type'=>$item['type']??null],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($line!==false)file_put_contents(cms_dir().'/cms-audit.jsonl',$line."\n",FILE_APPEND|LOCK_EX);}
function cms_text(mixed $v,int $max): string {if(!is_string($v))return '';$v=trim(str_replace("\0",'',$v));if((function_exists('mb_strlen')?mb_strlen($v,'UTF-8'):strlen($v))>$max)cms_out(422,['ok'=>false,'message'=>'একটি ঘরে নির্ধারিত সীমার চেয়ে বেশি লেখা হয়েছে।']);return $v;}
function cms_https(string $v,string $field): void {if($v!==''&&(!filter_var($v,FILTER_VALIDATE_URL)||parse_url($v,PHP_URL_SCHEME)!=='https'))cms_out(422,['ok'=>false,'message'=>$field.'-এ শুধু পূর্ণ HTTPS URL গ্রহণ করা হয়।']);}
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&($_GET['action']??'')==='token'){if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));cms_out(200,['ok'=>true,'csrf'=>$_SESSION['csrf']]);}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')cms_out(405,['ok'=>false,'message'=>'অনুমোদিত অনুরোধ নয়।']);
$d=cms_input();cms_csrf($d);cms_admin();$action=(string)($d['action']??'');$lock=cms_lock();
if($action==='list'){
 $items=cms_read();usort($items,fn($a,$b)=>strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??'')));
 $kd=is_file(__DIR__.'/data/knowledge-base.json')?json_decode((string)file_get_contents(__DIR__.'/data/knowledge-base.json'),true):[];
 $wd=is_file(__DIR__.'/knowledge.json')?json_decode((string)file_get_contents(__DIR__.'/knowledge.json'),true):[];
 flock($lock,LOCK_UN);fclose($lock);
 cms_out(200,['ok'=>true,'items'=>$items,'operations'=>['openai_configured'=>trim((string)getenv('OPENAI_API_KEY'))!=='','embedding_configured'=>trim((string)getenv('OPENAI_EMBEDDING_MODEL'))!=='','admin_password_configured'=>trim((string)getenv('UTTC_ADMIN_PASSWORD_HASH'))!=='','knowledge_published'=>count(array_filter((array)($kd['entries']??[]),fn($e)=>($e['status']??'')==='published')),'website_indexed'=>count((array)($wd['pages']??[])),'php_version'=>PHP_VERSION,'last_cms_update'=>is_file($p=cms_dir().'/cms-content.json')?gmdate('c',filemtime($p)):null]]);
}
if($action==='save'){
 $id=cms_text($d['id']??'',48);$type=cms_text($d['type']??'',20);$title=cms_text($d['title']??'',180);$body=cms_text($d['body']??'',12000);$url=cms_text($d['url']??'',500);$image=cms_text($d['image_url']??'',500);$slug=cms_text($d['slug']??'',180);$mt=cms_text($d['meta_title']??'',180);$md=cms_text($d['meta_description']??'',320);$canonical=cms_text($d['canonical_url']??'',500);
 if(!in_array($type,['course','notice','gallery','page','contact','menu'],true)||$title===''||$body==='')cms_out(422,['ok'=>false,'message'=>'ধরন, শিরোনাম ও বিবরণ অবশ্যই দিতে হবে।']);
 cms_https($url,'লিংক');cms_https($image,'ছবির URL');cms_https($canonical,'Canonical URL');
 if($slug!==''&&!preg_match('/^[a-z0-9][a-z0-9-]{0,178}$/',$slug))cms_out(422,['ok'=>false,'message'=>'Slug ইংরেজি ছোট হাতের অক্ষর, সংখ্যা ও hyphen দিয়ে লিখুন।']);
 $items=cms_read();$now=gmdate('c');$found=false;$auditAction='create';$auditItem=null;
 foreach($items as &$it){if($id!==''&&($it['id']??'')===$id){$it=array_merge($it,['type'=>$type,'title'=>$title,'body'=>$body,'url'=>$url,'image_url'=>$image,'slug'=>$slug,'meta_title'=>$mt,'meta_description'=>$md,'canonical_url'=>$canonical,'status'=>'draft','updated_at'=>$now,'published_at'=>null]);$found=true;$auditAction='update';$auditItem=$it;break;}}unset($it);
 if($id!==''&&!$found)cms_out(404,['ok'=>false,'message'=>'সম্পাদনার রেকর্ড পাওয়া যায়নি।']);
 if(!$found){$it=['id'=>bin2hex(random_bytes(12)),'type'=>$type,'title'=>$title,'body'=>$body,'url'=>$url,'image_url'=>$image,'slug'=>$slug,'meta_title'=>$mt,'meta_description'=>$md,'canonical_url'=>$canonical,'status'=>'draft','created_at'=>$now,'updated_at'=>$now,'published_at'=>null];$items[]=$it;$auditItem=$it;}
 cms_write($items);if(is_array($auditItem))cms_audit($auditAction,$auditItem);flock($lock,LOCK_UN);fclose($lock);cms_out(200,['ok'=>true,'message'=>'Draft সংরক্ষণ হয়েছে।']);
}
if($action==='publish'||$action==='delete'){
 $id=cms_text($d['id']??'',48);$items=cms_read();$found=false;$auditItem=null;
 foreach($items as $i=>&$it){if(($it['id']??'')===$id){$found=true;$auditItem=$it;if($action==='publish'){$it['status']='published';$it['published_at']=gmdate('c');$it['updated_at']=$it['published_at'];}else{unset($items[$i]);}break;}}unset($it);
 if(!$found)cms_out(404,['ok'=>false,'message'=>'রেকর্ড পাওয়া যায়নি।']);cms_write($items);if(is_array($auditItem))cms_audit($action,$auditItem);flock($lock,LOCK_UN);fclose($lock);cms_out(200,['ok'=>true,'message'=>$action==='publish'?'তথ্য প্রকাশিত হয়েছে।':'তথ্য মুছে ফেলা হয়েছে।']);
}
flock($lock,LOCK_UN);fclose($lock);cms_out(400,['ok'=>false,'message'=>'অজানা অনুরোধ।']);
