#!/usr/bin/env bash
set -uo pipefail

usage() {
  printf 'Usage: bash scripts/tdd.sh <red|green|diagnose> <unit|integration> [--filter TestName]\n' >&2
}

if (($# < 2)); then usage; exit 2; fi
mode=$1
suite=$2
shift 2

case "$mode" in red|green|diagnose) ;; *) usage; exit 2 ;; esac
case "$suite" in unit|integration) ;; *) usage; exit 2 ;; esac

filter_args=()
if (($# > 0)); then
  if (($# != 2)) || [[ $1 != "--filter" ]] || [[ -z $2 ]]; then
    usage
    exit 2
  fi
  filter_args=(--filter "$2")
fi

# The wp-env Docker CLI is required. WEM_WP_ENV_BIN is only for the
# command-runner self-test, never for production integration tests.
wp_env_bin=${WEM_WP_ENV_BIN:-wp-env}
container_cwd='wp-content/plugins/wordpress-event-manager'
configuration='phpunit.xml.dist'
if [[ $suite == integration ]]; then configuration='phpunit.integration.xml.dist'; fi

base_command=("$wp_env_bin" run cli --config=.wp-env.test.json "--env-cwd=$container_cwd")
phpunit_command=("${base_command[@]}" vendor/bin/phpunit -c "$configuration" "${filter_args[@]}")

log=$(mktemp) || { printf 'Could not create an output file.\n' >&2; exit 2; }
trap 'rm -f "$log"' EXIT

printf 'TDD %s | %s%s\n' "${mode^^}" "$suite" "${filter_args[*]:+ | ${filter_args[*]}}"

set +e
"${phpunit_command[@]}" >"$log" 2>&1
test_exit=$?
set -e

if [[ $mode == diagnose ]]; then
  cat "$log"
  printf 'Exit code: %d\n' "$test_exit"
  exit "$test_exit"
fi

count_line=$(grep -E '^Tests: [0-9]+' "$log" | tail -1 || true)
if [[ -n $count_line ]]; then printf '%s\n' "$count_line"; fi

# A RED must be an actual PHPUnit assertion failure. Do not accept
# startup failures, missing dependencies, PHP fatals, or test errors.
if [[ $mode == red ]]; then
  if ((test_exit != 0)) &&
     grep -Eq '^FAILURES!$' "$log" &&
     grep -Eq 'Failures: [1-9][0-9]*' "$log" &&
     ! grep -Eq 'Errors: [1-9][0-9]*|^ERRORS!$|Fatal error:|PHP Fatal error:|No tests executed!' "$log"; then
    printf 'RED confirmed: assertions failed (expected only when the contract is deliberately unimplemented).\n'
    exit 0
  fi
  printf 'RED not confirmed: expected an assertion failure, got pass or infrastructure/test error.\n' >&2
  tail -n 35 "$log" >&2
  exit 1
fi

if ((test_exit != 0)) || ! grep -Eq '^OK \([0-9]+ tests?, ' "$log"; then
  printf 'GREEN failed: focused PHPUnit run was not successful.\n' >&2
  tail -n 35 "$log" >&2
  exit 1
fi

printf 'Focused tests: GREEN. Checking PHP syntax...\n'
# Static gate for WEM-6: PHP syntax only. PHPStan/PHPCS and CI belong to WEM-7.
static_cmd='set -eu; php -l wordpress-event-manager.php >/dev/null; find includes tests -type f -name "*.php" -exec sh -c '\''for file do php -l "$file" >/dev/null || exit 1; done'\'' sh {} +'
set +e
"${base_command[@]}" sh -lc "$static_cmd" >"$log" 2>&1
static_exit=$?
set -e
if ((static_exit != 0)); then
  printf 'GREEN failed: PHP syntax verification could not complete.\n' >&2
  tail -n 35 "$log" >&2
  exit 1
fi
printf 'GREEN confirmed: focused tests and PHP syntax verification passed.\n'
