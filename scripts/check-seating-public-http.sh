#!/usr/bin/env bash
set -euo pipefail

base="${1:-http://localhost:8892}"
base="${base%/}"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
for entry in "GET availability" "GET members" "POST reservations"; do
  read -r method endpoint <<<"$entry"
  url="$base/wp-json/wem/v1/seating/$endpoint/"
  code="$(curl --silent --show-error --max-time 15 --dump-header "$tmp/headers" --output "$tmp/body" --write-out '%{http_code}' --request "$method" --header 'Accept: application/json' "$url")"
  if [[ "$code" != 403 ]]; then
    echo "FAIL $method $endpoint HTTP $code (expected 403)"
    grep -iE "^(content-type|location|x-redirect-by):" "$tmp/headers" || true
    head -c 500 "$tmp/body" || true
    echo
    exit 1
  fi
  if ! grep -qi "^content-type: .*application/json" "$tmp/headers"; then echo "FAIL $endpoint non-JSON response"; exit 1; fi
  if ! grep -qi '^cache-control: .*no-store' "$tmp/headers"; then echo "FAIL $endpoint cache"; exit 1; fi
  if ! grep -qi '^referrer-policy: no-referrer' "$tmp/headers"; then echo "FAIL $endpoint referrer"; exit 1; fi
  if ! grep -qi '^x-robots-tag: .*noindex' "$tmp/headers"; then echo "FAIL $endpoint robots"; exit 1; fi
  if ! grep -q '"code":"seating_access_unavailable"' "$tmp/body"; then echo "FAIL $endpoint body"; exit 1; fi
  echo "PASS $method $endpoint HTTP 403 secure headers"
done
