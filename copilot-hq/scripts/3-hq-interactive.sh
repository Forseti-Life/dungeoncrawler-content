#!/usr/bin/env bash
set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

help() {
  cat <<'H'
HQ interactive

Commands:
  status                       Show HQ status
  watch [seconds]              Live dashboard refresh
  responses                    Tail CEO responses
  inbox-responses              Tail inbox-loop responses
  agent <id>                   Show agent inbox/outbox/artifacts listing
  tail <agent> <inbox|outbox>  Tail newest file in that agent folder (best-effort)
  queue <wi> <topic> <text>    Enqueue CEO command
  dispatch                     Run one CEO dispatch immediately
  help                         This help
  exit                         Quit
H
}

newest_file() {
  local dir="$1"
  find "$dir" -type f 2>/dev/null | while IFS= read -r f; do printf '%s %s\n' "$(stat -c '%Y' "$f" 2>/dev/null || echo 0)" "$f"; done | sort -n | tail -n 1 | awk '{print $2}'
}

help
while true; do
  read -r -p "hq> " cmd args || echo
  line="$cmd ${args:-}"
  line="${line% }"

  case "$cmd" in
    exit|quit) exit 0 ;;
    help|:help) help ;;
    status) ./scripts/hq-status.sh ;;
    watch) ./scripts/hq-watch.sh ${args:-2} ;;
    responses) tail -f inbox/responses/latest.log ;;
    inbox-responses) tail -f inbox/responses/inbox-loop-latest.log ;;
    agent)
      agent_id="${args:-}"
      [ -n "$agent_id" ] || { echo "agent id required"; continue; }
      for sub in inbox outbox artifacts; do
        echo "== $agent_id/$sub =="
        ls -la "sessions/$agent_id/$sub" 2>/dev/null || echo "(missing)"
        echo
      done
      ;;
    tail)
      agent_id="$(echo "${args:-}" | awk '{print $1}')"
      which_dir="$(echo "${args:-}" | awk '{print $2}')"
      [ -n "$agent_id" ] && [ -n "$which_dir" ] || { echo "usage: tail <agent> <inbox|outbox|artifacts>"; continue; }
      dir="sessions/$agent_id/$which_dir"
      f="$(newest_file "$dir")"
      if [ -z "$f" ]; then
        echo "No files in $dir"
      else
        echo "== tail $f =="
        tail -n 80 "$f" || true
      fi
      ;;
    queue)
      wi="$(echo "${args:-}" | awk '{print $1}')"
      topic="$(echo "${args:-}" | awk '{print $2}')"
      text="${args#* * }"
      text="${text#* }"
      if [ -z "$wi" ] || [ -z "$topic" ] || [ -z "$text" ]; then
        echo "usage: queue <wi> <topic> <text>"; continue
      fi
      ./scripts/ceo-queue.sh "$wi" "$topic" "$text" >/dev/null
      echo "Queued."
      ;;
    dispatch)
      ./scripts/ceo-dispatch-next.sh || true
      ;;
    *)
      echo "Unknown: $cmd"; echo; help
      ;;
  esac
done
