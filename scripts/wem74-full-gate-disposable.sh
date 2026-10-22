#!/usr/bin/env bash
# WEM-74: one complete PHPUnit pass against a brand-new disposable WP instance.
# Preserve all logs and SQL snapshots. Never reuse, destroy, or clean an environment.
set -uo pipefail
repo=$(git rev-parse --show-toplevel) || exit 2
root=${1:-}
port=${2:-9003}
[[ $root = /* ]] || { echo 'Usage: bash scripts/wem74-full-gate-disposable.sh /ABSOLUTE/NEW/PATH [FREE_PORT]' >&2; exit 2; }
[[ ! -e $root ]] || { echo 'Refusing pre-existing directory' >&2; exit 2; }
[[ $port =~ ^[0-9]+$ ]] && ((port > 1024 && port < 65536)) || { echo 'Invalid port' >&2; exit 2; }
mkdir -p "$root" || exit 2
root=$(cd "$root" && pwd -P)
src=$(cd "$repo" && pwd -P)
[[ "$root/" != "$src/"* && "$src/" != "$root/"* ]] || { echo 'Root overlaps repository' >&2; exit 2; }
sha=$(git -C "$repo" rev-parse HEAD)
checkout="$root/wordpress-event-manager"
git -C "$repo" worktree add --detach "$checkout" "$sha" || exit 2
export WP_ENV_HOME="$root/wp-env-home"
unset WP_ENV_PORT WP_ENV_TESTS_PORT WP_ENV_MYSQL_PORT WP_ENV_TESTS_MYSQL_PORT
mkdir -p "$WP_ENV_HOME"
config=.wp-env.wem74-gate-disposable.json
printf '{"plugins":["."],"phpVersion":"8.3","port":%s,"testsEnvironment":false}\n' "$port" > "$checkout/$config"
printf 'SHA=%s\nROOT=%s\nWP_ENV_HOME=%s\nPORT=%s\nCONFIG=%s\n' "$sha" "$root" "$WP_ENV_HOME" "$port" "$config" | tee "$root/manifest.log"
cd "$checkout" || exit 2
wp-env start "--config=$config" >"$root/start.log" 2>&1 || { tail -n 60 "$root/start.log"; exit 2; }
wp-env run cli "--config=$config" --env-cwd=wp-content/plugins/wordpress-event-manager composer install --no-interaction --prefer-dist --no-progress >"$root/composer.log" 2>&1 || { tail -n 60 "$root/composer.log"; exit 2; }
wp-env run cli "--config=$config" wp plugin activate wordpress-event-manager >"$root/activation.log" 2>&1 || { tail -n 60 "$root/activation.log"; exit 2; }
snapshot() {
  local stage=$1
  wp-env run cli "--config=$config" wp db query "
SELECT DATABASE() AS effective_database;
SELECT table_name, engine, table_type FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'wp_wem_%' ORDER BY table_name;
SELECT 'tables' AS entity, COUNT(*) AS n FROM wp_wem_event_tables
UNION ALL SELECT 'reservations', COUNT(*) FROM wp_wem_table_reservations
UNION ALL SELECT 'consent', COUNT(*) FROM wp_wem_seating_visibility_consent
UNION ALL SELECT 'participation', COUNT(*) FROM wp_wem_seating_participation
UNION ALL SELECT 'audit', COUNT(*) FROM wp_wem_seating_admin_audit
UNION ALL SELECT 'movements', COUNT(*) FROM wp_wem_guest_movements;
SELECT constraint_name, table_name, referenced_table_name FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE() ORDER BY table_name,constraint_name;
" >"$root/$stage-sql.log" 2>&1
}
snapshot initial || { cat "$root/initial-sql.log"; exit 2; }
# The new WEM seating tables MUST be empty; no selective deletes are performed.
if ! grep -Eq '^tables[[:space:]]+0\r?$' "$root/initial-sql.log" ||
   ! grep -Eq '^reservations[[:space:]]+0\r?$' "$root/initial-sql.log"; then
  echo 'Initial SQL does not establish clean seating storage; refusing gate' | tee -a "$root/manifest.log"
  exit 2
fi
set +e
wp-env run cli "--config=$config" --env-cwd=wp-content/plugins/wordpress-event-manager vendor/bin/phpunit -c phpunit.xml.dist >"$root/unit.log" 2>&1
unit=$?
wp-env run cli "--config=$config" --env-cwd=wp-content/plugins/wordpress-event-manager vendor/bin/phpunit -c phpunit.integration.xml.dist >"$root/integration.log" 2>&1
integration=$?
set -e
snapshot final || true
wp-env status "--config=$config" >"$root/status.log" 2>&1 || true
printf 'UNIT_EXIT=%s\nINTEGRATION_EXIT=%s\n' "$unit" "$integration" | tee -a "$root/manifest.log"
echo '--- Unit summary ---'; tail -n 25 "$root/unit.log"
echo '--- Integration summary ---'; tail -n 55 "$root/integration.log"
echo "EVIDENCE: $root (environment retained; no cleanup)"
if ((unit != 0 || integration != 0)); then exit 1; fi
echo 'WEM-74 FRESH-INSTANCE FULL PHPUNIT GATE: PASS (single pass only; no repeatability claim)'
