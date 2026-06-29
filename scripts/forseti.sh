#!/usr/bin/env bash
set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

help() {
  cat <<'H'
Forseti (CEO) interactive

Commands:
  status                         Show HQ status
  blockers                       Show blocked/needs-info summary
  improve [YYYYMMDD]             Create PM+CEO post-release process review items
  improve-status [YYYYMMDD]      Show PM+CEO review completion stats
  watch [seconds]                Live dashboard refresh
  responses                      Tail CEO responses
  health                         Tail CEO health loop
  exec                           Tail agent executor loop
  help                           This help
  exit                           Quit
H
}

help
while true; do
  read -r -p "forseti> " cmd args || echo
  case "$cmd" in
    exit|quit) exit 0 ;;
    help|:help) help ;;
    status) ./scripts/hq-status.sh ;;
    blockers) ./scripts/hq-blockers.sh | head -n 120 ;;
    improve) ./scripts/improvement-round.sh ${args:-} ;;
    improve-status) ./scripts/improvement-round-status.sh ${args:-} ;;
    watch) ./scripts/hq-watch.sh ${args:-2} ;;
    responses) tail -f inbox/responses/latest.log ;;
    health) tail -f inbox/responses/ceo-health-latest.log ;;
    exec) tail -f inbox/responses/agent-exec-latest.log ;;
    *) echo "Unknown: $cmd"; help ;;
  esac
done
