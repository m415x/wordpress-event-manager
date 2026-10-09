#!/usr/bin/env bash
set -euo pipefail

# WEM-34: create a clean, self-contained WordPress plugin release archive.
# Run in a PHP 8.3+ environment with composer, ext-mbstring, git, tar and zip.
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

for tool in git tar composer php zip; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    printf 'ERROR: packaging requires %s.\n' "$tool" >&2
    exit 1
  fi
done

if ! php -r 'exit(extension_loaded("mbstring") ? 0 : 1);'; then
  printf 'ERROR: ext-mbstring is required by the QR runtime.\n' >&2
  exit 1
fi

# Never package a modified/index-only version or untracked local artifacts.
# HEAD must contain the audited and committed composer.lock.
if ! git diff --quiet HEAD -- composer.json composer.lock; then
  printf 'ERROR: commit composer.json and composer.lock before packaging.\n' >&2
  exit 1
fi

stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
plugin="$stage/wordpress-event-manager"
mkdir -p "$plugin"
git archive --format=tar HEAD | tar -xf - -C "$plugin"

# No credentials, local wp-env state, development suites, or historical tools.
rm -rf "$plugin/.github" "$plugin/docs" "$plugin/tests" "$plugin/scripts" \
  "$plugin/.gitignore" "$plugin/.gitattributes" "$plugin/.wp-env.json" \
  "$plugin/.wp-env.test.json" "$plugin/.wp-env.phpunit.json" \
  "$plugin/node_modules" "$plugin/package-lock.json" "$plugin/pnpm-lock.yaml" \
  "$plugin/package.json" "$plugin/phpunit.xml.dist" \
  "$plugin/phpunit.integration.xml.dist" "$plugin/phpstan.neon.dist" \
  "$plugin/phpcs.xml.dist" "$plugin/AGENTS.md"

(
  cd "$plugin"
  composer install --no-dev --no-scripts --no-interaction --prefer-dist \
    --optimize-autoloader --no-progress
)

test -f "$plugin/vendor/autoload.php" || {
  printf 'ERROR: production vendor/autoload.php missing.\n' >&2
  exit 1
}
test -f "$plugin/vendor/chillerlan/php-qrcode/composer.json" || {
  printf 'ERROR: chillerlan/php-qrcode not packaged.\n' >&2
  exit 1
}
test -f "$plugin/vendor/chillerlan/php-settings-container/composer.json" || {
  printf 'ERROR: QR runtime settings dependency not packaged.\n' >&2
  exit 1
}
php -r 'require $argv[1]; exit(class_exists("chillerlan\\QRCode\\QRCode") ? 0 : 1);' \
  "$plugin/vendor/autoload.php"

# Keep upstream license files delivered by Composer and root GPL metadata.
mkdir -p "$repo_root/dist"
out="$repo_root/dist/wordpress-event-manager.zip"
rm -f "$out"
(cd "$stage" && zip -q -r "$out" wordpress-event-manager)
test -s "$out"
printf 'Production ZIP created: %s\n' "$out"
