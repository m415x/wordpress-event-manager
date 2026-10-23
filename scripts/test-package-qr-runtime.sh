#!/usr/bin/env bash
set -euo pipefail
# Packaging contract: local QR runtime must be included in the distributable,
# not merely installed in an ignored working-tree vendor directory.
bash scripts/package-plugin.sh
zip_file=$(find dist -maxdepth 1 -type f -name 'wordpress-event-manager-*.zip' -printf '%T@ %p\n' | sort -nr | head -1 | cut -d' ' -f2-)
test -n "$zip_file"
host_repo=$(pwd -W 2>/dev/null || pwd)
MSYS_NO_PATHCONV=1 docker run --rm --network none \
  --mount "type=bind,source=$host_repo,target=/workspace,readonly" \
  --workdir /workspace -i python:3.12-alpine \
  python - "$zip_file" <<'PY'
import sys,zipfile
z=zipfile.ZipFile(sys.argv[1])
names=set(z.namelist())
base="wordpress-event-manager/"
required=[
    base+"vendor/autoload.php",
    base+"vendor/composer/installed.php",
    base+"vendor/chillerlan/php-qrcode/src/QRCode.php",
    base+"includes/class-local-qr-encoder.php"
]
missing=[p for p in required if p not in names]
if missing:
    raise SystemExit("RED: packaged QR runtime missing: "+", ".join(missing))
print("PACKAGED QR RUNTIME: GREEN")
PY
