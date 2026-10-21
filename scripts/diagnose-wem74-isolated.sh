#!/usr/bin/env bash
# WEM-74/WEM-72 disposable comparison: never touches existing .wp-env.phpunit.json.
set -euo pipefail
repo=$(git rev-parse --show-toplevel)
feature_sha=640e1be60639d1209aa218820b744298f9874bb8
dev_sha=cc60086cc0e62455cbbc6822139b1d1bff2ed612
case ${1:-} in
  '') echo 'Usage: bash scripts/diagnose-wem74-isolated.sh /ABSOLUTE/PATH/NEW-EMPTY-DIRECTORY' >&2; exit 2;;
esac
base=$1
[[ $base = /* ]] || { echo 'ERROR: absolute directory required' >&2; exit 2; }
[[ ! -e $base ]] || { echo 'ERROR: destination already exists; refusing reuse or cleanup' >&2; exit 2; }
mkdir -p "$base"
base=$(cd "$base" && pwd -P)
current=$(cd "$repo" && pwd -P)
[[ "$base/" != "$current/"* && "$current/" != "$base/"* ]] || { echo 'ERROR: destination overlaps repo' >&2; exit 2; }
printf 'DIAGNOSTIC ROOT: %s\n' "$base" | tee "$base/manifest.txt"
git -C "$repo" rev-parse --verify "$dev_sha^{commit}" >/dev/null
git -C "$repo" rev-parse --verify "$feature_sha^{commit}" >/dev/null

for lane in dev feature; do
  if [[ $lane = dev ]]; then sha=$dev_sha; port=8996; else sha=$feature_sha; port=8998; fi
  lane_root="$base/$lane"
  mkdir -p "$lane_root"
  checkout="$lane_root/wordpress-event-manager"
  git -C "$repo" worktree add --detach "$checkout" "$sha"
  home="$lane_root/wp-env-home"
  mkdir -p "$home"
  cat > "$checkout/.wp-env.wem74-isolated.json" <<EOF
{"plugins":["."],"phpVersion":"8.3","port":$port,"testsEnvironment":false}
EOF
  echo "$lane SHA=$sha CONFIG=$checkout/.wp-env.wem74-isolated.json WP_ENV_HOME=$home PORT=$port" | tee -a "$base/manifest.txt"
  (
    cd "$checkout"
    export WP_ENV_HOME="$home"
    unset WP_ENV_PORT WP_ENV_TESTS_PORT WP_ENV_MYSQL_PORT WP_ENV_TESTS_MYSQL_PORT
    config=.wp-env.wem74-isolated.json
    wp-env start "--config=$config" >"$lane_root/start.log" 2>&1
    wp-env run cli "--config=$config" --env-cwd=wp-content/plugins/wordpress-event-manager composer install --no-interaction --prefer-dist --no-progress >"$lane_root/composer.log" 2>&1
    read_counts() {
      local stage=$1
      wp-env run cli "--config=$config" wp db query "SELECT DATABASE() AS db, (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wp_wem_guest_movements') AS movement_table_exists;" > "$lane_root/$stage-presence.log" 2>&1 || true
      # Table may not exist on a brand-new installation: only count if present.
      local found
      found=$(wp-env run cli "--config=$config" wp db query --skip-column-names "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wp_wem_guest_movements';" 2>/dev/null | tr -d '\r[:space:]' || true)
      if [[ $found = 1 ]]; then
        wp-env run cli "--config=$config" wp db query "SELECT guest_id,event_term_id,COUNT(*) AS n FROM wp_wem_guest_movements GROUP BY guest_id,event_term_id ORDER BY guest_id,event_term_id;" > "$lane_root/$stage-counts.log" 2>&1 || true
      else
        printf 'movement table absent\n' > "$lane_root/$stage-counts.log"
      fi
    }
    read_counts initial
    for cycle in 1 2; do
      for testfile in MovementLedgerPersistenceTest AtomicCheckinTransitionTest; do
        # Bypass tdd.sh: that runner hardcodes the EXISTING PHPUnit config.
        set +e
        wp-env run cli "--config=$config" --env-cwd=wp-content/plugins/wordpress-event-manager vendor/bin/phpunit -c phpunit.integration.xml.dist "tests/Integration/$testfile.php" >"$lane_root/$testfile-$cycle.log" 2>&1
        code=$?
        set -e
        printf '%s %s cycle=%s exit=%s\n' "$lane" "$testfile" "$cycle" "$code" | tee -a "$base/manifest.txt"
        read_counts "$testfile-$cycle"
      done
    done
    wp-env status "--config=$config" >"$lane_root/status.log" 2>&1 || true
    # Deliberately do NOT destroy, stop or clean; preserve forensic artifacts for review.
  )
done
echo "COMPARISON COMPLETE: $base"
echo 'Review manifest and per-lane logs before classifying. All disposable resources retained; none cleaned.'
