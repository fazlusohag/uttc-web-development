#!/usr/bin/env bash
set -euo pipefail
data_dir="api/data"
fixture="$data_dir/cms-content.json"
mkdir -p "$data_dir"
backup=""
if [ -f "$fixture" ]; then backup="$(mktemp)"; cp "$fixture" "$backup"; fi
cleanup_fixture() { if [ -n "$backup" ]; then cp "$backup" "$fixture"; rm -f "$backup"; else rm -f "$fixture"; fi; }
trap cleanup_fixture EXIT
cat > "$fixture" <<'JSON'
{"updated_at":"2026-11-19T00:00:00Z","items":[
{"id":"pub-course","type":"course","title":"Published course","body":"Visible","page_key":"computer-office-application-moulvibazar","status":"published"},
{"id":"draft-course","type":"course","title":"Draft course","body":"Hidden","status":"draft"},
{"id":"pub-notice","type":"notice","title":"Published notice","body":"Notice body","status":"published"},
{"id":"pub-home-page","type":"page","title":"Home SEO content","body":"Published home copy","slug":"index","meta_title":"UTTC Home SEO","meta_description":"Published home description","canonical_url":"https://uttc.example/","published_at":"2026-11-19T00:00:00Z","status":"published"},
{"id":"draft-page","type":"page","title":"Draft SEO content","body":"Must stay hidden","slug":"about-uttc-moulvibazar","meta_title":"Draft title","status":"draft"},
{"id":"pub-contact","type":"contact","title":"Contact desk","body":"Contact details","status":"published"},
{"id":"pub-menu","type":"menu","title":"Admissions","body":"Menu item","url":"https://uttc.example/admission","status":"published"},
{"id":"private-extra","type":"internal","title":"Internal record","body":"Must not be public","status":"published"},
{"id":["bad","id"],"type":"notice","title":{"unsafe":"shape"},"body":false,"url":null,"image_url":17,"published_at":["not","a","date"],"status":"published"}
]}
JSON
port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); if (!$s) exit(1); echo substr(strrchr(stream_socket_get_name($s, false), ":"), 1); fclose($s);')"
log_file="$(mktemp)"
php -S "127.0.0.1:${port}" -t . >"$log_file" 2>&1 &
server_pid=$!
cleanup_server() { kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true; rm -f "$log_file"; }
trap 'cleanup_server; cleanup_fixture' EXIT
ready=0
for _ in $(seq 1 30); do
  if curl --silent --output /dev/null "http://127.0.0.1:${port}/api/cms-public.php"; then ready=1; break; fi
  sleep 0.2
done
if [ "$ready" -ne 1 ]; then cat "$log_file"; echo "FAIL: PHP test server did not start."; exit 1; fi
all="$(curl --silent --show-error "http://127.0.0.1:${port}/api/cms-public.php")"
grep -q '"pub-course"' <<<"$all"
grep -q '"pub-notice"' <<<"$all"
! grep -q '"draft-course"' <<<"$all"
! grep -q '"private-extra"' <<<"$all"
grep -q '"pub-home-page"' <<<"$all"
grep -q '"meta_title":"UTTC Home SEO"' <<<"$all"
grep -q '"meta_description":"Published home description"' <<<"$all"
grep -q '"published_at":"2026-11-19T00:00:00Z"' <<<"$all"
! grep -q '"draft-page"' <<<"$all"
! grep -q '"Draft SEO content"' <<<"$all"
grep -q '"pub-contact"' <<<"$all"
grep -q '"pub-menu"' <<<"$all"
grep -q '"id":""' <<<"$all"
grep -q '"title":""' <<<"$all"
grep -q '"body":""' <<<"$all"
grep -q '"image_url":"17"' <<<"$all"
grep -q '"published_at":null' <<<"$all"
courses="$(curl --silent --show-error "http://127.0.0.1:${port}/api/cms-public.php?type=course")"
grep -q '"pub-course"' <<<"$courses"
grep -q '"page_key":"computer-office-application-moulvibazar"' <<<"$courses"
! grep -q '"pub-notice"' <<<"$courses"
! grep -q '"draft-course"' <<<"$courses"
invalid="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' "http://127.0.0.1:${port}/api/cms-public.php?type=unknown")"
[ "$invalid" = "400" ]
pages="$(curl --silent --show-error "http://127.0.0.1:${port}/api/cms-public.php?type=page")"
grep -q '"slug":"index"' <<<"$pages"
grep -q '"canonical_url":"https://uttc.example/"' <<<"$pages"
! grep -q '"draft-page"' <<<"$pages"
! grep -q '"pub-contact"' <<<"$pages"
menus="$(curl --silent --show-error "http://127.0.0.1:${port}/api/cms-public.php?type=menu")"
grep -q '"pub-menu"' <<<"$menus"
! grep -q '"pub-contact"' <<<"$menus"
! grep -q '"pub-home-page"' <<<"$menus"
# Corrupt public storage must return an explicit server error, not a misleading empty feed.
printf '{invalid-json' > "$fixture"
corrupt_public_status="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' "http://127.0.0.1:${port}/api/cms-public.php")"
[ "$corrupt_public_status" = "500" ]
echo "OK: CMS public API exposes Published records only, filters by type, preserves Page SEO fields, and rejects unknown types."
