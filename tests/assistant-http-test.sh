#!/usr/bin/env bash
set -euo pipefail

port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); if (!$s) exit(1); echo substr(strrchr(stream_socket_get_name($s, false), ":"), 1); fclose($s);')"
log_file="$(mktemp)"
php -S "127.0.0.1:${port}" -t . >"$log_file" 2>&1 &
server_pid=$!
cleanup() {
  kill "$server_pid" 2>/dev/null || true
  wait "$server_pid" 2>/dev/null || true
  rm -f "$log_file"
}
trap cleanup EXIT

ready=0
for _ in $(seq 1 30); do
  if curl --silent --output /dev/null "http://127.0.0.1:${port}/api/assistant.php"; then
    ready=1
    break
  fi
  sleep 0.2
done
if [ "$ready" -ne 1 ]; then
  cat "$log_file"
  echo "FAIL: PHP test server did not start."
  exit 1
fi

allowed="$(curl --silent --show-error --include --request OPTIONS \
  --header 'Origin: https://uttc.edu.bd' \
  --header 'Access-Control-Request-Method: POST' \
  "http://127.0.0.1:${port}/api/assistant.php")"
grep -q ' 204 ' <<<"$allowed"
grep -qi 'Access-Control-Allow-Origin: https://uttc.edu.bd' <<<"$allowed"
grep -qi 'Access-Control-Allow-Methods: POST, OPTIONS' <<<"$allowed"

for local_origin in "http://localhost:3000" "http://127.0.0.1:3000"; do
  local_response="$(curl --silent --show-error --include --request OPTIONS \
    --header "Origin: ${local_origin}" \
    --header 'Access-Control-Request-Method: POST' \
    "http://127.0.0.1:${port}/api/assistant.php")"
  grep -q ' 204 ' <<<"$local_response"
  grep -qi "Access-Control-Allow-Origin: ${local_origin}" <<<"$local_response"
done

denied="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --request OPTIONS \
  --header 'Origin: https://attacker.example' \
  "http://127.0.0.1:${port}/api/assistant.php")"
[ "$denied" = "403" ]

wrong_method="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' \
  "http://127.0.0.1:${port}/api/assistant.php")"
[ "$wrong_method" = "405" ]

echo "OK: CORS preflight, origin rejection, and HTTP method checks passed."
