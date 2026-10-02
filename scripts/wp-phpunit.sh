#!/usr/bin/env bash
set -euo pipefail

# Isolated PHPUnit installation. This script must never operate on
# the manual WordPress .wp-env.test.json installation or its database.
config='.wp-env.phpunit.json'
container_cwd='wp-content/plugins/wordpress-event-manager'

if [[ $# != 1 ]]; then
  printf 'Usage: bash scripts/wp-phpunit.sh <start|stop>\n' >&2
  exit 2
fi

case "$1" in
  start)
    wp-env start "--config=$config"
    wp-env run cli "--config=$config" "--env-cwd=$container_cwd" \
      composer install --no-interaction --prefer-dist --no-progress
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
