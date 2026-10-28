#!/usr/bin/env bash
set -euo pipefail
base="${1:-http://localhost:8892}"
base="${base%/}"
cli="${WEM_TEST_CLI_CONTAINER:-wp-env-wordpress-event-manager-phpunit-5142e11c-cli-1}"
check="/var/www/html/wp-content/plugins/wordpress-event-manager/tests/Integration/helpers/http-auth-cleanup-check.php"
MSYS_NO_PATHCONV=1 MSYS2_ARG_CONV_EXCL='*' docker exec "$cli" wp eval-file "$check" --allow-root
tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT
for action in wem_checkin_ajax wem_list_ajax; do
  status="$(curl --silent --show-error --max-time 15 --output "$tmp" --write-out '%{http_code}' --request POST "$base/wp-admin/admin-ajax.php" --data-urlencode "action=$action")"
  if [[ "$status" != 403 ]] || [[ "$(cat "$tmp")" != '{"success":false,"data":{"code":"guest_access_unavailable"}}' ]]; then
    echo "FAIL staff guard $action HTTP $status" >&2
    exit 1
  fi
  echo "PASS staff $action HTTP 403"
done
echo "PASS WEM-93 cleanup and staff regressions"
