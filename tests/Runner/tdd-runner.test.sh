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
  local expected=$1 scenario=$2 mode=$3 suite=$4
  shift 4
  local result=0
  WEM_WP_ENV_BIN="$temp/wp-env-mock" TDD_MOCK_SCENARIO="$scenario" \
    bash "$root/scripts/tdd.sh" "$mode" "$suite" "$@" >"$temp/result.log" 2>&1 || result=$?
  if [[ $result -ne $expected ]]; then
    printf 'FAIL: %s %s %s (expected exit %s, got %s)\n' "$scenario" "$mode" "$suite" "$expected" "$result" >&2
    cat "$temp/result.log" >&2
    exit 1
  fi
  printf 'PASS: %s %s %s\n' "$scenario" "$mode" "$suite"
}

case_test 0 assertion_failure red unit --filter ContractNotImplemented
case_test 1 passing red unit
case_test 1 bootstrap_failure red integration
case_test 0 passing green unit
case_test 0 passing green integration --filter CanReadMetadata
case_test 1 assertion_failure green unit
case_test 1 syntax_failure green unit
case_test 2 bootstrap_failure diagnose integration
printf 'Runner classification self-tests: 8/8 passed\n'
