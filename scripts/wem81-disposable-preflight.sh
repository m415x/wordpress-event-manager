#!/usr/bin/env bash
# WEM-81/T1: disposable, independent InnoDB environment preflight.
# No tests claimed, no cleanups, and no reuse of existing WP_ENV_HOME.
set -euo pipefail
repo=$(git rev-parse --show-toplevel)
root=${1:-}
port=${2:-9021}
[[ $root = /* && ! -e $root ]] || { echo "ERROR: specify a nonexistent absolute evidence directory" >&2; exit 2; }
[[ $port =~ ^[0-9]+$ ]] && ((port > 1024 && port < 65536)) || exit 2
src=$(cd "$repo" && pwd -P)
[[ "$root/" != "$src/"* && "$src/" != "$root/"* ]] || exit 2
mkdir -p "$root"
root=$(cd "$root" && pwd -P)
sha=$(git -C "$repo" rev-parse HEAD)
git -C "$repo" worktree add --detach "$root/plugin" "$sha"
export WP_ENV_HOME="$root/wp-env-home"
unset WP_ENV_PORT WP_ENV_TESTS_PORT WP_ENV_MYSQL_PORT WP_ENV_TESTS_MYSQL_PORT
mkdir -p "$WP_ENV_HOME"
config=.wp-env.wem81-disposable.json
printf '{"plugins":["."],"phpVersion":"8.3","port":%s,"testsEnvironment":false}\n' "$port" >"$root/plugin/$config"
printf 'SHA=%s\nROOT=%s\nPORT=%s\nCONFIG=%s\n' "$sha" "$root" "$port" "$config" | tee "$root/manifest.log"
cd "$root/plugin"
wp-env start "--config=$config" >"$root/start.log" 2>&1
wp-env run cli "--config=$config" --env-cwd=wp-content/plugins/wordpress-event-manager composer install --no-interaction --prefer-dist --no-progress >"$root/composer.log" 2>&1
wp-env run cli "--config=$config" wp plugin activate wordpress-event-manager >"$root/activation.log" 2>&1
wp-env run cli "--config=$config" wp db query "
SELECT DATABASE() AS database_name;
SELECT table_name,engine,table_type
FROM information_schema.tables
WHERE table_schema=DATABASE()
AND table_name IN ('wp_wem_event_tables','wp_wem_table_reservations','wp_wem_seating_admin_audit')
ORDER BY table_name;
SELECT COUNT(*) AS existing_reservations FROM wp_wem_table_reservations;
SELECT COUNT(*) AS existing_audits FROM wp_wem_seating_admin_audit;
" >"$root/sql-preflight.log" 2>&1
cat "$root/manifest.log" "$root/sql-preflight.log"
echo "WEM-81 ENVIRONMENT PREFLIGHT COMPLETE; retained at $root; NOT RED."
