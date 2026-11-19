<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60, stale-while-revalidate=120');
header('X-Content-Type-Options: nosniff');
$path=__DIR__.'/data/cms-content.json';
$data=is_file($path)?json_decode((string)file_get_contents($path),true):[];
$items=is_array($data)&&is_array($data['items']??null)?$data['items']:[];
$type=trim((string)($_GET['type']??''));
$allowed=['course','notice','gallery','page','contact','menu'];
if($type!==''&&!in_array($type,$allowed,true)){http_response_code(400);echo json_encode(['ok'=>false,'message'=>'Invalid type']);exit;}
$items=array_values(array_filter($items,static fn($item)=>is_array($item)&&($item['status']??'')==='published'&&in_array(($item['type']??''),$allowed,true)&&($type===''||($item['type']??'')===$type)));
$items=array_map(static fn($item)=>['id'=>$item['id']??'','type'=>$item['type']??'','title'=>$item['title']??'','body'=>$item['body']??'','url'=>$item['url']??'','image_url'=>$item['image_url']??'','slug'=>$item['slug']??'','page_key'=>$item['page_key']??'','meta_title'=>$item['meta_title']??'','meta_description'=>$item['meta_description']??'','canonical_url'=>$item['canonical_url']??'','published_at'=>$item['published_at']??null],$items);
echo json_encode(['ok'=>true,'items'=>$items],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
