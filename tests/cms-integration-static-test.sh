#!/usr/bin/env bash
set -euo pipefail
pages=(
  index.html
  about-uttc-moulvibazar.html
  404.html
  contact-uttc-moulvibazar.html
  facilities-uttc-moulvibazar.html
  faq-uttc-moulvibazar.html
  privacy-policy.html
  notices-uttc-moulvibazar.html
  gallery-uttc-moulvibazar.html
  driving-training-moulvibazar.html
  women-driving-training-moulvibazar.html
  computer-office-application-moulvibazar.html
  freelancing-course-moulvibazar.html
  graphic-design-course-moulvibazar.html
)
for page in "${pages[@]}"; do
  test -f "$page" || { echo "Missing public page: $page"; exit 1; }
  grep -q 'data-page-key=' "$page" || { echo "Missing CMS page key: $page"; exit 1; }
  grep -q 'assets/js/cms-public.js' "$page" || { echo "Missing CMS public integration: $page"; exit 1; }
  grep -q 'class="skip-link" href="#main-content"' "$page" || { echo "Missing keyboard skip link: $page"; exit 1; }
  grep -q 'id="main-content"' "$page" || { echo "Missing main landmark target: $page"; exit 1; }
  grep -q '.skip-link:focus' "$page" || { echo "Missing visible skip-link focus style: $page"; exit 1; }
  grep -q 'prefers-reduced-motion:reduce' "$page" || { echo "Missing reduced-motion support: $page"; exit 1; }
done
grep -q 'data-cms-feed="notice"' notices-uttc-moulvibazar.html
grep -q 'data-cms-feed="gallery"' gallery-uttc-moulvibazar.html
for page in driving-training-moulvibazar.html women-driving-training-moulvibazar.html computer-office-application-moulvibazar.html freelancing-course-moulvibazar.html graphic-design-course-moulvibazar.html; do
  grep -q 'data-cms-feed="course"' "$page" || { echo "Missing course feed: $page"; exit 1; }
done
grep -q 'Require all denied' api/data/.htaccess
grep -q 'status.*published' api/cms-public.php
grep -q "item.page_key===pageKey" assets/js/cms-public.js
grep -q "const feedCache=new Map()" assets/js/cms-public.js
grep -q "feedCache.delete(type)" assets/js/cms-public.js
grep -q "function publishedDate(value)" assets/js/cms-public.js
grep -q "cms-published-date" assets/js/cms-public.js
grep -q "new Intl.DateTimeFormat" assets/js/cms-public.js
grep -q "cms-gallery-card" assets/js/cms-public.js
grep -q "renderCard(item,type)" assets/js/cms-public.js
grep -q "async function mountPageContent()" assets/js/cms-public.js
grep -q "x.slug===key" assets/js/cms-public.js
grep -q "main.append(section)" assets/js/cms-public.js
grep -q "async function mountContact()" assets/js/cms-public.js
grep -q "contact-uttc-moulvibazar" assets/js/cms-public.js
grep -q "feed('contact')" assets/js/cms-public.js
grep -q "page_key" api/cms-public.php
grep -q 'কোর্সের জন্য আবশ্যক' admin/cms.php
grep -q "coursePageKeys" api/cms.php
grep -q "publicPageKeys" api/cms.php
grep -q "cms_out(409" api/cms.php
grep -Fq "isset(\$parts['user'])" api/cms.php
grep -Fq "isset(\$parts['pass'])" api/cms.php
grep -q "u.username" assets/js/cms-public.js
grep -q "u.password" assets/js/cms-public.js
grep -q "type==='page'" api/cms.php
grep -q 'id="previewMeta"' admin/cms.php
grep -q "previewMeta').textContent=meta.join" admin/cms.php
grep -q "header \.nav-links,header nav ul,header nav" assets/js/cms-public.js
grep -q "nav.matches('ul,ol')" assets/js/cms-public.js
grep -q "li.append(a)" assets/js/cms-public.js
grep -q "textContent=text" assets/js/cms-public.js
grep -q "!u.username&&!u.password" assets/js/cms-public.js
grep -q "og:title" assets/js/cms-public.js
grep -q "og:description" assets/js/cms-public.js
grep -q "setMeta('meta\[name=" assets/js/cms-public.js

cms_page="$(cat admin/cms.php)"
grep -q '<dialog id="previewDialog"' <<<"$cms_page"
grep -q 'data-preview=' <<<"$cms_page"
grep -q "\$('previewBody').textContent=item.body" <<<"$cms_page"
grep -q "\$('previewDialog').showModal()" <<<"$cms_page"
grep -q "u.protocol==='https:'&&!u.username&&!u.password" <<<"$cms_page"
grep -q 'role="status" aria-live="polite" aria-atomic="true"' <<<"$cms_page"
grep -q 'if(submit.disabled)return' <<<"$cms_page"
grep -q "submit.disabled=true" <<<"$cms_page"
grep -q "submit.textContent='সংরক্ষণ হচ্ছে…'" <<<"$cms_page"
grep -q "finally{submit.disabled=false" <<<"$cms_page"
grep -q 'if(b.disabled)return' <<<"$cms_page"
grep -q 'b.disabled=true' <<<"$cms_page"
grep -q 'finally{b.disabled=false' <<<"$cms_page"
grep -q "syncFields();window.scrollTo" <<<"$cms_page"

index_page="$(cat index.html)"
grep -q 'aria-controls="navLinks"' <<<"$index_page"
grep -q "function setMenuOpen(open)" <<<"$index_page"
grep -q "event.key==='Escape'" <<<"$index_page"
grep -q "menuBtn.focus()" <<<"$index_page"
grep -q "event.target.closest('a')" <<<"$index_page"
grep -q "aria-label.*মূল মেনু" <<<"$index_page"
grep -q "addEventListener('error'.*img.remove()" assets/js/cms-public.js

echo "CMS static integration checks passed."

grep -q 'id="search"' admin/cms.php
grep -q 'id="statusFilter"' admin/cms.php
grep -q 'id="resultCount"' admin/cms.php
grep -q "toLocaleLowerCase().includes(query)" admin/cms.php
grep -q "statusFilter==='published'" admin/cms.php
grep -q "addEventListener('input',render)" admin/cms.php
