#!/usr/bin/env bash
set -euo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
temp=$(mktemp -d)
trap 'rm -rf "$temp"' EXIT

cat > "$temp/wp-env-mock" <<'EOF'
#!/usr/bin/env bash
if [[ " $* " == *" sh -lc "* ]]; then
  if [[ ${TDD_MOCK_SCENARIO:-} == syntax_failure ]]; then
    echo 'PHP Parse error: syntax error in fixture.php'
    exit 1
  fi
  exit 0
fi
case "${TDD_MOCK_SCENARIO:-}" in
  passing|syntax_failure)
    echo 'PHPUnit 9.6.37'
    echo 'OK (1 test, 2 assertions)'
    exit 0
    ;;
  assertion_failure)
    echo 'PHPUnit 9.6.37'
    echo 'FAILURES!'
    echo 'Tests: 1, Assertions: 1, Failures: 1.'
    exit 1
    ;;
  bootstrap_failure)
    echo 'Error: WordPress bootstrap missing'
    echo 'ERRORS!'
    echo 'Tests: 1, Assertions: 0, Errors: 1.'
    exit 2
    ;;
  *)
    echo 'Unknown fixture'
    exit 2
    ;;
esac
EOF
chmod +x "$temp/wp-env-mock"

case_test() {
  local expected_exit=$1 expected_output=$2 scenario=$3 mode=$4
  shift 4
  local result=0 actual
  WEM_TDD_SKIP_SYNC=1 WEM_WP_ENV_BIN="$temp/wp-env-mock" TDD_MOCK_SCENARIO="$scenario" \
    bash "$root/scripts/tdd.sh" "$mode" "$@" >"$temp/result.log" 2>&1 || result=$?
  actual=$(cat "$temp/result.log")
  if [[ $result -ne $expected_exit ]] || [[ $actual != "$expected_output" ]]; then
    printf 'FAIL: %s %s (expected exit %d and output <%s>; got exit %d and <%s>)\n' \
      "$scenario" "$mode" "$expected_exit" "$expected_output" "$result" "$actual" >&2
    exit 1
  fi
  printf 'PASS: %s %s\n' "$scenario" "$mode"
}

case_verbose() {
  local scenario=$1 mode=$2 needle=$3
  shift 3
  local result=0
  WEM_TDD_SKIP_SYNC=1 WEM_WP_ENV_BIN="$temp/wp-env-mock" TDD_MOCK_SCENARIO="$scenario" \
    bash "$root/scripts/tdd.sh" "$mode" "$@" >"$temp/result.log" 2>&1 || result=$?
  if ! grep -Fq "$needle" "$temp/result.log"; then
    printf 'FAIL: %s %s verbose did not reveal <%s>\n' "$scenario" "$mode" "$needle" >&2
    cat "$temp/result.log" >&2
    exit 1
  fi
  printf 'PASS: %s %s verbose\n' "$scenario" "$mode"
}

case_test 0 RED assertion_failure red unit --filter ContractNotImplemented
case_test 1 ERROR passing red unit
case_test 1 ERROR bootstrap_failure red integration
case_test 0 GREEN passing green unit
case_test 0 GREEN passing green tests/Integration/WordPressPluginMetadataTest.php
case_test 1 ERROR assertion_failure green unit
case_test 1 ERROR syntax_failure green unit
case_verbose assertion_failure red 'Failures: 1' unit -v
case_verbose bootstrap_failure red 'WordPress bootstrap missing' integration --verbose
case_verbose passing green 'OK (1 test, 2 assertions)' tests/Unit/PluginEntrypointTest.php --verbose
case_verbose bootstrap_failure diagnose 'WordPress bootstrap missing' integration
# Sync behavior uses disposable git mock: no network or real pull.
cat > "$temp/git-mock" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$TDD_GIT_CALLS"
if [[ ${TDD_GIT_FAIL:-0} == 1 ]]; then
  echo 'fatal: Not possible to fast-forward, aborting.'
  exit 128
fi
exit 0
EOF
chmod +x "$temp/git-mock"

sync_case() {
  local label=$1 should_call=$2 expected_exit=$3 expected_output=$4
  shift 4
  local result=0 actual calls
  : > "$temp/git-calls"
  TDD_GIT_CALLS="$temp/git-calls" WEM_TDD_GIT_BIN="$temp/git-mock" \
    WEM_WP_ENV_BIN="$temp/wp-env-mock" TDD_MOCK_SCENARIO=passing \
    "$@" > "$temp/result.log" 2>&1 || result=$?
  actual=$(cat "$temp/result.log")
  calls=$(cat "$temp/git-calls")
  if [[ $result -ne $expected_exit || $actual != "$expected_output" ]]; then
    printf 'FAIL: %s unexpected output/exit (exit %d, output <%s>)\n' "$label" "$result" "$actual" >&2
    exit 1
  fi
  if [[ $should_call == yes && $calls != 'pull --ff-only --quiet' ]] ||
     [[ $should_call == no && -n $calls ]]; then
    printf 'FAIL: %s git pull call mismatch <%s>\n' "$label" "$calls" >&2
    exit 1
  fi
  printf 'PASS: %s\n' "$label"
}

sync_case auto_sync yes 0 GREEN env CI=false WEM_TDD_SKIP_SYNC=0 bash "$root/scripts/tdd.sh" green unit
sync_case skip_ci no 0 GREEN env CI=true WEM_TDD_SKIP_SYNC=0 bash "$root/scripts/tdd.sh" green unit
sync_case skip_explicit no 0 GREEN env CI=false WEM_TDD_SKIP_SYNC=1 bash "$root/scripts/tdd.sh" green unit
sync_case failed_pull yes 1 ERROR env CI=false WEM_TDD_SKIP_SYNC=0 TDD_GIT_FAIL=1 bash "$root/scripts/tdd.sh" green unit

printf 'Runner classification self-tests: 15/15 passed\n'
