#!/usr/bin/env bash
set -euo pipefail

# Classify *Test.php paths exactly once. Any new or ambiguous inheritance
# fails closed, so the integration gate cannot silently drop a new file.
if (($# != 3)); then
  echo 'Usage: classify-integration-tests.sh <directory> <core-manifest> <durable-manifest>' >&2
  exit 2
fi
directory=$1
core=$2
durable=$3
: > "$core"
: > "$durable"
count=0
while IFS= read -r file; do
  [[ -n $file ]] || continue
  count=$((count + 1))
  wp=0
  raw=0
  if grep -Eq 'extends[[:space:]]+\\?WP_UnitTestCase\b' "$file"; then wp=1; fi
  if grep -Eq 'extends[[:space:]]+(\\?PHPUnit\\Framework\\TestCase|TestCase)\b' "$file"; then raw=1; fi
  if (( wp + raw != 1 )); then
    printf 'ERROR: unclassified or ambiguous integration test: %s\n' "$file" >&2
    exit 1
  fi
  if (( wp )); then printf '%s\n' "$file" >> "$core"; else printf '%s\n' "$file" >> "$durable"; fi
done < <(find "$directory" -type f -name '*Test.php' | LC_ALL=C sort)
if (( count == 0 )); then echo 'ERROR: no integration tests discovered' >&2; exit 1; fi
classified=$(($(wc -l < "$core") + $(wc -l < "$durable")))
if (( classified != count )); then echo 'ERROR: test classification coverage mismatch' >&2; exit 1; fi
printf 'CLASSIFIED: total=%d core=%d durable=%d\n' "$count" "$(wc -l < "$core")" "$(wc -l < "$durable")"
