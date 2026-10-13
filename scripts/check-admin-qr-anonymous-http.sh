#!/usr/bin/env bash
set -euo pipefail

# WEM-65: real HTTP anonymous smoke test against isolated PHPUnit WordPress.
# Core WordPress returns 400/0 for an unregistered nopriv AJAX action.
# This is distinct from authenticated controller's explicit 403 JSON denial.
base_url='http://localhost:8892'
for operation in issue rotate reissue; do
  action="wem_invitation_qr_${operation}"
  for method in POST GET; do
    headers=$(mktemp)
    body=$(mktemp)
    trap 'rm -f "$headers" "$body"' EXIT
    if [[ "$method" == POST ]]; then
      status=$(curl -sS -D "$headers" -o "$body" -w '%{http_code}' \
        -X POST "$base_url/wp-admin/admin-ajax.php" --data-urlencode "action=$action")
    else
      status=$(curl -sS -D "$headers" -o "$body" -w '%{http_code}' \
        "$base_url/wp-admin/admin-ajax.php?action=$action")
    fi

    actual=$(cat "$body")
    if [[ "$status" != 400 || "$actual" != '0' ]]; then
      printf 'FAIL: anonymous %s %s: HTTP %s, body %q\n' "$method" "$action" "$status" "$actual" >&2
      exit 1
    fi
    if ! grep -qiE '^Cache-Control:.*(no-cache|no-store)' "$headers"; then
      printf 'FAIL: anonymous %s %s: missing no-cache header\n' "$method" "$action" >&2
      exit 1
    fi
    printf 'PASS: anonymous %s %s — core HTTP 400/0 and no-cache\n' "$method" "$action"
    rm -f "$headers" "$body"
    trap - EXIT
  done
done
printf 'ADMIN QR ANONYMOUS HTTP: GREEN\n'
