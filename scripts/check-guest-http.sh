#!/usr/bin/env bash
set -euo pipefail

# Verify the two anonymous AJAX routes over actual HTTP, not PHPUnit CLI.
# Fixed localhost target prevents accidentally testing external environments.
base_url='http://localhost:8890'
expected_body='{"success":false,"data":{"code":"guest_access_unavailable"}}'

for action in wem_checkin_ajax wem_list_ajax; do
  response_file=$(mktemp) || exit 2
  trap 'rm -f "$response_file"' EXIT
  if ! status=$(curl --silent --show-error --output "$response_file" --write-out '%{http_code}' \
    --request POST "$base_url/wp-admin/admin-ajax.php" \
    --data-urlencode "action=$action"); then
    printf 'ERROR: HTTP request failed for %s\n' "$action" >&2
    exit 1
  fi

  body=$(cat "$response_file")
  if [[ "$status" != 403 || "$body" != "$expected_body" ]]; then
    printf 'FAIL: %s (HTTP %s; body: %s)\n' "$action" "$status" "$body" >&2
    exit 1
  fi

  printf 'PASS: %s — HTTP 403 and fixed deny-all JSON\n' "$action"
  rm -f "$response_file"
  trap - EXIT
done

printf 'ANONYMOUS HTTP GUARDS: GREEN\n'
