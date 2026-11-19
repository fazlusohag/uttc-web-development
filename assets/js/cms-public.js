(function(){
'use strict';
const labels={course:'প্রশিক্ষণ ও কোর্স',notice:'সর্বশেষ নোটিশ',gallery:'প্রশিক্ষণ গ্যালারি',page:'প্রকাশিত তথ্য',contact:'যোগাযোগের তথ্য'};
function el(tag,cls,text){const n=document.createElement(tag);if(cls)n.className=cls;if(text)n.textContent=text;return n;}
async function mount(box){const type=box.dataset.cmsFeed;try{const res=await fetch('/api/cms-public.php?type='+encodeURIComponent(type),{headers:{Accept:'application/json'}});if(!res.ok)throw new Error('HTTP '+res.status);const data=await res.json();if(!data.ok)throw new Error('CMS response invalid');box.replaceChildren();if(!data.items.length){box.append(el('p','muted','এখনো কোনো যাচাইকৃত তথ্য প্রকাশ করা হয়নি। সর্বশেষ তথ্য জানতে UTTC অফিসে যোগাযোগ করুন।'));return;}
data.items.forEach(item=>{const card=el('article','card cms-card');if(item.image_url){try{const u=new URL(item.image_url);if(u.protocol==='https:'){const img=el('img','cms-image');img.src=u.href;img.alt=item.title;img.loading='lazy';img.referrerPolicy='no-referrer';card.append(img);}}catch(_){}}
card.append(el('h2','',item.title));card.append(el('p','',item.body));if(item.url){try{const u=new URL(item.url);if(u.protocol==='https:'){const a=el('a','btn','বিস্তারিত দেখুন');a.href=u.href;a.target='_blank';a.rel='noopener noreferrer';card.append(a);}}catch(_){}}
box.append(card);});}catch(e){box.replaceChildren(el('p','muted','এই মুহূর্তে প্রকাশিত তথ্য লোড করা যাচ্ছে না। অনুগ্রহ করে পরে আবার চেষ্টা করুন।'));}}
document.querySelectorAll('[data-cms-feed]').forEach(mount);
})();