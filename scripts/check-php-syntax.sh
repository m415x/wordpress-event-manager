#!/bin/sh
# POSIX shell; enforce LF checkout via .gitattributes on Windows.
set -eu

php -l wordpress-event-checkin-manager.php >/dev/null
find includes tests -type f -name '*.php' -exec sh -c '
    for file do
        php -l "$file" >/dev/null || exit 1
    done
' sh {} +
printf 'PHP syntax: OK (plugin entrypoint, includes/, tests/)\n'
