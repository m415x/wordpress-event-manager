#!/usr/bin/env bash
set -euo pipefail

# Deterministic mocks: never start Docker or contact a real WordPress site.
root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
temp=$(mktemp -d) || exit 2
trap 'rm -rf "$temp"' EXIT
mkdir -p "$temp/bin"
export WEM_MOCK_LOG="$temp/commands.log"
: > "$WEM_MOCK_LOG"

cat > "$temp/bin/wp-env" <<'MOCK'
#!/usr/bin/env bash
printf 'wp-env %s\n' "$*" >> "$WEM_MOCK_LOG"
if [[ "$*" == *'wp plugin is-active'* && ${WEM_MOCK_ACTIVE:-0} != 1 ]]; then
  exit 1
fi
MOCK

cat > "$temp/bin/curl" <<'MOCK'
#!/usr/bin/env bash
printf 'curl %s\n' "$*" >> "$WEM_MOCK_LOG"
output=''
action=''
while (($#)); do
  case "$1" in
    --output) output=$2; shift 2 ;;
    --data-urlencode) action=$2; shift 2 ;;
    *) shift ;;
  esac
done
if [[ -z "$output" || "$action" != action=wem_* ]]; then exit 9; fi
printf '%s' '{"success":false,"data":{"code":"guest_access_unavailable"}}' > "$output"
printf '%s' "${WEM_MOCK_HTTP:-403}"
MOCK
chmod +x "$temp/bin/wp-env" "$temp/bin/curl"
export PATH="$temp/bin:$PATH"

export WEM_MOCK_ACTIVE=0
bash "$root/scripts/wp-local.sh" start > "$temp/start.log"
grep -q 'wp plugin activate wordpress-event-manager' "$WEM_MOCK_LOG"
grep -q 'LOCAL WORDPRESS READY' "$temp/start.log"
printf 'PASS: first start activates plugin\n'

: > "$WEM_MOCK_LOG"
export WEM_MOCK_ACTIVE=1
bash "$root/scripts/wp-local.sh" start > "$temp/start.log"
if grep -q 'wp plugin activate' "$WEM_MOCK_LOG"; then
  printf 'FAIL: repeated start reactivated the plugin\n' >&2
  exit 1
fi
grep -q 'Plugin already active' "$temp/start.log"
printf 'PASS: repeated start preserves active state\n'

: > "$WEM_MOCK_LOG"
bash "$root/scripts/wp-local.sh" status > "$temp/status.log"
grep -q 'wp plugin status wordpress-event-manager' "$WEM_MOCK_LOG"
bash "$root/scripts/wp-local.sh" stop > "$temp/stop.log"
grep -q 'wp-env stop --config=.wp-env.test.json' "$WEM_MOCK_LOG"
if grep -Eq 'reset|destroy|rm -rf|delete' "$WEM_MOCK_LOG"; then
  printf 'FAIL: destructive operation observed\n' >&2
  exit 1
fi
printf 'PASS: status and stop avoid destructive commands\n'

: > "$WEM_MOCK_LOG"
bash "$root/scripts/check-guest-http.sh" > "$temp/http.log"
grep -q 'ANONYMOUS HTTP GUARDS: GREEN' "$temp/http.log"
grep -q 'action=wem_checkin_ajax' "$WEM_MOCK_LOG"
grep -q 'action=wem_list_ajax' "$WEM_MOCK_LOG"
printf 'PASS: anonymous HTTP guard checks both actions\n'

export WEM_MOCK_HTTP=200
if bash "$root/scripts/check-guest-http.sh" > "$temp/http.log" 2>&1; then
  printf 'FAIL: HTTP 200 incorrectly accepted\n' >&2
  exit 1
fi
printf 'PASS: anonymous HTTP guard rejects HTTP 200\n'
printf 'LOCAL WORKFLOW SCRIPTS: GREEN (mocked only)\n'
