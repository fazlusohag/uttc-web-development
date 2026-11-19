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
{"id":"pub-course","type":"course","title":"Published course","body":"Visible","status":"published"},
{"id":"draft-course","type":"course","title":"Draft course","body":"Hidden","status":"draft"},
{"id":"pub-notice","type":"notice","title":"Published notice","body":"Notice body","status":"published"},
{"id":"private-extra","type":"internal","title":"Internal record","body":"Must not be public","status":"published"}
]}
JSON
port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); if (!$s) exit(1); echo substr(strrchr(stream_socket_name($s), ":"), 1); fclose($s);')"
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
courses="$(curl --silent --show-error "http://127.0.0.1:${port}/api/cms-public.php?type=course")"
grep -q '"pub-course"' <<<"$courses"
! grep -q '"pub-notice"' <<<"$courses"
! grep -q '"draft-course"' <<<"$courses"
invalid="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' "http://127.0.0.1:${port}/api/cms-public.php?type=unknown")"
[ "$invalid" = "400" ]
echo "OK: CMS public API exposes Published records only, filters by type, and rejects unknown types."
