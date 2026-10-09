#!/usr/bin/env bash
set -euo pipefail

# WEM-34: create a clean, self-contained WordPress plugin release archive.
# Run in a PHP 8.3+ environment with composer, ext-mbstring, ext-zip and tar.
# Package a strictly allowlisted plugin tree, including from wp-env CLI containers
# that do not ship Git. Never copy the development working directory wholesale.
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

for tool in tar composer php; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    printf 'ERROR: packaging requires %s.\n' "$tool" >&2
    exit 1
  fi
done

if ! php -r 'exit(extension_loaded("zip") ? 0 : 1);'; then
  printf 'ERROR: PHP ext-zip is required to create the distributable archive.\n' >&2
  exit 1
fi

if ! php -r 'exit(extension_loaded("mbstring") ? 0 : 1);'; then
  printf 'ERROR: ext-mbstring is required by the QR runtime.\n' >&2
  exit 1
fi

# Copy only WordPress runtime files. Never include local untracked files,
# test configuration, credentials, docs, development tools, or preexisting vendor.
# Release versioning remains tied to the validated composer.lock input.
stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
plugin="$stage/wordpress-event-manager"
mkdir -p "$plugin"

runtime_paths=(wordpress-event-manager.php composer.json composer.lock includes)
optional_paths=(assets languages templates admin public css js images)
for path in "${optional_paths[@]}"; do
  if [[ -e "$repo_root/$path" ]]; then
    runtime_paths+=("$path")
  fi
done
for path in "${runtime_paths[@]}"; do
  if [[ ! -e "$repo_root/$path" ]]; then
    printf 'ERROR: required runtime path missing: %s\\n' "$path" >&2
    exit 1
  fi
done

tar -cf - "${runtime_paths[@]}" | tar -xf - -C "$plugin"

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
php -r '
$archive = new ZipArchive();
if ($archive->open($argv[1], ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "ERROR: cannot create ZIP.\\n");
    exit(1);
}
$root = realpath($argv[2]);
$parent = dirname($root);
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
$archive->addEmptyDir(basename($root));
foreach ($files as $file) {
    $path = $file->getPathname();
    if ($file->isLink()) {
        fwrite(STDERR, "ERROR: symlink in production package.\\n");
        exit(1);
    }
    $relative = substr($path, strlen($parent) + 1);
    if ($file->isDir()) {
        $archive->addEmptyDir($relative);
    } else {
        $archive->addFile($path, $relative);
    }
}
if (!$archive->close()) {
    fwrite(STDERR, "ERROR: ZIP close failed.\\n");
    exit(1);
}
' "$out" "$plugin"
test -s "$out"
printf 'Production ZIP created: %s\n' "$out"
