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

# CMS storage writes must handle partial writes and replace atomically only after a complete flush.
grep -q "fopen(\$tmp,'xb')" api/cms.php
grep -q 'while(\$written<\$length)' api/cms.php
grep -q 'fflush(\$h)' api/cms.php
grep -q 'fsync(\$h)' api/cms.php
grep -q '!\$ok||\$written!==\$length||!@rename(\$tmp,\$p)' api/cms.php

grep -q 'cms_assert_unique_target' api/cms.php
grep -q 'একই ID-র একাধিক পুরোনো রেকর্ড' api/cms.php
grep -q 'legacy-duplicate-id' tests/cms-authenticated-integration-test.sh
grep -q 'duplicate_update_status' tests/cms-authenticated-integration-test.sh

grep -q 'CMS ডেটা নষ্ট বা অবৈধ JSON' api/cms.php
grep -q 'json_last_error() !== JSON_ERROR_NONE' api/cms-public.php
grep -q 'corrupt_save_status' tests/cms-authenticated-integration-test.sh
grep -q 'corrupt_public_status' tests/cms-public-api-test.sh

grep -q 'session_write_close();\$action' api/cms.php
grep -q 'submit.disabled=true' admin/cms.php
grep -q 'নিরাপত্তার জন্য সম্পাদনা' admin/cms.php

grep -q 'Knowledge Base ডেটা পড়া যাচ্ছে না বা JSON অবৈধ' api/admin.php
grep -q 'Knowledge Base History নষ্ট বা পড়া যাচ্ছে না' api/admin.php
grep -q 'json_last_error() !== JSON_ERROR_NONE' api/admin.php

# Knowledge Base backup must be CLI-only, private, lock-consistent and integrity-verifiable.
grep -q "PHP_SAPI !== 'cli'" scripts/backup-knowledge-base.php
grep -q "flock(\$lock, LOCK_SH)" scripts/backup-knowledge-base.php
grep -q "hash_file('sha256'" scripts/backup-knowledge-base.php
grep -q "knowledge-history.json" scripts/backup-knowledge-base.php
grep -q "UTTC_BACKUP_RETENTION" scripts/backup-knowledge-base.php
grep -q "Refusing to back up invalid" scripts/backup-knowledge-base.php

# Read-only backup verification validates snapshot manifests, file hashes and JSON structure.
grep -q "PHP_SAPI !== 'cli'" scripts/verify-knowledge-base-backup.php
grep -q "hash_equals(\$entry\['sha256'\], \$hash)" scripts/verify-knowledge-base-backup.php
grep -q "Snapshot manifest is invalid" scripts/verify-knowledge-base-backup.php
grep -q "No live data was changed" scripts/verify-knowledge-base-backup.php
grep -q "Snapshot does not contain the required Knowledge Base file" scripts/verify-knowledge-base-backup.php
grep -q "A listed backup path contains a symbolic link" scripts/verify-knowledge-base-backup.php
grep -q "Knowledge Base source file is missing or unsafe" scripts/backup-knowledge-base.php

# Restore is an explicit CLI operation with pre-restore backup, verification, locking and rollback.
grep -q "PHP_SAPI !== 'cli'" scripts/restore-knowledge-base-backup.php
grep -q "verify-knowledge-base-backup.php" scripts/restore-knowledge-base-backup.php
grep -q "backup-knowledge-base.php" scripts/restore-knowledge-base-backup.php
grep -q "flock(\$lock, LOCK_EX)" scripts/restore-knowledge-base-backup.php
grep -q "Live data changed while preparing safety backup" scripts/restore-knowledge-base-backup.php
grep -q "rollback was attempted" scripts/restore-knowledge-base-backup.php
grep -q "Uploaded source documents were not changed" scripts/restore-knowledge-base-backup.php
grep -q "knowledge-base.json" scripts/restore-knowledge-base-backup.php
