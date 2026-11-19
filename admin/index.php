<?php
declare(strict_types=1);
?><!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>UTTC AI Knowledge Base</title>
<style>
:root{--navy:#142d4e;--green:#237a57;--ink:#17283d;--muted:#64748b;--line:#e5eaf0;--bg:#f3f5f8;--white:#fff;--red:#b42318;--amber:#9a6700}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,"Noto Sans Bengali",system-ui,-apple-system,"Segoe UI",sans-serif}button,input,textarea,select{font:inherit}.shell{min-height:100vh}.top{background:var(--navy);color:#fff;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px}.brand{display:flex;align-items:center;gap:12px}.brand img{width:42px;height:42px;object-fit:contain;background:#fff;border-radius:50%;padding:3px}.brand strong{display:block;font-size:16px}.brand small{color:#c8d7e7}.wrap{max-width:1200px;margin:auto;padding:26px 20px}.welcome{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:22px}.welcome h1{margin:0 0 6px;font-size:clamp(22px,3vw,30px)}.welcome p{margin:0;color:var(--muted)}.stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:20px}.stat,.panel{background:var(--white);border:1px solid var(--line);border-radius:16px;box-shadow:0 4px 16px #142d4e08}.stat{padding:18px}.stat span{display:block;color:var(--muted);font-size:13px}.stat b{display:block;font-size:28px;margin-top:6px}.layout{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(320px,.8fr);gap:18px}.panel{padding:20px}.panel h2{font-size:18px;margin:0 0 16px}.toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}.field{display:grid;gap:6px;margin-bottom:13px}.field label{font-size:13px;font-weight:650}.field input,.field textarea,.field select,.toolbar input{width:100%;border:1px solid #d6dee8;border-radius:10px;padding:11px 12px;background:#fff;color:var(--ink);outline:none}.field input:focus,.field textarea:focus,.field select:focus,.toolbar input:focus{border-color:var(--green);box-shadow:0 0 0 3px #237a5718}.field textarea{min-height:115px;resize:vertical}.btn{border:0;border-radius:10px;padding:10px 15px;font-weight:650;cursor:pointer;background:var(--green);color:#fff}.btn.secondary{background:#eaf0f5;color:var(--navy)}.btn.danger{background:#fff0ef;color:var(--red)}.btn:disabled{opacity:.55;cursor:not-allowed}.muted{color:var(--muted);font-size:13px}.list{display:grid;gap:10px}.item{border:1px solid var(--line);border-radius:12px;padding:13px;display:grid;gap:8px}.itemtop{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.item strong{font-size:14px}.pill{font-size:11px;border-radius:99px;padding:4px 8px;background:#fff4d6;color:var(--amber);white-space:nowrap}.pill.live{background:#e8f5ee;color:var(--green)}.itemactions{display:flex;gap:7px;flex-wrap:wrap}.itemactions button{font-size:12px;padding:7px 10px}.hidden{display:none!important}.notice{padding:11px 13px;border-radius:10px;margin-bottom:14px;font-size:13px;display:none}.notice.show{display:block}.notice.error{background:#fff0ef;color:var(--red)}.notice.ok{background:#e8f5ee;color:#176344}.login{max-width:430px;margin:9vh auto;padding:28px}.login h1{margin:0 0 6px}.filebox{border:1px dashed #b8c6d5;border-radius:12px;padding:16px;background:#f9fbfd}.filebox input{max-width:100%}.footer{padding:22px;text-align:center;color:var(--muted);font-size:12px}@media(max-width:800px){.layout{grid-template-columns:1fr}.stats{grid-template-columns:repeat(3,1fr)}.top{padding:14px 16px}.wrap{padding:18px 14px}}@media(max-width:520px){.stats{grid-template-columns:1fr 1fr}.welcome{display:block}.welcome .btn{margin-top:14px}.brand small{font-size:11px}}
</style>
</head>
<body>
<div class="shell">
<header class="top"><div class="brand"><img src="../uttc-logo.png" alt="UTTC"><div><strong>UTTC AI Knowledge Base</strong><small>প্রশাসনিক তথ্যভান্ডার</small></div></div><button id="logoutBtn" class="btn secondary hidden" type="button">লগআউট</button></header>
<main class="wrap">
<section id="loginView" class="panel login">
<h1>Admin Login</h1><p class="muted">শুধু অনুমোদিত UTTC কর্তৃপক্ষের জন্য।</p>
<div id="loginNotice" class="notice"></div>
<form id="loginForm"><div class="field"><label for="password">অ্যাডমিন পাসওয়ার্ড</label><input id="password" type="password" autocomplete="current-password" required></div><button class="btn" type="submit">নিরাপদে প্রবেশ করুন</button></form>
<p class="muted">প্রবেশের জন্য সার্ভারে অ্যাডমিন পাসওয়ার্ড সেটআপ থাকতে হবে।</p>
</section>
<section id="appView" class="hidden">
<div class="welcome"><div><h1>Knowledge Base Dashboard</h1><p>তথ্য যোগ করুন, যাচাই করুন এবং AI-এর জন্য প্রকাশ করুন।</p></div><button id="refreshBtn" class="btn secondary" type="button">তথ্য রিফ্রেশ</button><button id="historyBtn" class="btn secondary" type="button">আপডেট ইতিহাস</button></div>
<div id="appNotice" class="notice"></div>
<section id="historyPanel" class="panel hidden" style="margin-bottom:18px"><h2>Knowledge Base Update History</h2><div id="historyList" class="list"><p class="muted">ইতিহাস দেখতে বোতামে চাপ দিন।</p></div></section>
<div class="stats"><div class="stat"><span>মোট তথ্য</span><b id="totalCount">০</b></div><div class="stat"><span>Draft / পর্যালোচনা</span><b id="draftCount">০</b></div><div class="stat"><span>AI-তে প্রকাশিত</span><b id="publishedCount">০</b></div></div>
<section class="panel" style="margin-bottom:18px">
<h2>AI Knowledge Retrieval Test</h2>
<p class="muted">প্রশ্ন লিখে দেখুন ওয়েবসাইট ও Published Knowledge Base থেকে কোন তথ্য পাওয়া যাচ্ছে। এটি তথ্য খোঁজার পরীক্ষা; AI-এর চূড়ান্ত উত্তর পরীক্ষা নয়।</p>
<form id="retrievalTestForm"><div class="toolbar"><input id="testQuestion" maxlength="1200" required placeholder="যেমন: ড্রাইভিং কোর্সে ভর্তির যোগ্যতা কী?"><button class="btn" type="submit">তথ্য খুঁজে পরীক্ষা করুন</button></div></form>
<div id="retrievalTestResult" class="list"><p class="muted">এখনো কোনো পরীক্ষা চালানো হয়নি।</p></div>
</section>
<div class="layout">
<section class="panel"><h2 id="formHeading">নতুন প্রশ্ন ও উত্তর যোগ করুন</h2>
<form id="entryForm">
<input id="entryId" type="hidden">
<div class="field"><label for="title">তথ্যের শিরোনাম</label><input id="title" maxlength="180" required placeholder="যেমন: ড্রাইভিং কোর্সে ভর্তি"></div>
<div class="field"><label for="category">বিভাগ</label><select id="category"><option>ভর্তি</option><option>কোর্স</option><option>ফি</option><option>পরীক্ষা</option><option>নোটিশ</option><option>সনদ</option><option>যোগাযোগ</option><option>অন্যান্য</option></select></div>
<div class="field"><label for="question">প্রশ্ন (ঐচ্ছিক)</label><textarea id="question" maxlength="3000" placeholder="শিক্ষার্থীরা কীভাবে প্রশ্ন করতে পারে?"></textarea></div>
<div class="field"><label for="answer">অনুমোদিত উত্তর / তথ্য</label><textarea id="answer" maxlength="30000" required placeholder="ওয়েবসাইটে নেই এমন তথ্য হলে কর্তৃপক্ষ যাচাই করে এখানে লিখুন।"></textarea></div>
<div class="toolbar"><button class="btn" type="submit">Draft হিসেবে সংরক্ষণ</button><button id="resetBtn" class="btn secondary" type="button">ফর্ম পরিষ্কার</button></div>
</form>
<hr style="border:0;border-top:1px solid var(--line);margin:20px 0">
<h2>ডকুমেন্ট আপলোড</h2><div class="filebox"><form id="uploadForm"><div class="field"><label for="document">PDF, Word (DOCX), TXT বা Markdown</label><input id="document" type="file" accept=".pdf,.docx,.txt,.md" required></div><p class="muted">সর্বোচ্চ ৮ MB। আপলোড করা ফাইলের তথ্য প্রথমে Draft হবে; যাচাইয়ের পর Publish করতে হবে। সার্ভারে প্রয়োজনীয় PDF/DOCX পাঠ-সুবিধা না থাকলে সিস্টেম জানিয়ে দেবে।</p><button class="btn secondary" type="submit">ফাইল আপলোড ও Draft তৈরি</button></form></div>
</section>
<section class="panel"><h2>সংরক্ষিত তথ্য</h2><div class="toolbar"><input id="searchInput" placeholder="শিরোনাম বা তথ্য খুঁজুন..."></div><div id="entryList" class="list"><p class="muted">তথ্য লোড হচ্ছে...</p></div></section>
</div>
<p class="muted" style="margin-top:18px">নিরাপত্তা: কেবল Published তথ্যই AI-এর উত্তরে ব্যবহার হবে। Draft তথ্য শিক্ষার্থীদের উত্তরে যাবে না।</p>
</section>
</main><footer class="footer">উপজেলা কারিগরি প্রশিক্ষণ কেন্দ্র (UTTC), মৌলভীবাজার · অফিসিয়াল যোগাযোগ: 01727-007417</footer>
</div>
<script>
const API='../api/admin.php';let csrf='';let entries=[];
const $=id=>document.getElementById(id);
function showNotice(id,msg,type='ok'){const n=$(id);n.textContent=msg;n.className='notice show '+type;}
async function request(action,body={},file=null){const fd=file?new FormData():null;let options;if(file){fd.append('action',action);fd.append('csrf',csrf);fd.append('document',file);options={method:'POST',body:fd};}else{options={method:action==='token'?'GET':'POST',headers:{'Content-Type':'application/json'},...(action==='token'?{}:{body:JSON.stringify({action,csrf,...body})})};}const res=await fetch(API+(action==='token'?'?action=token':''),options);const data=await res.json().catch(()=>({ok:false,message:'সার্ভারের উত্তর পড়া যায়নি।'}));if(!res.ok||!data.ok)throw new Error(data.message||'অনুরোধ সম্পন্ন হয়নি।');return data;}
function bn(n){return new Intl.NumberFormat('bn-BD').format(n);}
function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function resetForm(){$('entryForm').reset();$('entryId').value='';$('formHeading').textContent='নতুন প্রশ্ন ও উত্তর যোগ করুন';}
function render(){const q=$('searchInput').value.toLowerCase();const filtered=entries.filter(e=>(e.title+' '+e.question+' '+e.answer+' '+e.category).toLowerCase().includes(q));$('totalCount').textContent=bn(entries.length);$('draftCount').textContent=bn(entries.filter(e=>e.status!=='published').length);$('publishedCount').textContent=bn(entries.filter(e=>e.status==='published').length);$('entryList').innerHTML=filtered.length?filtered.map(e=>'<article class="item"><div class="itemtop"><strong>'+escapeHtml(e.title)+'</strong><span class="pill '+(e.status==='published'?'live':'')+'">'+(e.status==='published'?'প্রকাশিত':'Draft')+'</span></div><span class="muted">'+escapeHtml(e.category||'অন্যান্য')+(e.file_name?' · '+escapeHtml(e.file_name):'')+'</span><div class="itemactions"><button class="btn secondary" data-edit="'+escapeHtml(e.id)+'">সম্পাদনা</button>'+(e.status==='published'?'':'<button class="btn" data-publish="'+escapeHtml(e.id)+'">Publish</button>')+'<button class="btn danger" data-delete="'+escapeHtml(e.id)+'">মুছুন</button></div></article>').join(''):'<p class="muted">কোনো তথ্য পাওয়া যায়নি।</p>';}
async function load(){const d=await request('list');entries=d.entries||[];render();}
$('loginForm').addEventListener('submit',async e=>{e.preventDefault();try{const t=await request('token');csrf=t.csrf;await request('login',{password:$('password').value});$('loginView').classList.add('hidden');$('appView').classList.remove('hidden');$('logoutBtn').classList.remove('hidden');await load();}catch(err){showNotice('loginNotice',err.message,'error');}});
$('entryForm').addEventListener('submit',async e=>{e.preventDefault();try{await request('save',{id:$('entryId').value,title:$('title').value,category:$('category').value,question:$('question').value,answer:$('answer').value});showNotice('appNotice','তথ্য Draft হিসেবে সংরক্ষণ করা হয়েছে। যাচাই করে Publish করুন।');resetForm();await load();}catch(err){showNotice('appNotice',err.message,'error');}});
$('uploadForm').addEventListener('submit',async e=>{e.preventDefault();try{const file=$('document').files[0];if(!file)throw new Error('একটি ফাইল নির্বাচন করুন।');await request('upload',{},file);showNotice('appNotice','ফাইল থেকে তথ্য Draft হিসেবে তৈরি হয়েছে। Publish করার আগে যাচাই করুন।');$('uploadForm').reset();await load();}catch(err){showNotice('appNotice',err.message,'error');}});
$('entryList').addEventListener('click',async e=>{const b=e.target.closest('button');if(!b)return;const id=b.dataset.edit||b.dataset.publish||b.dataset.delete;const item=entries.find(x=>x.id===id);try{if(b.dataset.edit){$('entryId').value=item.id;$('title').value=item.title;$('category').value=item.category;$('question').value=item.question||'';$('answer').value=item.answer;$('formHeading').textContent='তথ্য সম্পাদনা করুন';window.scrollTo({top:0,behavior:'smooth'});}else if(b.dataset.publish){if(!confirm('যাচাই করা হয়েছে? এই তথ্য AI-এর উত্তরে ব্যবহার হবে।'))return;await request('publish',{id});showNotice('appNotice','তথ্য AI-এর জন্য প্রকাশ করা হয়েছে।');await load();}else if(b.dataset.delete){if(!confirm('এই তথ্য স্থায়ীভাবে মুছে ফেলবেন?'))return;await request('delete',{id});showNotice('appNotice','তথ্য মুছে ফেলা হয়েছে।');await load();}}catch(err){showNotice('appNotice',err.message,'error');}});
$('retrievalTestForm').addEventListener('submit',async e=>{
 e.preventDefault();const box=$('retrievalTestResult');box.innerHTML='<p class="muted">তথ্য খোঁজা হচ্ছে...</p>';
 try{const d=await request('test_retrieval',{question:$('testQuestion').value});
 if(!d.matches.length){box.innerHTML='<p class="muted">কোনো প্রাসঙ্গিক তথ্য পাওয়া যায়নি। এই প্রশ্নের উত্তর অনুমান করে দেওয়া উচিত নয়।</p>';return;}
 box.innerHTML=d.matches.map(m=>'<article class="item"><div class="itemtop"><strong>'+escapeHtml(m.title)+'</strong><span class="pill '+(m.source_type==='admin'?'live':'')+'">'+(m.source_type==='admin'?'Published Admin':'Website')+' · Score '+escapeHtml(m.score)+'</span></div>'+(m.url?'<a href="'+escapeHtml(m.url)+'" target="_blank" rel="noopener noreferrer">ওয়েবসাইট পৃষ্ঠা খুলুন</a>':'')+'<p class="muted">'+escapeHtml(m.excerpt)+'</p></article>').join('');
 }catch(err){box.innerHTML='<p class="muted">'+escapeHtml(err.message)+'</p>';}
});
 $('historyBtn').addEventListener('click',async()=>{const panel=$('historyPanel');panel.classList.toggle('hidden');if(panel.classList.contains('hidden'))return;const box=$('historyList');box.innerHTML='<p class="muted">ইতিহাস লোড হচ্ছে...</p>';try{const d=await request('history');box.innerHTML=d.versions.length?d.versions.map(v=>'<article class="item"><strong>'+escapeHtml(new Date(v.saved_at).toLocaleString())+'</strong><span class="muted">আগের সংস্করণ · '+bn(v.entry_count)+'টি তথ্য</span><p class="muted">'+escapeHtml((v.titles||[]).join(' · '))+'</p></article>').join(''):'<p class="muted">এখনো কোনো পরিবর্তনের ইতিহাস নেই। পরবর্তী পরিবর্তনের আগে বর্তমান সংস্করণ থেকে ইতিহাস তৈরি হবে।</p>';}catch(err){box.innerHTML='<p class="muted">'+escapeHtml(err.message)+'</p>';}});
$('resetBtn').addEventListener('click',resetForm);$('refreshBtn').addEventListener('click',()=>load().catch(e=>showNotice('appNotice',e.message,'error')));$('searchInput').addEventListener('input',render);
$('logoutBtn').addEventListener('click',async()=>{try{await request('logout');}catch(e){}location.reload();});
(async()=>{try{const t=await request('token');csrf=t.csrf;const d=await request('list');entries=d.entries||[];$('loginView').classList.add('hidden');$('appView').classList.remove('hidden');$('logoutBtn').classList.remove('hidden');render();}catch(e){if(e.message.includes('লগইন')||e.message.includes('অনুমতি')){}else if(!e.message.includes('প্রবেশ'))showNotice('loginNotice',e.message,'error');}})();
</script>
</body></html>
