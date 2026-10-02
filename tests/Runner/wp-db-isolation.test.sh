#!/usr/bin/env bash
set -euo pipefail

# WEM-11 regression guard: PHPUnit must not use the manual WordPress site's
# wp-env configuration. This test reads configuration; never invokes Docker.
root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
cd "$root"

manual='.wp-env.test.json'
isolated='.wp-env.phpunit.json'
runner='scripts/tdd.sh'

if [[ ! -f "$manual" || ! -f "$isolated" ]]; then
  printf 'FAIL: a dedicated PHPUnit wp-env configuration is required.\n' >&2
  exit 1
fi

if ! grep -Fq "wp_env_config='.wp-env.phpunit.json'" "$runner"; then
  printf 'FAIL: TDD runner must explicitly select isolated PHPUnit wp-env.\n' >&2
  exit 1
fi

# The runner must never invoke the manual site's wp-env configuration.
if grep -Fq 'base_command=("$wp_env_bin" run cli --config=.wp-env.test.json' "$runner"; then
  printf 'FAIL: runner still targets manual WordPress wp-env.\n' >&2
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  # Portable local test remains dependency-light; Node is available for pnpm.
  node -e '
    const fs = require("node:fs")
    const dev = JSON.parse(fs.readFileSync(".wp-env.test.json", "utf8"))
    const test = JSON.parse(fs.readFileSync(".wp-env.phpunit.json", "utf8"))
    if (dev.port === test.port || !Number.isInteger(test.port)) process.exit(1)
  ' || { printf 'FAIL: development and PHPUnit HTTP ports must differ.\n' >&2; exit 1; }
else
  php -r '
    $dev = json_decode(file_get_contents(".wp-env.test.json"), true);
    $test = json_decode(file_get_contents(".wp-env.phpunit.json"), true);
    if (!isset($dev["port"], $test["port"]) || $dev["port"] === $test["port"]) exit(1);
  ' || { printf 'FAIL: development and PHPUnit HTTP ports must differ.\n' >&2; exit 1; }
fi

printf 'PASS: manual wp-env and PHPUnit use distinct configurations and ports.\n'
printf 'WORDPRESS DATABASE ISOLATION: GREEN (configuration only)\n'
