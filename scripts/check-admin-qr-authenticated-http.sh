#!/usr/bin/env bash
set -euo pipefail

# WEM-65: real authenticated HTTP denial against isolated PHPUnit WordPress.
# GET and invalid nonce POST never reach credential mutations.
: "${WEM_QR_HTTP_USER:?Provide a disposable 8892 administrator login}"
: "${WEM_QR_HTTP_PASSWORD:?Provide that administrator password}"
base_url='http://localhost:8892'
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT

curl -fsS -c "$tmp/cookies" -b "$tmp/cookies" -o /dev/null "$base_url/wp-login.php"

curl -fsS -c "$tmp/cookies" -b "$tmp/cookies" -o /dev/null \
  --data-urlencode "log=$WEM_QR_HTTP_USER" \
  --data-urlencode "pwd=$WEM_QR_HTTP_PASSWORD" \
  --data-urlencode 'wp-submit=Log In' \
  --data-urlencode "redirect_to=$base_url/wp-admin/" \
  --data-urlencode 'testcookie=1' \
  "$base_url/wp-login.php"

# WordPress login may return HTTP 200 with an error page; verify the cookie
# against a protected endpoint before testing WEM request semantics.
if ! grep -Eq '(^#HttpOnly_|^[^#[:space:]]).*wordpress_logged_in_' "$tmp/cookies"; then
  printf 'ERROR: WordPress did not issue a logged-in cookie; check test credentials.\n' >&2
  exit 1
fi
auth_status=$(curl -sS -b "$tmp/cookies" -o /dev/null -w '%{http_code}' "$base_url/wp-admin/profile.php")
if [[ "$auth_status" != 200 ]]; then
  printf 'ERROR: authenticated WordPress profile check returned HTTP %s; check login/session.\n' "$auth_status" >&2
  exit 1
fi
printf 'PASS: WordPress authentication preflight\n'

for operation in issue rotate reissue; do
  for method in GET POST; do
    action="wem_invitation_qr_${operation}"
    if [[ $method == GET ]]; then
      status=$(curl -sS -b "$tmp/cookies" -D "$tmp/headers" -o "$tmp/body" -w '%{http_code}' \
        "$base_url/wp-admin/admin-ajax.php?action=$action")
    else
      status=$(curl -sS -b "$tmp/cookies" -D "$tmp/headers" -o "$tmp/body" -w '%{http_code}' \
        --data-urlencode "action=$action" \
        --data-urlencode 'nonce=definitely-invalid' \
        --data-urlencode 'guest_id=1' \
        "$base_url/wp-admin/admin-ajax.php")
    fi
    if [[ "$status" != 403 || "$(cat "$tmp/body")" != '{"success":false,"data":{"code":"invitation_qr_unavailable"}}' ]]; then
      printf 'FAIL: authenticated %s %s HTTP %s; check login and plugin\n' "$method" "$action" "$status" >&2
      exit 1
    fi
    if ! grep -qiE '^Cache-Control:.*no-store' "$tmp/headers" ||
       ! grep -qiE '^Referrer-Policy: *no-referrer' "$tmp/headers" ||
       ! grep -qiE '^X-Robots-Tag: *noindex' "$tmp/headers"; then
      printf 'FAIL: %s %s missing protective headers\n' "$method" "$action" >&2
      exit 1
    fi
    printf 'PASS: authenticated %s %s — fixed 403 and protective headers\n' "$method" "$action"
  done
done
printf 'ADMIN QR AUTHENTICATED HTTP DENY: GREEN\n'
