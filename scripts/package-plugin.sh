#!/usr/bin/env bash
set -euo pipefail
repo=$(git rev-parse --show-toplevel)
cd "$repo"
# Local PHPUnit port overrides are never part of the ZIP; do not let them
# block packaging. All other tracked edits (staged or unstaged) still block.
if ! git diff --quiet -- . ':(exclude).wp-env.phpunit.json' ||
   ! git diff --cached --quiet -- . ':(exclude).wp-env.phpunit.json'; then
  echo "Refusing to package tracked uncommitted changes" >&2; exit 2
fi
command -v docker >/dev/null || { echo "Docker is required for Python packaging" >&2; exit 2; }
head=$(git rev-parse --short=12 HEAD)
mkdir -p dist
# One-shot container, no new Compose project, ports or persistent volumes.
manifest=$(mktemp "$repo/dist/.package-tracked.XXXXXX")
trap 'rm -f "$manifest"' EXIT
git ls-files -z > "$manifest"
manifest_name=$(basename "$manifest")
host_repo=$(cd "$repo" && pwd -W 2>/dev/null || pwd)
MSYS_NO_PATHCONV=1 docker run --rm --network none \
  --mount "type=bind,source=$host_repo,target=/workspace" \
  --workdir /workspace -i python:3.12-alpine \
  python - "$manifest_name" "$head" <<'PY'
import pathlib, sys, zipfile
repo = pathlib.Path("/workspace")
manifest_name, head = sys.argv[1:]
target = repo / "dist" / ("wordpress-event-manager-" + head + ".zip")
tracked = (repo / "dist" / manifest_name).read_bytes().split(b"\0")
roots = {"includes", "assets", "languages", "templates", "vendor"}
files = {"wordpress-event-manager.php", "readme.txt", "LICENSE", "license.txt"}
included = []
for item in tracked:
    if not item:
        continue
    path = pathlib.Path(item.decode())
    if path.parts[0] not in roots and str(path) not in files:
        continue
    if any(x in {"tests", "test", "node_modules", ".git", "bin"} for x in path.parts):
        continue
    if path.suffix in {".log", ".db", ".sqlite", ".zip"} or path.name.startswith(".env"):
        continue
    source = repo / path
    if source.is_file() and not source.is_symlink():
        included.append((path, source))
# Composer vendor is intentionally gitignored. Bundle only the installed
# production QR dependency tree plus Composer's generated autoloader.
# Never include vendor/bin, test suites or unrelated development packages.
runtime_roots = (
    pathlib.Path("vendor/composer"),
    pathlib.Path("vendor/chillerlan/php-qrcode"),
    pathlib.Path("vendor/chillerlan/php-settings-container"),
)
vendor = repo / "vendor"
if not (vendor / "autoload.php").is_file():
    raise SystemExit("ERROR: vendor/autoload.php missing; run Composer install first")
runtime_files = [pathlib.Path("vendor/autoload.php")]
for relative_root in runtime_roots:
    root = repo / relative_root
    if not root.is_dir():
        raise SystemExit("ERROR: required Composer runtime missing: " + str(relative_root))
    runtime_files.extend(p.relative_to(repo) for p in root.rglob("*") if p.is_file())
for path in runtime_files:
    source = repo / path
    if source.is_symlink() or any(parent.is_symlink() for parent in source.parents if parent != repo):
        raise SystemExit("ERROR: symlink in Composer runtime: " + str(path))
    if any(x in {"tests", "test", "bin", ".git"} for x in path.parts):
        continue
    if path.suffix in {".log", ".zip", ".sqlite", ".db"}:
        continue
    included.append((path, source))
assert any(str(p) == "wordpress-event-manager.php" for p, _ in included)
included = list(dict(included).items())
with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as archive:
    for path, source in sorted(included):
        info = zipfile.ZipInfo("wordpress-event-manager/" + path.as_posix(), (2026, 10, 21, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o644 << 16
        archive.writestr(info, source.read_bytes())
print("ZIP:", target)
print("Runtime files:", len(included))
PY
