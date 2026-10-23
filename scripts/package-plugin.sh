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
command -v python3 >/dev/null || { echo "Python 3 is required for packaging" >&2; exit 2; }
head=$(git rev-parse --short=12 HEAD)
mkdir -p dist
python3 - "$repo" "$repo/dist/wordpress-event-manager-$head.zip" <<'PY'
import pathlib, subprocess, sys, zipfile
repo, target = map(pathlib.Path, sys.argv[1:])
tracked = subprocess.check_output(["git", "ls-files", "-z"]).split(b"\0")
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
assert any(str(p) == "wordpress-event-manager.php" for p, _ in included)
with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as archive:
    for path, source in sorted(included):
        info = zipfile.ZipInfo("wordpress-event-manager/" + path.as_posix(), (2026, 10, 21, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o644 << 16
        archive.writestr(info, source.read_bytes())
print("ZIP:", target)
print("Runtime files:", len(included))
PY
