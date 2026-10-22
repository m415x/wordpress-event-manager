#!/usr/bin/env bash
# WEM-74 FK diagnostic: new, independent and disposable persistent wp-env.
set -euo pipefail
repo=$(git rev-parse --show-toplevel)
base=${1:-}
[[ $base = /* ]] || { echo 'Usage: bash scripts/wem74-probe-composite-fk.sh /ABSOLUTE/NEW/PATH' >&2; exit 2; }
[[ ! -e $base ]] || { echo 'Destination already exists: refusing reuse' >&2; exit 2; }
mkdir -p "$base"
base=$(cd "$base" && pwd -P)
current=$(cd "$repo" && pwd -P)
[[ "$base/" != "$current/"* && "$current/" != "$base/"* ]] || { echo 'Destination overlaps checkout' >&2; exit 2; }
sha=$(git -C "$repo" rev-parse HEAD)
checkout="$base/wordpress-event-manager"
git -C "$repo" worktree add --detach "$checkout" "$sha"
export WP_ENV_HOME="$base/wp-env-home"
unset WP_ENV_PORT WP_ENV_TESTS_PORT WP_ENV_MYSQL_PORT WP_ENV_TESTS_MYSQL_PORT
mkdir -p "$WP_ENV_HOME"
port=8999
config=.wp-env.wem74-fk-disposable.json
printf '{"plugins":["."],"phpVersion":"8.3","port":%s,"testsEnvironment":false}\n' "$port" > "$checkout/$config"
printf 'SHA=%s\nROOT=%s\nCONFIG=%s\nWP_ENV_HOME=%s\nPORT=%s\n' "$sha" "$base" "$config" "$WP_ENV_HOME" "$port" | tee "$base/manifest.log"
cd "$checkout"
wp-env start "--config=$config" > "$base/start.log" 2>&1
wp-env run cli "--config=$config" --env-cwd=wp-content/plugins/wordpress-event-manager composer install --no-interaction --prefer-dist --no-progress > "$base/composer.log" 2>&1
wp-env run cli "--config=$config" wp plugin activate wordpress-event-manager > "$base/activation.log" 2>&1 || {
  cat "$base/activation.log"; exit 1;
}
wp-env run cli "--config=$config" --env-cwd=wp-content/plugins/wordpress-event-manager wp eval-file scripts/wem74-composite-fk-probe.php > "$base/fk-probe.log" 2>&1 || {
  tail -n 110 "$base/fk-probe.log"; exit 1;
}
wp-env status "--config=$config" > "$base/status.log" 2>&1 || true
tail -n 35 "$base/fk-probe.log"
echo "Evidence preserved at $base. No cleanup/stop of existing environments was performed."
