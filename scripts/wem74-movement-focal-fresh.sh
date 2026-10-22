#!/usr/bin/env bash
# WEM-74: focused first-pass check of movement tests in disposable WordPress.
# No existing wp-env DB is touched, and no deletion is performed.
set -euo pipefail
repo=$(git rev-parse --show-toplevel)
base=${1:-}
port=${2:-9011}
[[ "$base" = /* && ! -e "$base" ]] || { echo "Provide a NEW absolute path" >&2; exit 2; }
[[ "$port" =~ ^[0-9]+$ ]] && ((port > 1024 && port < 65536)) || exit 2
mkdir -p "$base"
base=$(cd "$base" && pwd -P)
sha=$(git -C "$repo" rev-parse HEAD)
checkout="$base/wordpress-event-manager"
git -C "$repo" worktree add --detach "$checkout" "$sha"
export WP_ENV_HOME="$base/wp-env-home"
unset WP_ENV_PORT WP_ENV_TESTS_PORT WP_ENV_MYSQL_PORT WP_ENV_TESTS_MYSQL_PORT
mkdir -p "$WP_ENV_HOME"
config=.wp-env.wem74-movement-focal.json
printf '{"plugins":["."],"phpVersion":"8.3","port":%s,"testsEnvironment":false}\n' "$port" > "$checkout/$config"
printf 'SHA=%s\nROOT=%s\nPORT=%s\nCONFIG=%s\n' "$sha" "$base" "$port" "$config" | tee "$base/manifest.log"
cd "$checkout"
wp-env start "--config=$config" >"$base/start.log" 2>&1 || { tail -n 40 "$base/start.log"; exit 2; }
wp-env run cli "--config=$config" --env-cwd=wp-content/plugins/wordpress-event-manager composer install --no-interaction --prefer-dist --no-progress >"$base/composer.log" 2>&1 || { tail -n 30 "$base/composer.log"; exit 2; }
wp-env run cli "--config=$config" wp plugin activate wordpress-event-manager >"$base/activation.log" 2>&1 || { tail -n 40 "$base/activation.log"; exit 2; }
set +e
for test in MovementLedgerPersistenceTest AtomicCheckinTransitionTest; do
  wp-env run cli "--config=$config" --env-cwd=wp-content/plugins/wordpress-event-manager vendor/bin/phpunit -c phpunit.integration.xml.dist "tests/Integration/${test}.php" >"$base/${test}.log" 2>&1
  printf '%s EXIT=%s\n' "$test" "$?" | tee -a "$base/manifest.log"
  tail -n 22 "$base/${test}.log"
done
set -e
echo "Evidence retained at $base; NO repeatability claim and NO cleanup."
