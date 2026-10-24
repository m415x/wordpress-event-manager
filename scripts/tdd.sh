#!/usr/bin/env bash
set -uo pipefail

usage() {
  printf 'Usage: bash scripts/tdd.sh <red|green|diagnose> [unit|integration|tests/Unit/FooTest.php|tests/Integration/FooTest.php] [--filter Name] [-v|--verbose]\n' >&2
}

if (($# < 1)); then usage; exit 2; fi
mode=$1
shift
case "$mode" in red|green|diagnose) ;; *) usage; exit 2 ;; esac

suite=unit
test_path=''
if (($# > 0)); then
  case "$1" in
    unit|integration)
      suite=$1
      shift
      ;;
    tests/Unit/*.php)
      test_path=$1
      shift
      ;;
    tests/Integration/*.php)
      suite=integration
      test_path=$1
      shift
      ;;
  esac
fi

verbose=false
filter=''
while (($# > 0)); do
  case "$1" in
    -v|--verbose)
      verbose=true
      shift
      ;;
    --filter)
      if (($# < 2)) || [[ -z $2 ]] || [[ -n $filter ]]; then usage; exit 2; fi
      filter=$2
      shift 2
      ;;
    *)
      usage
      exit 2
      ;;
  esac
done

# Sync the current tracking branch before focused local test runs.
# CI runs on a detached checkout and the mock runner opts out explicitly.
# A failed fast-forward stops the test instead of masking a stale baseline.
if [[ ${CI:-} != true && ${WEM_TDD_SKIP_SYNC:-0} != 1 ]]; then
  git_# Full integration: classify every *Test.php exactly once and run the two
# transaction models in separate, sequential PHPUnit CLI invocations.
# Focused paths keep the original single-process execution contract.
integration_configs=()
cleanup_integration_configs() {
  local file
  for file in "${integration_configs[@]}"; do rm -f "$file"; done
}
if [[ $suite == integration && -z $test_path ]]; then
  integration_files=()
  while IFS= read -r name; do integration_files+=("$name"); done < <(find tests/Integration -type f -name '*Test.php' | LC_ALL=C sort)
  if (("${#integration_files[@]}" == 0)); then printf 'ERROR\n' >&2; exit 1; fi
  core_files=(); durable_files=()
  for name in "${integration_files[@]}"; do
    if grep -Eq 'extends[[:space:]]+\\?WP_UnitTestCase\\b' "$name"; then
      core_files+=("$name")
    elif grep -Eq 'extends[[:space:]]+(\\?PHPUnit\\\\Framework\\\\TestCase|TestCase)\\b' "$name"; then
      durable_files+=("$name")
    else
      printf 'ERROR: unclassified integration test: %s\n' "$name" >&2
      exit 1
    fi
  done
  if (("${#core_files[@]}" + "${#durable_files[@]}" != "${#integration_files[@]}")); then
    printf 'ERROR: integration classification does not cover all tests\n' >&2; exit 1
  fi
  # XML must live at repository root so PHPUnit resolves bootstrap and
  # test file paths relative to the same root as the canonical config.
  for group in core durable; do
    cfg=$(mktemp "$repo_root/.wem-integration-${group}.XXXXXX.xml") || exit 2
    integration_configs+=("$cfg")
    printf '%s\n' '<?xml version="1.0" encoding="UTF-8"?>' \
      '<phpunit bootstrap="tests/integration-bootstrap.php" colors="true" failOnWarning="true">' \
      '  <testsuites><testsuite name="integration">' > "$cfg"
    if [[ $group == core ]]; then selected=("${core_files[@]}"); else selected=("${durable_files[@]}"); fi
    for name in "${selected[@]}"; do
      printf '    <file>%s</file>\n' "$name" >> "$cfg"
    done
    printf '%s\n' '  </testsuite></testsuites></phpunit>' >> "$cfg"
  done
fi

log=$(mktemp) || { cleanup_integration_configs; printf 'ERROR\n' >&2; exit 2; }
trap 'rm -f "$log"; cleanup_integration_configs' EXIT

test_exit=0
if (("${#integration_configs[@]}" > 0)); then
  # No concurrent WordPress Core bootstraps or PHPUnit processes.
  for cfg in "${integration_configs[@]}"; do
    if ! grep -q '<file>' "$cfg"; then continue; fi
    command=("${base_command[@]}" vendor/bin/phpunit -c "$(basename "$cfg")")
    if [[ -n $filter ]]; then command+=(--filter "$filter"); fi
    set +e
    "${command[@]}" >>"$log" 2>&1
    status=$?
    set -e
    if (( status != 0 )); then test_exit=$status; fi
  done
else
  set +e
  "${phpunit_command[@]}" >"$log" 2>&1
  test_exit=$?
  set -e
fi

show_details() {
  if [[ $verbose == true ]] || [[ $mode == diagnose ]]; then
    cat "$log"
  fi
}

if [[ $mode == diagnose ]]; then
  show_details
  exit "$test_exit"
fi

# A valid RED is an assertion failure, never a bootstrap failure or PHP error.
if [[ $mode == red ]]; then
  if ((test_exit != 0)) &&
     grep -Eq '^FAILURES!$' "$log" &&
     grep -Eq 'Failures: [1-9][0-9]*' "$log" &&
     ! grep -Eq 'Errors: [1-9][0-9]*|^ERRORS!$|Fatal error:|PHP Fatal error:|No tests executed!' "$log"; then
    printf 'RED\n'
    show_details
    exit 0
  fi
  printf 'ERROR\n' >&2
  if [[ $verbose == true ]]; then cat "$log" >&2; fi
  exit 1
fi

if ((test_exit != 0)) || ! grep -Eq '^OK \([0-9]+ tests?, ' "$log"; then
  printf 'ERROR\n' >&2
  if [[ $verbose == true ]]; then cat "$log" >&2; fi
  exit 1
fi

# WEM-6 scoped syntax gate. Full static analysis remains a separate CI gate.
static_cmd='set -eu; php -l wordpress-event-manager.php >/dev/null; find includes tests -type f -name "*.php" -exec sh -c '\''for file do php -l "$file" >/dev/null || exit 1; done'\'' sh {} +'
phpunit_log=$log
syntax_log=$(mktemp) || { printf 'ERROR\n' >&2; exit 2; }
trap 'rm -f "$phpunit_log" "$syntax_log"' EXIT
set +e
"${base_command[@]}" sh -lc "$static_cmd" >"$syntax_log" 2>&1
static_exit=$?
set -e
if ((static_exit != 0)); then
  printf 'ERROR\n' >&2
  if [[ $verbose == true ]]; then cat "$phpunit_log" "$syntax_log" >&2; fi
  exit 1
fi

printf 'GREEN\n'
show_details
if [[ $verbose == true ]]; then cat "$syntax_log"; fi
