#!/usr/bin/env bash
# WEM-74 closure: read-only Docker/wp-env ownership and evidence inventory.
# No stops, deletions, pruning, or test reruns.
set -euo pipefail
root=${1:-"$HOME"}
out=${2:-"$HOME/WEM74-CLEANUP-INVENTORY-$(date +%Y%m%d-%H%M%S)"}
mkdir -p "$out"
printf 'ROOT_SEARCH=%s\nEVIDENCE_DIR=%s\n' "$root" "$out" > "$out/manifest.txt"
docker ps -a --no-trunc --format '{{.ID}} {{.Names}}' > "$out/docker-container-index.txt"
docker network ls --no-trunc > "$out/docker-networks.txt"
docker volume ls > "$out/docker-volumes.txt"
docker ps -a --no-trunc --format '{{.ID}} {{.Names}}' |
while read -r id name; do
  case "$name" in
    *wem74-isolated-*|*wem74-fk-disposable-*|*wem74-gate-disposable-*|*wem73-custody-*|*phpunit-*)
      echo "$id $name" >> "$out/selected-containers.txt"
      docker inspect "$id" >> "$out/selected-container-inspect.jsonl"
      printf '\n===== %s =====\n' "$name" >> "$out/resources.txt"
      docker inspect --format 'ID={{.Id}} NAME={{.Name}} STATUS={{.State.Status}} PROJECT={{index .Config.Labels "com.docker.compose.project"}} CONFIG_FILES={{index .Config.Labels "com.docker.compose.project.config_files"}} WORKDIR={{index .Config.Labels "com.docker.compose.project.working_dir"}} PORTS={{json .NetworkSettings.Ports}} NETWORKS={{json .NetworkSettings.Networks}} MOUNTS={{json .Mounts}}' "$id" >> "$out/resources.txt"
    ;;
  esac
done
# The roots are supplied by the operator; do not search the entire filesystem.
for dir in "$root"/WEM74-* "$root"/wem74-*; do
  [[ -d "$dir" ]] || continue
  printf '\n===== ROOT %s =====\n' "$dir" >> "$out/owned-roots.txt"
  find "$dir" -maxdepth 2 -type f \( -name 'manifest.log' -o -name 'unit.log' -o -name 'integration.log' -o -name '*sql.log' -o -name 'fk-probe.log' -o -name 'start.log' -o -name 'activation.log' -o -name '.wp-env.*json' -o -name 'status.log' \) -print >> "$out/owned-roots.txt"
  for file in "$dir"/manifest.log "$dir"/initial-sql.log "$dir"/final-sql.log "$dir"/unit.log "$dir"/integration.log "$dir"/fk-probe.log; do
    if [[ -f "$file" ]]; then
      sha256sum "$file" >> "$out/evidence-sha256.txt"
    fi
  done
done
echo "READ-ONLY INVENTORY: $out"
echo 'Inspect selected-containers.txt, resources.txt, owned-roots.txt, evidence-sha256.txt, docker-networks.txt and docker-volumes.txt.'
echo 'NO Docker or wp-env resources were modified.'
