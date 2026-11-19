#!/usr/bin/env bash
set -euo pipefail

data_dir="api/data"
mkdir -p "$data_dir"
fixture="$data_dir/cms-content.json"
login_attempts="$data_dir/login-attempts.json"
backup=""
attempt_backup=""
audit="$data_dir/cms-audit.jsonl"
audit_backup=""
if [ -f "$fixture" ]; then backup="$(mktemp)"; cp "$fixture" "$backup"; fi
if [ -f "$login_attempts" ]; then attempt_backup="$(mktemp)"; cp "$login_attempts" "$attempt_backup"; fi
if [ -f "$audit" ]; then audit_backup="$(mktemp)"; cp "$audit" "$audit_backup"; fi
cleanup_files() {
  if [ -n "$backup" ]; then cp "$backup" "$fixture"; rm -f "$backup"; else rm -f "$fixture"; fi
  if [ -n "$attempt_backup" ]; then cp "$attempt_backup" "$login_attempts"; rm -f "$attempt_backup"; else rm -f "$login_attempts"; fi
  if [ -n "$audit_backup" ]; then cp "$audit_backup" "$audit"; rm -f "$audit_backup"; else rm -f "$audit"; fi
  rm -f "$data_dir/cms.lock"
}
trap cleanup_files EXIT

port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); if (!$s) exit(1); echo substr(strrchr(stream_socket_get_name($s, false), ":"), 1); fclose($s);')"
tmp="$(mktemp -d)"
log_file="$tmp/server.log"
cookie_jar="$tmp/cookies.txt"
password="UTTC-CI-Test-Only-Password-2026"
hash="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$password")"
UTTC_ADMIN_PASSWORD_HASH="$hash" php -S "127.0.0.1:${port}" -t . >"$log_file" 2>&1 &
server_pid=$!
cleanup_server() { kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true; rm -rf "$tmp"; }
trap 'cleanup_server; cleanup_files' EXIT

ready=0
for _ in $(seq 1 30); do
  if curl --silent --output /dev/null "http://127.0.0.1:${port}/api/admin.php?action=token"; then ready=1; break; fi
  sleep 0.2
done
if [ "$ready" -ne 1 ]; then cat "$log_file"; echo "FAIL: PHP test server did not start."; exit 1; fi

unauth="$(curl --silent --show-error --cookie-jar "$cookie_jar" --cookie "$cookie_jar" \
  --header 'Content-Type: application/json' --data '{"action":"list","csrf":"missing"}' \
  --write-out '\n%{http_code}' "http://127.0.0.1:${port}/api/cms.php")"
[ "${unauth##*$'\n'}" = "419" ]

token_json="$(curl --silent --show-error --cookie-jar "$cookie_jar" --cookie "$cookie_jar" "http://127.0.0.1:${port}/api/admin.php?action=token")"
csrf="$(php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["csrf"]??"";' <<<"$token_json")"
[ -n "$csrf" ]
cms_token_json="$(curl --silent --show-error --cookie-jar "$cookie_jar" --cookie "$cookie_jar" "http://127.0.0.1:${port}/api/cms.php?action=token")"
cms_csrf="$(php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["csrf"]??"";' <<<"$cms_token_json")"
[ -n "$cms_csrf" ]

unauth_with_token="$(curl --silent --show-error --cookie "$cookie_jar" --header 'Content-Type: application/json' --data "$(php -r 'echo json_encode(["action"=>"list","csrf"=>$argv[1]]);' "$cms_csrf")" --write-out '\n%{http_code}' "http://127.0.0.1:${port}/api/cms.php")"
[ "${unauth_with_token##*$'\n'}" = "401" ]

oversized_body_file="$(mktemp)"
head -c 65537 /dev/zero | tr '\0' 'x' >"$oversized_body_file"
oversized_cms_status="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' \
  --header 'Content-Type: application/json' --data-binary "@${oversized_body_file}" \
  "http://127.0.0.1:${port}/api/cms.php")"
rm -f "$oversized_body_file"
[ "$oversized_cms_status" = "413" ]

login="$(curl --silent --show-error --cookie-jar "$cookie_jar" --cookie "$cookie_jar" \
  --header 'Content-Type: application/json' --data "$(php -r 'echo json_encode(["action"=>"login","csrf"=>$argv[1],"password"=>$argv[2]]);' "$csrf" "$password")" \
  "http://127.0.0.1:${port}/api/admin.php")"
grep -q '"ok":true' <<<"$login"



list_payload="$(php -r 'echo json_encode(["action"=>"list","csrf"=>$argv[1]]);' "$cms_csrf")"
list_result="$(curl --silent --show-error --cookie "$cookie_jar" --header 'Content-Type: application/json' --data "$list_payload" "http://127.0.0.1:${port}/api/cms.php")"
grep -q '"ok":true' <<<"$list_result"

invalid_course_payload="$(php -r 'echo json_encode(["action"=>"save","csrf"=>$argv[1],"type"=>"course","title"=>"CI Missing Page Assignment","body"=>"Must be rejected"]);' "$cms_csrf")"
invalid_course_result="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --cookie "$cookie_jar" --header 'Content-Type: application/json' --data "$invalid_course_payload" "http://127.0.0.1:${port}/api/cms.php")"
[ "$invalid_course_result" = "422" ]
invalid_page_payload="$(php -r 'echo json_encode(["action"=>"save","csrf"=>$argv[1],"type"=>"course","title"=>"CI Invalid Page Assignment","body"=>"Must be rejected","page_key"=>"not-a-course-page"]);' "$cms_csrf")"
invalid_page_result="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --cookie "$cookie_jar" --header 'Content-Type: application/json' --data "$invalid_page_payload" "http://127.0.0.1:${port}/api/cms.php")"
[ "$invalid_page_result" = "422" ]

invalid_cms_page_payload="$(php -r 'echo json_encode(["action"=>"save","csrf"=>$argv[1],"type"=>"page","title"=>"CI Invalid Page Slug","body"=>"Must be rejected","slug"=>"not-a-real-public-page"]);' "$cms_csrf")"
invalid_cms_page_result="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --cookie "$cookie_jar" --header 'Content-Type: application/json' --data "$invalid_cms_page_payload" "http://127.0.0.1:${port}/api/cms.php")"
[ "$invalid_cms_page_result" = "422" ]

valid_cms_page_payload="$(php -r 'echo json_encode(["action"=>"save","csrf"=>$argv[1],"type"=>"page","title"=>"CI Valid Home SEO Page","body"=>"Temporary SEO page record","slug"=>"index"]);' "$cms_csrf")"
valid_cms_page_result="$(curl --silent --show-error --cookie "$cookie_jar" --header 'Content-Type: application/json' --data "$valid_cms_page_payload" "http://127.0.0.1:${port}/api/cms.php")"
grep -q '"ok":true' <<<"$valid_cms_page_result"
duplicate_cms_page_payload="$(php -r 'echo json_encode(["action"=>"save","csrf"=>$argv[1],"type"=>"page","title"=>"CI Duplicate Home SEO Page","body"=>"Must be rejected","slug"=>"index"]);' "$cms_csrf")"
duplicate_cms_page_result="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --cookie "$cookie_jar" --header 'Content-Type: application/json' --data "$duplicate_cms_page_payload" "http://127.0.0.1:${port}/api/cms.php")"
[ "$duplicate_cms_page_result" = "409" ]

save_payload="$(php -r 'echo json_encode(["action"=>"save","csrf"=>$argv[1],"type"=>"course","title"=>"CI CMS Contract Record","body"=>"Temporary integration test record","page_key"=>"computer-office-application-moulvibazar"]);' "$cms_csrf")"
save_result="$(curl --silent --show-error --cookie "$cookie_jar" --header 'Content-Type: application/json' --data "$save_payload" "http://127.0.0.1:${port}/api/cms.php")"
grep -q '"ok":true' <<<"$save_result"

public_before="$(curl --silent --show-error "http://127.0.0.1:${port}/api/cms-public.php?type=course")"
! grep -q 'CI CMS Contract Record' <<<"$public_before"

id="$(php -r '$d=json_decode(stream_get_contents(STDIN),true); foreach(($d["items"]??[]) as $i) if(($i["title"]??"")==="CI CMS Contract Record") echo $i["id"];' <<<"$(cat "$fixture")")"
[ -n "$id" ]
publish_payload="$(php -r 'echo json_encode(["action"=>"publish","csrf"=>$argv[1],"id"=>$argv[2]]);' "$cms_csrf" "$id")"
publish_result="$(curl --silent --show-error --cookie "$cookie_jar" --header 'Content-Type: application/json' --data "$publish_payload" "http://127.0.0.1:${port}/api/cms.php")"
grep -q '"ok":true' <<<"$publish_result"
public_after="$(curl --silent --show-error "http://127.0.0.1:${port}/api/cms-public.php?type=course")"
grep -q 'CI CMS Contract Record' <<<"$public_after"
grep -q '"action":"create"' "$audit"
grep -q '"action":"publish"' "$audit"

delete_payload="$(php -r 'echo json_encode(["action"=>"delete","csrf"=>$argv[1],"id"=>$argv[2]]);' "$cms_csrf" "$id")"
delete_result="$(curl --silent --show-error --cookie "$cookie_jar" --header 'Content-Type: application/json' --data "$delete_payload" "http://127.0.0.1:${port}/api/cms.php")"
grep -q '"ok":true' <<<"$delete_result"
public_after_delete="$(curl --silent --show-error "http://127.0.0.1:${port}/api/cms-public.php?type=course")"
! grep -q 'CI CMS Contract Record' <<<"$public_after_delete"
grep -q '"action":"delete"' "$audit"

for attempt in 1 2 3 4 5; do
  wrong_login="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --cookie "$cookie_jar" \
    --header 'Content-Type: application/json' --data "$(php -r 'echo json_encode(["action"=>"login","csrf"=>$argv[1],"password"=>"wrong-password"]);' "$csrf")" \
    "http://127.0.0.1:${port}/api/admin.php")"
  [ "$wrong_login" = "401" ]
done
throttled_login="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --cookie "$cookie_jar" \
  --header 'Content-Type: application/json' --data "$(php -r 'echo json_encode(["action"=>"login","csrf"=>$argv[1],"password"=>"wrong-password"]);' "$csrf")" \
  "http://127.0.0.1:${port}/api/admin.php")"
[ "$throttled_login" = "429" ]

echo "OK: CMS session, CSRF, body limit, course/page validation, duplicate page protection, Draft/Publish/Delete, audit and login throttling passed."
