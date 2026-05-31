#!/usr/bin/env bash
set -euo pipefail

# Background loop to execute agent inbox items.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

configured_agent_ids() {
  python3 - <<'PY'
import re
from pathlib import Path
p = Path('org-chart/agents/agents.yaml')
if not p.exists():
    raise SystemExit(0)
for ln in p.read_text(encoding='utf-8', errors='ignore').splitlines():
    m = re.match(r'^\s*-\s+id:\s*(\S+)\s*$', ln)
    if m:
        print(m.group(1))
PY
}

role_for_agent() {
  local agent_id="$1"
  python3 - "$agent_id" <<'PY'
import sys, re, pathlib
agent_id = sys.argv[1]
text = pathlib.Path('org-chart/agents/agents.yaml').read_text(encoding='utf-8', errors='ignore').splitlines()
in_item = False
role = ''
for line in text:
    m = re.match(r'^\s*-\s+id:\s*(.+)\s*$', line)
    if m:
        in_item = (m.group(1).strip() == agent_id)
        continue
    if in_item:
        m = re.match(r'^\s*role:\s*(.+)\s*$', line)
        if m:
            role = m.group(1).strip()
            break
print(role)
PY
}

agent_level_weight() {
  # Higher number = higher scheduling priority.
  local role="$1"
  case "$role" in
    ceo) echo 500 ;;
    product-manager) echo 400 ;;
    business-analyst) echo 300 ;;
    software-developer) echo 200 ;;
    tester) echo 150 ;;
    security-analyst) echo 100 ;;
    *) echo 100 ;;
  esac
}

agent_inbox_count() {
  local agent="$1"
  local dir="sessions/${agent}/inbox"
  dir="$(readlink -f "$dir" 2>/dev/null || echo "$dir")"
  [ -d "$dir" ] || { echo 0; return; }
  find "$dir" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | wc -l | awk '{print $1}'
}

# Organizational priority weighting (shared helper).
if [ -f "$ROOT_DIR/scripts/lib/org-priority.sh" ]; then
  # shellcheck source=/dev/null
  . "$ROOT_DIR/scripts/lib/org-priority.sh"
fi

item_effective_roi() {
  local item_dir="$1" item_name="$2"
  local roi_file="$item_dir/roi.txt"
  local roi="1"

  if [ -f "$roi_file" ]; then
    roi="$(head -n 1 "$roi_file" 2>/dev/null | tr -d '\r' | tr -cd '0-9' || echo 0)"
  else
    if [[ "$item_name" =~ (^|-)roi-([0-9]{1,9})(-|$) ]]; then
      roi="${BASH_REMATCH[2]}"
    fi
  fi
  [[ "$roi" =~ ^[0-9]+$ ]] || roi=1
  [ "$roi" -ge 1 ] 2>/dev/null || roi=1

  ROI_MAX="${AGENT_EXEC_ROI_MAX:-10000}"
  if [[ "$ROI_MAX" =~ ^[0-9]+$ ]] && [ "$ROI_MAX" -ge 1 ] 2>/dev/null && [ "$roi" -gt "$ROI_MAX" ] 2>/dev/null; then
    roi="$ROI_MAX"
  fi

  local org_bonus
  org_bonus="$(org_priority_bonus_for_item "$item_dir" "$item_name" "$roi")"
  [[ "$org_bonus" =~ ^[0-9]+$ ]] || org_bonus=0

  echo $((roi + org_bonus))
}

agent_top_effective_roi() {
  local agent="$1"
  local dir="sessions/${agent}/inbox"
  dir="$(readlink -f "$dir" 2>/dev/null || echo "$dir")"
  [ -d "$dir" ] || { echo 0; return; }

  local top=0
  while IFS= read -r name; do
    [ -n "$name" ] || continue
    local item_dir="$dir/$name"
    [ -d "$item_dir" ] || continue
    local roi
    roi="$(item_effective_roi "$item_dir" "$name")"
    [[ "$roi" =~ ^[0-9]+$ ]] || roi=0
    if [ "$roi" -gt "$top" ] 2>/dev/null; then
      top="$roi"
    fi
  done < <(find "$dir" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | sed 's|.*/||' || true)

  echo "$top"
}

prioritized_non_ceo_agents() {
  # Output: agent ids in priority order.
  # Sort by: has inbox items desc, agent level desc, top effective ROI desc, agent id asc.
  while IFS= read -r agent; do
    [ -n "$agent" ] || continue
    case "$agent" in
      ceo-copilot|ceo-copilot-2|ceo-copilot-3) continue ;;
    esac
    [ -d "sessions/${agent}" ] || continue
    if [ "$(./scripts/is-agent-paused.sh "$agent")" = "true" ]; then
      continue
    fi

    local role level inbox_n has_inbox top_roi
    role="$(role_for_agent "$agent")"
    level="$(agent_level_weight "$role")"
    inbox_n="$(agent_inbox_count "$agent")"
    has_inbox=0
    if [[ "$inbox_n" =~ ^[0-9]+$ ]] && [ "$inbox_n" -gt 0 ] 2>/dev/null; then
      has_inbox=1
    fi
    if [ "$has_inbox" -ne 1 ]; then
      continue
    fi
    top_roi=0
    top_roi="$(agent_top_effective_roi "$agent")"
    [[ "$top_roi" =~ ^[0-9]+$ ]] || top_roi=0
    [[ "$level" =~ ^[0-9]+$ ]] || level=0
    printf '%s\t%s\t%s\t%s\n' "$has_inbox" "$level" "$top_roi" "$agent"
  done < <(configured_agent_ids)
}

PIDFILE=".agent-exec-loop.pid"
LOCKFILE="tmp/.agent-exec-loop.lock"
LOGDIR="inbox/responses"
LATEST="$LOGDIR/agent-exec-latest.log"
mkdir -p "$LOGDIR"

cmd="${1:-start}"
interval="${2:-60}"

read_pid() {
  [ -f "$PIDFILE" ] || { echo ""; return; }
  pid="$(cat "$PIDFILE" 2>/dev/null || true)"
  [[ "$pid" =~ ^[0-9]+$ ]] && echo "$pid" || echo ""
}

is_running() {
  pid="$(read_pid)"
  [ -n "$pid" ] && ps -p "$pid" >/dev/null 2>&1
}

case "$cmd" in
  start)
    # Single-instance guard: if another loop already holds the lock, do not
    # spawn a redundant background process (prevents duplicate agent runs).
    if command -v flock >/dev/null 2>&1; then
      mkdir -p "$(dirname "$LOCKFILE")" 2>/dev/null || true
      exec 8>"$LOCKFILE" || true
      if ! flock -n 8 2>/dev/null; then
        echo "Already running (lock held: $LOCKFILE)"
        exit 0
      fi
      flock -u 8 2>/dev/null || true
    fi
    if is_running; then
      echo "Already running (pid $(read_pid))"
      exit 0
    fi
    setsid bash -c "'$0' run '$interval'" >/dev/null 2>&1 &
    pid=$!
    echo "$pid" > "$PIDFILE"
    echo "Started (pid $pid)"
    echo "To stop: send SIGTERM to pid $pid"
    ;;

  status)
    if is_running; then
      echo "running (pid $(read_pid))"
    else
      echo "not running"
    fi
    ;;

  verify)
    if is_running; then
      echo "ok (running pid $(read_pid))"
      exit 0
    fi
    echo "ERROR: agent exec loop not running"
    exit 1
    ;;

  stop)
    pid="$(read_pid)"
    if [ -n "$pid" ] && ps -p "$pid" >/dev/null 2>&1; then
      kill "$pid" >/dev/null 2>&1 || true
      sleep 0.2
      ps -p "$pid" >/dev/null 2>&1 && kill -9 "$pid" >/dev/null 2>&1 || true
      echo "Stopped (pid $pid)"
      exit 0
    fi
    echo "Not running"
    ;;

  run)
    # Single-instance guard (definitive): keep lock held for the life of the loop.
    mkdir -p "$(dirname "$LOCKFILE")" 2>/dev/null || true
    if command -v flock >/dev/null 2>&1; then
      exec 9>"$LOCKFILE" || exit 1
      if ! flock -n 9 2>/dev/null; then
        echo "Another agent-exec-loop is already running (lock: $LOCKFILE)" >&2
        exit 0
      fi
    fi

    echo $$ > "$PIDFILE"
    tmpdir=""
    trap 'rm -rf "${tmpdir:-}" 2>/dev/null || true' EXIT
    while true; do
      if [ "$(./scripts/is-org-enabled.sh 2>/dev/null || echo false)" != "true" ]; then
        sleep "$interval"
        continue
      fi
      ts="$(date -Iseconds)"
      daylog="$LOGDIR/agent-exec-$(date +%Y%m%d).log"

      # Best-effort: never block execution.
      # Pull new replies into HQ queues before executing seats.
      ./scripts/consume-forseti-replies.sh >/dev/null 2>&1 || true

      any=0

      # Run CEO worker threads concurrently (shared inbox queue; lock-protected).
      tmpdir="$(mktemp -d 2>/dev/null || echo '')"
      if [ -n "$tmpdir" ]; then
        ceo_agents=(ceo-copilot ceo-copilot-2 ceo-copilot-3)
        pids=()
        for agent in "${ceo_agents[@]}"; do
          [ -d "sessions/${agent}" ] || continue
          if [ "$(./scripts/is-agent-paused.sh "$agent")" = "true" ]; then
            continue
          fi
          (
            out="$(./scripts/agent-exec-next.sh "$agent" 2>&1)" || rc=$?
            rc="${rc:-0}"
            printf '%s' "$rc" > "${tmpdir}/${agent}.rc" 2>/dev/null || true
            printf '%s' "$out" > "${tmpdir}/${agent}.out" 2>/dev/null || true
          ) &
          pids+=("$!")
        done

        for pid in "${pids[@]}"; do
          wait "$pid" 2>/dev/null || true
        done

        for agent in "${ceo_agents[@]}"; do
          [ -f "${tmpdir}/${agent}.rc" ] || continue
          rc="$(cat "${tmpdir}/${agent}.rc" 2>/dev/null || echo 0)"
          out="$(cat "${tmpdir}/${agent}.out" 2>/dev/null || true)"
          # Normalize multi-line output into a single line for logs.
          out_line="$(printf '%s' "$out" | tr '\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/[[:space:]]+$//')"

          if [ "$rc" -eq 0 ]; then
            any=1
            echo "[$ts] $out_line" | tee -a "$daylog" > "$LATEST"
          elif [ "$rc" -ne 2 ]; then
            echo "[$ts] ERROR($rc) $agent: $out_line" | tee -a "$daylog" > "$LATEST"
          fi
        done

        rm -rf "$tmpdir" 2>/dev/null || true
      fi

      mapfile -t agent_lines < <(prioritized_non_ceo_agents | sort -t $'\t' -k1,1nr -k2,2nr -k3,3nr -k4,4)
      for line in "${agent_lines[@]}"; do
        agent="$(printf '%s' "$line" | awk -F'\t' '{print $4}')"
        [ -n "$agent" ] || continue
        if out=$(./scripts/agent-exec-next.sh "$agent" 2>&1); then
          rc=$?
          if [ "$rc" -eq 0 ]; then
            any=1
            echo "[$ts] $out" | tee -a "$daylog" > "$LATEST"
            # Extract processed inbox item name and run gate-transition routing.
            item_name="$(printf '%s' "$out" | grep -oE 'processed [^ ]+' | awk '{print $2}' | head -1 || true)"
            ./scripts/route-gate-transitions.sh "$agent" "$item_name" 2>/dev/null || true
          fi
        else
          rc=$?
          if [ "$rc" -ne 2 ]; then
            echo "[$ts] ERROR($rc) $agent: $out" | tee -a "$daylog" > "$LATEST"
          fi
        fi
      done

      if [ "$any" -eq 0 ]; then
        echo "[$ts] idle (no inbox items)" | tee -a "$daylog" > "$LATEST"
      fi

      # NOTE: Idle work generation is disabled (Board directive 2026-02-22).

      # Publish current state to Drupal (rate-limited).
      publish_stamp="tmp/.last-publish-agent-tracker.epoch"
      now_epoch="$(date +%s)"
      last_epoch=0
      if [ -f "$publish_stamp" ]; then
        last_epoch="$(cat "$publish_stamp" 2>/dev/null || echo 0)"
      fi
      if [[ "$last_epoch" =~ ^[0-9]+$ ]] && [ $((now_epoch - last_epoch)) -ge 120 ]; then
        echo "$now_epoch" > "$publish_stamp" 2>/dev/null || true
        ./scripts/publish-forseti-agent-tracker.sh >/dev/null 2>&1 || true
      fi

      sleep "$interval"
    done
    ;;

  *)
    echo "Usage: $0 start|stop|status|verify [interval_seconds]" >&2
    exit 1
    ;;
esac
