#!/usr/bin/env bash
set -euo pipefail

# Local, disposable WordPress only. Never fall back to another wp-env config.
config='.wp-env.test.json'
plugin='wordpress-event-manager'

usage() {
  printf 'Usage: bash scripts/wp-local.sh <start|status|stop>\n' >&2
  exit 2
}

[[ $# == 1 ]] || usage

case "$1" in
  start)
    wp-env start "--config=$config"
    if wp-env run cli "--config=$config" wp plugin is-active "$plugin"; then
      printf 'Plugin already active.\n'
    else
      wp-env run cli "--config=$config" wp plugin activate "$plugin"
    fi
    wp-env run cli "--config=$config" wp plugin status "$plugin"
    printf 'LOCAL WORDPRESS READY: http://localhost:8890\n'
    ;;
  status)
    wp-env run cli "--config=$config" wp plugin status "$plugin"
    ;;
  stop)
    wp-env stop "--config=$config"
    printf 'LOCAL WORDPRESS STOPPED (data preserved).\n'
    ;;
  *)
    usage
    ;;
esac
