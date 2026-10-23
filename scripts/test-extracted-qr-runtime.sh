#!/usr/bin/env bash
set -euo pipefail
# Real packaged-runtime probe: extracted ZIP must load QR independently from checkout vendor.
repo=$(git rev-parse --show-toplevel)
cd "$repo"
bash scripts/test-package-qr-runtime.sh
head=$(git rev-parse --short=12 HEAD)
zipfile="dist/wordpress-event-manager-$head.zip"
test -f "$zipfile"
target=$(mktemp -d "$repo/dist/.qr-package-probe.XXXXXX")
trap 'rm -rf -- "$target"' EXIT
host_repo=$(cd "$repo" && pwd -W 2>/dev/null || pwd)
# Extract with the same cached one-shot Python image; no local Python required.
MSYS_NO_PATHCONV=1 docker run --rm --network none \
  --mount "type=bind,source=$host_repo,target=/workspace" \
  --workdir /workspace -i python:3.12-alpine python - "$head" "$(basename "$target")" <<'PY'
import pathlib,sys,zipfile
head,directory=sys.argv[1:]
with zipfile.ZipFile('/workspace/dist/wordpress-event-manager-'+head+'.zip') as z:
    z.extractall('/workspace/dist/'+directory)
PY
# wp-env already mounts this checkout; execution is from the ZIP extraction only.
plugin_dir=$(basename "$repo")
relative="dist/$(basename "$target")/wordpress-event-manager"
WEM_QR_EXTRACT="$relative" wp-env run cli --config=.wp-env.phpunit.json \
  --env-cwd="wp-content/plugins/$plugin_dir" \
  php -r '
    $root = getenv("WEM_QR_EXTRACT");
    if (!$root) { $root = $argv[1]; }
    require $root . "/vendor/autoload.php";
    require $root . "/includes/class-local-qr-encoder.php";
    $svg = (new WEM_Local_QR_Encoder())->render_svg("https://example.invalid/?wem_preissued=" . str_repeat("A",43));
    if (!str_contains($svg,"<svg") || !str_contains($svg,"</svg>")) { exit(1); }
    echo "EXTRACTED QR RUNTIME: GREEN\\n";
  ' "$relative"
