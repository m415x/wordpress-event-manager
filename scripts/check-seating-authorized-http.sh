#!/usr/bin/env bash
set -euo pipefail
base="${1:-http://localhost:8892}"
base="${base%/}"
cli="${WEM_TEST_CLI_CONTAINER:-wp-env-wordpress-event-manager-phpunit-5142e11c-cli-1}"
fixture="/var/www/html/wp-content/plugins/wordpress-event-manager/tests/Integration/helpers/http-auth-fixture.php"
tmp="$(mktemp -d)"
guest='' event='' table='' admin='' token=''
cleanup() {
  if [[ -n "$guest" && -n "$event" && -n "$table" && -n "$admin" ]]; then
    docker exec "$cli" wp eval-file "$fixture" cleanup "$guest" "$event" "$table" "$admin" --allow-root >/dev/null || echo 'FAIL: fixture cleanup; inspect isolated database' >&2
  fi
  rm -rf "$tmp"
}
trap cleanup EXIT
if ! output="$(docker exec "$cli" wp eval-file "$fixture" create --allow-root)"; then
  echo 'FAIL: fixture creation' >&2; exit 1
fi
IFS='|' read -r token guest event table admin <<<"$output"
if [[ -z "$token" || ! "$guest" =~ ^[0-9]+$ || ! "$table" =~ ^[0-9]+$ ]]; then
  echo 'FAIL: malformed fixture output; inspect isolated database' >&2; exit 1
fi
probe() {
  local method="$1" endpoint="$2" expected="$3"; shift 3
  local status
  status="$(curl --silent --show-error --max-time 15 --dump-header "$tmp/headers" --output "$tmp/body" --write-out '%{http_code}' --request "$method" --header "X-WEM-Invitation: $token" --header 'Accept: application/json' "$@" "$base/?rest_route=/wem/v1/seating/$endpoint")"
  if [[ "$status" != "$expected" ]]; then
    echo "FAIL $method $endpoint HTTP $status (expected $expected)" >&2; exit 1
  fi
  if ! grep -qi '^content-type: .*application/json' "$tmp/headers"; then echo "FAIL $endpoint Content-Type" >&2; exit 1; fi
  if ! grep -qi '^cache-control: .*no-store' "$tmp/headers"; then echo "FAIL $endpoint cache" >&2; exit 1; fi
  if ! grep -qi '^referrer-policy: no-referrer' "$tmp/headers"; then echo "FAIL $endpoint referrer" >&2; exit 1; fi
  if ! grep -qi '^x-robots-tag: .*noindex' "$tmp/headers"; then echo "FAIL $endpoint robots" >&2; exit 1; fi
  if grep -Fq "$token" "$tmp/body"; then echo "FAIL $endpoint token disclosure" >&2; exit 1; fi
  if grep -Fq 'PRIVATE-T6-' "$tmp/body"; then echo "FAIL $endpoint PII disclosure" >&2; exit 1; fi
  echo "PASS $method $endpoint HTTP $status secure/no-secret"
}
probe GET availability 200
probe GET members 200
probe POST reservations 201 --data-urlencode "table_id=$table"
probe POST reservations 409 --data-urlencode "table_id=$table"
probe GET members 200
if ! grep -Fq 'Integrante privado' "$tmp/body"; then echo 'FAIL private member projection' >&2; exit 1; fi
echo 'PASS authenticated HTTP acceptance 5/5'
