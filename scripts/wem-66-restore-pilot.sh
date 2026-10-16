#!/usr/bin/env bash
set -Eeuo pipefail
# One-shot, destructive ONLY to the explicitly verified disposable PHPUnit 8892 DB.
cd "$(dirname "$0")/.."
config=.wp-env.phpunit.json
plugin="wp-content/plugins/$(basename "$PWD")"
dir=.tmp/wem-66-snapshot
reference="$dir/pre-restore.sql"
historical="$dir/phpunit-baseline.sql"
expected_host=915c3be11d98
testfile=tests/Integration/InvitationCredentialLifecycleTest.php
methods=(testIssueCreatesOneOpaqueActiveCredentialWithoutPersistingBearerSecret testRotateInvalidatesPreviousGenerationAndCreatesNewActiveCredential)
[[ $(basename "$PWD") == wordpress-event-manager-clean && -s "$reference" && -s "$historical" ]] || { echo "DENY checkout/snapshots" >&2; exit 2; }
node -e 'let c=require("./.wp-env.phpunit.json");if(c.port!==8892||c.testsEnvironment!==false||JSON.stringify(c.plugins)!=="[\".\"]")process.exit(2)' || { echo "DENY config" >&2; exit 2; }
cli() { wp-env run cli "--config=$config" -- "$@"; }
phpunit() { wp-env run cli "--config=$config" "--env-cwd=$plugin" -- "$@"; }
identity() {
  local url prefix db active host
  url=$(cli wp option get siteurl) || return 2
  prefix=$(cli wp db prefix) || return 2
  db=$(cli wp config get DB_NAME) || return 2
  active=$(cli wp db query "SELECT DATABASE()" --skip-column-names --silent) || return 2
  host=$(cli wp db query "SELECT @@hostname" --skip-column-names --silent) || return 2
  [[ "$url" == http://localhost:8892 && "$prefix" == wp_ && "$db" == wordpress && "$active" == wordpress && "$host" == "$expected_host" ]] || { echo "DENY target identity mismatch" >&2; return 2; }
}
identity || exit 2
ref_sha=$(sha256sum "$reference" | cut -d " " -f1)
hist_sha=$(sha256sum "$historical" | cut -d " " -f1)
work="$dir/one-shot-$$"; mkdir -p "$work"
echo "REFERENCE_SHA256=$ref_sha HISTORIC_SHA256=$hist_sha"
# Verify snapshots are within the expected WordPress structure and include required tables.
for t in wp_posts wp_postmeta wp_users wp_usermeta wp_terms wp_term_taxonomy wp_term_relationships wp_wem_guest_movements wp_wem_invitation_credentials wp_options; do
  grep -Fq "CREATE TABLE \`$t\`" "$reference" || { echo "DENY absent snapshot table: $t" >&2; exit 2; }
done
# Canonical functional comparison ignores only known volatile option values.
# Full SQL diff is always saved and reported, including unapproved changes.
canonical() {
  local infile=$1 outfile=$2
  sed -E '/^-- Dump completed on /d; s/(\x27_site_transient_timeout_wp_theme_files_patterns-[^\x27]+\x27,)[^,]+,/\1<VOLATILE>,/; s/(\x27_transient_doing_cron\x27,)[^,]+,/\1<VOLATILE>,/' "$infile" > "$outfile"
}
# IMPORTANT: capture an immutable reference of all rows before touching the database.
cp "$reference" "$work/reference.sql"
canonical "$work/reference.sql" "$work/reference.canonical.sql"
pending=0; restore_failed=0
restore() {
  local stage=$1 after="$work/after-$1.sql"
  identity || { echo "STOP: unsafe target, no import" >&2; return 2; }
  [[ $(sha256sum "$reference" | cut -d " " -f1) == "$ref_sha" && $(sha256sum "$historical" | cut -d " " -f1) == "$hist_sha" ]] || { echo "STOP: snapshots changed" >&2; return 2; }
  echo "RESTORE $stage"
  cli wp db import "$plugin/$reference" || return 1
  pending=0
  identity || return 2
  cli wp db export "$plugin/$work/after-$stage.sql" || return 1
  canonical "$after" "$work/after-$stage.canonical.sql"
  if ! cmp -s "$work/reference.canonical.sql" "$work/after-$stage.canonical.sql"; then
    diff -u "$work/reference.canonical.sql" "$work/after-$stage.canonical.sql" > "$work/diff-$stage.txt" || true
    echo "STOP: functional baseline differs: $work/diff-$stage.txt" >&2; return 1
  fi
  if ! cmp -s "$work/reference.sql" "$after"; then
    diff -u "$work/reference.sql" "$after" > "$work/volatile-diff-$stage.txt" || true
    echo "NOTE: raw dump changed; inspect $work/volatile-diff-$stage.txt (only allowed volatile options/dump date)"
  fi
  echo "RESTORE $stage PASS: complete canonical SQL baseline"
}
on_exit() {
  local status=$?; trap - EXIT ERR INT TERM
  if (( pending )); then
    echo "TRAP: unconditional restoration after interruption/failure" >&2
    restore interrupted || { echo "CRITICAL: restore failed, manual recovery required" >&2; exit 1; }
  fi
  exit "$status"
}
trap on_exit EXIT
trap 'exit 130' INT; trap 'exit 143' TERM
for method in "${methods[@]}"; do
  identity || exit 2
  pending=1
  set +e
  log="$work/phpunit-$method.log"
  phpunit vendor/bin/phpunit -c phpunit.integration.xml.dist --filter "$method" "$testfile" 2>&1 | tee "$log"
  result=${PIPESTATUS[0]}
  if ! grep -Eq "^OK \([1-9][0-9]* tests?,|^OK \(1 test,|^Tests: [1-9][0-9]*," "$log"; then
    echo "STOP: PHPUnit did not demonstrate execution of a positive test count" >&2
    result=1
  fi
  set -e
  restore "$method" || exit 1
  echo "METHOD_EXIT $method: $result"
  if (( result != 0 )); then echo "STOP: test failed but restore verified" >&2; exit 1; fi
done
echo "ONE-SHOT PILOT PASS: both methods + restores; historic residues not intentionally cleaned."
