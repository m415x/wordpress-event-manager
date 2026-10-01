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
  WEM_WP_ENV_BIN="$temp/wp-env-mock" TDD_MOCK_SCENARIO="$scenario" \
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
  WEM_WP_ENV_BIN="$temp/wp-env-mock" TDD_MOCK_SCENARIO="$scenario" \
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
case_verbose assertion_failure red 'Failures: 1' -v unit
case_verbose bootstrap_failure red 'WordPress bootstrap missing' integration --verbose
case_verbose passing green 'OK (1 test, 2 assertions)' tests/Unit/PluginEntrypointTest.php --verbose
case_verbose bootstrap_failure diagnose 'WordPress bootstrap missing' integration
printf 'Runner classification self-tests: 11/11 passed\n'
