#!/usr/bin/env bash
set -euo pipefail

# Isolated PHPUnit installation. This script must never operate on
# the manual WordPress .wp-env.test.json installation or its database.
config='.wp-env.phpunit.json'
# wp-env mounts "." under the checkout directory's basename.
# Never assume the plugin directory has the canonical production slug.
checkout_name="$(basename "$PWD")"
container_cwd="wp-content/plugins/${checkout_name}"

if [[ $# != 1 ]]; then
  printf 'Usage: bash scripts/wp-phpunit.sh <start|stop>\n' >&2
  exit 2
fi

case "$1" in
  start)
    wp-env start "--config=$config"
    if [[ -f vendor/autoload.php && -f vendor/chillerlan/php-qrcode/composer.json ]]; then
      printf 'Composer dependencies already installed in checkout; preserving vendor files.\n'
    else
      wp-env run cli "--config=$config" "--env-cwd=$container_cwd" \
        composer install --no-interaction --prefer-dist --no-progress
    fi
    printf 'ISOLATED PHPUNIT READY (separate from localhost:8890).\n'
    ;;
  stop)
    wp-env stop "--config=$config"
    printf 'ISOLATED PHPUNIT STOPPED (data preserved).\n'
    ;;
  *)
    printf 'Usage: bash scripts/wp-phpunit.sh <start|stop>\n' >&2
    exit 2
    ;;
esac
