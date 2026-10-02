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
  git_log=$(mktemp) || { printf 'ERROR\n' >&2; exit 2; }
  if ! "${WEM_TDD_GIT_BIN:-git}" pull --ff-only --quiet >"$git_log" 2>&1; then
    printf 'ERROR\n' >&2
    if [[ $verbose == true ]]; then cat "$git_log" >&2; fi
    rm -f "$git_log"
    exit 1
  fi
  if [[ $verbose == true && -s $git_log ]]; then cat "$git_log"; fi
  rm -f "$git_log"
fi

wp_env_bin=${WEM_WP_ENV_BIN:-wp-env}
container_cwd='wp-content/plugins/wordpress-event-manager'
configuration=phpunit.xml.dist
if [[ $suite == integration ]]; then configuration=phpunit.integration.xml.dist; fi

# Dedicated PHPUnit WordPress installation: its database must never be
# shared with the manual site at localhost:8890.
wp_env_config='.wp-env.phpunit.json'
base_command=("$wp_env_bin" run cli "--config=$wp_env_config" "--env-cwd=$container_cwd")
phpunit_command=("${base_command[@]}" vendor/bin/phpunit -c "$configuration")
if [[ -n $filter ]]; then phpunit_command+=(--filter "$filter"); fi
if [[ -n $test_path ]]; then phpunit_command+=("$test_path"); fi

log=$(mktemp) || { printf 'ERROR\n' >&2; exit 2; }
trap 'rm -f "$log"' EXIT

set +e
"${phpunit_command[@]}" >"$log" 2>&1
test_exit=$?
set -e

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
