#!/usr/bin/env bash
set -euo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
temp=$(mktemp -d)
trap 'rm -rf "$temp"' EXIT
mkdir -p "$temp/tests"
printf '%s\n' '<?php class CoreTest extends WP_UnitTestCase {}' > "$temp/tests/CoreTest.php"
printf '%s\n' '<?php class DurableTest extends TestCase {}' > "$temp/tests/DurableTest.php"
printf '%s\n' '<?php class AjaxTest extends WP_Ajax_UnitTestCase {}' > "$temp/tests/AjaxTest.php"
bash "$root/scripts/classify-integration-tests.sh" "$temp/tests" "$temp/core" "$temp/durable" > "$temp/classification"
grep -q 'total=3 core=2 durable=1' "$temp/classification"
[[ $(wc -l < "$temp/core") -eq 2 && $(wc -l < "$temp/durable") -eq 1 ]]
printf '%s\n' '<?php class UnknownTest extends CustomBase {}' > "$temp/tests/UnknownTest.php"
if bash "$root/scripts/classify-integration-tests.sh" "$temp/tests" "$temp/core" "$temp/durable" > /dev/null 2>&1; then
  echo 'FAIL: unknown test was silently omitted' >&2; exit 1
fi
rm "$temp/tests/UnknownTest.php"

cat > "$temp/mock" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
if [[ " $* " == *" sh -lc "* ]]; then exit 0; fi
config=''
last=''
while (($#)); do
  if [[ $last == -c ]]; then config=$1; break; fi
  last=$1
  shift
done
[[ -n $config ]] || exit 3
printf '%s\n' "$config" >> "$WEM_TEST_CALLS"
if [[ $config == *integration-core* ]]; then
  if [[ ${WEM_TEST_FAIL_GROUP:-} == core ]]; then
    printf '%s\n' 'FAILURES!' 'Tests: 1, Assertions: 1, Failures: 1.'
    exit 1
  fi
else
  if [[ ${WEM_TEST_FAIL_GROUP:-} == durable ]]; then
    printf '%s\n' 'FAILURES!' 'Tests: 1, Assertions: 1, Failures: 1.'
    exit 1
  fi
fi
printf '%s\n' 'OK (1 test, 1 assertion)'
MOCK
chmod +x "$temp/mock"
run() {
  local failed=$1 expected=$2 result=0
  : > "$temp/calls"
  (cd "$root" && WEM_TDD_SKIP_SYNC=1 WEM_WP_ENV_BIN="$temp/mock" \
    WEM_TEST_CALLS="$temp/calls" WEM_TEST_FAIL_GROUP="$failed" \
    bash scripts/tdd.sh green integration --filter 'SomeTest' -v) >"$temp/output" 2>&1 || result=$?
  [[ $result -eq $expected ]] || { cat "$temp/output"; echo "Wrong exit: $result" >&2; exit 1; }
  [[ $(wc -l < "$temp/calls") -eq 2 ]] || { echo 'Runner omitted a group' >&2; exit 1; }
  grep -q 'integration-core' "$temp/calls"
  grep -q 'integration-durable' "$temp/calls"
  if (( expected == 0 )); then grep -q GREEN "$temp/output"; else grep -q ERROR "$temp/output"; fi
}
run '' 0
run core 1
run durable 1
echo 'PASS: exhaustive classifier, unknown-test refusal, sequential groups and failure propagation'
