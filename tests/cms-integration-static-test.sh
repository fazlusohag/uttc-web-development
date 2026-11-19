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
done
grep -q 'data-cms-feed="notice"' notices-uttc-moulvibazar.html
grep -q 'data-cms-feed="gallery"' gallery-uttc-moulvibazar.html
for page in driving-training-moulvibazar.html women-driving-training-moulvibazar.html computer-office-application-moulvibazar.html freelancing-course-moulvibazar.html graphic-design-course-moulvibazar.html; do
  grep -q 'data-cms-feed="course"' "$page" || { echo "Missing course feed: $page"; exit 1; }
done
grep -q 'Require all denied' api/data/.htaccess
grep -q 'status.*published' api/cms-public.php
grep -q "header \.nav-links,header nav ul,header nav" assets/js/cms-public.js
grep -q "nav.matches('ul,ol')" assets/js/cms-public.js
grep -q "li.append(a)" assets/js/cms-public.js
grep -q "textContent=text" assets/js/cms-public.js
grep -q "!u.username&&!u.password" assets/js/cms-public.js
grep -q "og:title" assets/js/cms-public.js
grep -q "og:description" assets/js/cms-public.js
grep -q "setMeta('meta\[name=" assets/js/cms-public.js

echo "CMS static integration checks passed."
