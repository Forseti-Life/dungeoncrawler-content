#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# shellcheck source=lib/agents.sh
source "./scripts/lib/agents.sh"
# shellcheck source=lib/merge-health.sh
source "./scripts/lib/merge-health.sh"

now_iso() { date -Iseconds; }

pid_status() {
  local name="$1" pidfile="$2"
  local pid=""
  if [ -f "$pidfile" ]; then
    pid="$(cat "$pidfile" 2>/dev/null || true)"
  fi
  if [[ "$pid" =~ ^[0-9]+$ ]] && ps -p "$pid" >/dev/null 2>&1; then
    printf '%-18s %s (pid %s)\n' "$name:" "running" "$pid"
  else
    printf '%-18s %s\n' "$name:" "not running"
  fi
}

count_glob() {
  shopt -s nullglob
  local arr=( $1 )
  shopt -u nullglob
  echo "${#arr[@]}"
}

queue_counts() {
  shopt -s nullglob
  local files=(inbox/commands/*.md)
  shopt -u nullglob
  local ceo=0 pm=0
  for f in "${files[@]}"; do
    if grep -q '^\- pm:' "$f"; then
      pm=$((pm+1))
    else
      ceo=$((ceo+1))
    fi
  done
  echo "$ceo" "$pm" "${#files[@]}"
}

latest_mtime_epoch() {
  local path="$1"
  path="$(readlink -f "$path" 2>/dev/null || echo "$path")"
  if [ ! -e "$path" ]; then
    echo 0
    return
  fi
  # Latest mtime among files/dirs under path.
  local latest
  latest=$(find "$path" -mindepth 1 2>/dev/null | while IFS= read -r f; do stat -c '%Y' "$f" 2>/dev/null || echo 0; done | sort -n | tail -n 1 || true)
  if [ -z "$latest" ]; then
    echo 0
  else
    printf '%.0f\n' "$latest"
  fi
}

fmt_age() {
  local epoch="$1"
  if [ "$epoch" -le 0 ]; then
    echo "-"
    return
  fi
  local now
  now=$(date +%s)
  local delta=$((now-epoch))
  if [ "$delta" -lt 60 ]; then
    echo "${delta}s"
  elif [ "$delta" -lt 3600 ]; then
    echo "$((delta/60))m"
  elif [ "$delta" -lt 86400 ]; then
    echo "$((delta/3600))h"
  else
    echo "$((delta/86400))d"
  fi
}

agent_pending_inbox_count() {
  local agent="$1"
  local dir="sessions/${agent}/inbox"
  dir="$(readlink -f "$dir" 2>/dev/null || echo "$dir")"
  if [ ! -d "$dir" ]; then
    echo 0
    return
  fi
  find "$dir" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | wc -l | awk '{print $1}'
}

agent_next_inbox() {
  local agent="$1"
  local dir="sessions/${agent}/inbox"
  dir="$(readlink -f "$dir" 2>/dev/null || echo "$dir")"
  if [ ! -d "$dir" ]; then
    echo "-"
    return
  fi
  local next
  next=$(find "$dir" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | sed 's|.*/||' | sort | head -n 1 || true)
  echo "${next:--}"
}

agent_exec_status() {
  # Heuristic: if outbox updated in last 30 minutes, assume executing.
  local agent="$1"
  local out_epoch
  out_epoch=$(latest_mtime_epoch "sessions/${agent}/outbox")
  if [ "$out_epoch" -le 0 ]; then
    echo "no"
    return
  fi
  local now
  now=$(date +%s)
  local delta=$((now-out_epoch))
  if [ "$delta" -le 1800 ]; then
    echo "yes"
  else
    echo "no"
  fi
}

printf 'HQ status @ %s\n\n' "$(now_iso)"

printf '%-18s %s\n\n' "Org enabled:" "$(./scripts/is-org-enabled.sh 2>/dev/null || echo false)"

pid_status "CEO loop" ".ceo-inbox-loop.pid"
pid_status "Inbox loop" ".inbox-loop.pid"

pid_status "Orchestrator" ".orchestrator-loop.pid"
pid_status "Agent exec" ".agent-exec-loop.pid"
pid_status "CEO ops" ".ceo-ops-loop.pid"
pid_status "CEO health" ".ceo-health-loop.pid"
pid_status "Publisher loop" ".publish-forseti-agent-tracker-loop.pid"
pid_status "Checkpoint" ".auto-checkpoint-loop.pid"
pid_status "Improve round" ".improvement-round-loop.pid"

echo
read ceo pm total < <(queue_counts)
printf '%-18s %s\n' "Queue (CEO):" "$ceo"
printf '%-18s %s\n' "Queue (PM):" "$pm"
printf '%-18s %s\n' "Queue (total):" "$total"
printf '%-18s %s\n' "Processed:" "$(count_glob 'inbox/processed/*.md')"

blocked_count="$(./scripts/hq-blockers.sh count 2>/dev/null || echo 0)"
printf '%-18s %s\n' "Blocked:" "$blocked_count"

merge_health_scan "$ROOT_DIR"
printf '%-18s %s\n' "Merge health:" "$MERGE_HEALTH_SUMMARY"

echo
printf '%-26s %6s %6s %-26s %10s\n' "Agent" "Inbox" "Exec" "Next inbox" "Last act"
printf '%-26s %6s %6s %-26s %10s\n' "--------------------------" "------" "------" "--------------------------" "----------"

while IFS= read -r agent; do
  if is_paused "$agent"; then
    continue
  fi
  inbox_n="$(agent_pending_inbox_count "$agent")"
  exec="$(agent_exec_status "$agent")"
  next="$(agent_next_inbox "$agent")"

  inbox_epoch=$(latest_mtime_epoch "sessions/${agent}/inbox")
  out_epoch=$(latest_mtime_epoch "sessions/${agent}/outbox")
  art_epoch=$(latest_mtime_epoch "sessions/${agent}/artifacts")
  last_epoch=$inbox_epoch
  [ "$out_epoch" -gt "$last_epoch" ] && last_epoch=$out_epoch
  [ "$art_epoch" -gt "$last_epoch" ] && last_epoch=$art_epoch

  printf '%-26s %6s %6s %-26s %10s\n' "$agent" "$inbox_n" "$exec" "$next" "$(fmt_age "$last_epoch")"
done < <(configured_agent_ids)

# ── QA / security-analyst starvation detection ──────────────────────────────
# Emits WARN when any tester/security-analyst seat has inbox items older than
# 24 hours with no matching outbox file. Emits ERROR (and exits 1) when such
# a seat has 3+ unprocessed items.

_starvation_exit=0
_merge_exit=0

if [ "$MERGE_HEALTH_HAS_ISSUES" -eq 1 ]; then
  echo
  echo "── Merge issue details ──"
  while IFS= read -r detail; do
    [ -n "$detail" ] || continue
    printf 'ERROR [merge-health] %s\n' "$detail"
  done < <(merge_health_issue_lines 10)
  echo "ERROR [merge-health] Remediate: finish or abort any in-progress merge/rebase/cherry-pick/revert, then checkpoint or clean local tracked changes before the next merge/pull"
  _merge_exit=1
fi

_check_starvation() {
  local seat="$1"
  local inbox_dir="sessions/${seat}/inbox"
  local outbox_dir="sessions/${seat}/outbox"
  [ -d "$inbox_dir" ] || return 0

  local now
  now=$(date +%s)
  local threshold_secs=86400   # 24 hours
  local unprocessed=0
  local oldest_age_h=0
  local oldest_item=""

  while IFS= read -r item_dir; do
    local item
    item="$(basename "$item_dir")"
    # Skip if a matching outbox file exists (same date-prefix = first 8 chars).
    local date_prefix="${item:0:8}"
    if ls "$outbox_dir"/${date_prefix}*.md >/dev/null 2>&1; then
      continue
    fi
    # Derive age from folder name date prefix (YYYYMMDD) — more reliable than
    # mtime because orchestrator scripts update mtime when touching roi.txt etc.
    local item_epoch=0
    if [[ "$item" =~ ^([0-9]{4})([0-9]{2})([0-9]{2}) ]]; then
      item_epoch=$(date -d "${BASH_REMATCH[1]}-${BASH_REMATCH[2]}-${BASH_REMATCH[3]}" +%s 2>/dev/null || echo 0)
    fi
    if [ "$item_epoch" -le 0 ]; then
      item_epoch=$(stat -c '%Y' "$item_dir" 2>/dev/null || echo 0)
    fi
    local age_secs=$(( now - item_epoch ))
    if [ "$age_secs" -lt "$threshold_secs" ]; then
      continue
    fi
    unprocessed=$(( unprocessed + 1 ))
    local age_h=$(( age_secs / 3600 ))
    if [ "$age_h" -gt "$oldest_age_h" ]; then
      oldest_age_h=$age_h
      oldest_item="$item"
    fi
  done < <(find "$inbox_dir" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | sort)

  [ "$unprocessed" -eq 0 ] && return 0

  if [ "$unprocessed" -ge 3 ]; then
    printf 'ERROR [qa-starvation] %s: %d items, oldest %dh (%s) — QA/security possibly bypassed\n' \
      "$seat" "$unprocessed" "$oldest_age_h" "$oldest_item"
    _starvation_exit=1
  else
    printf 'WARN  [qa-starvation] %s: %d items, oldest %dh (%s) — check agent cap\n' \
      "$seat" "$unprocessed" "$oldest_age_h" "$oldest_item"
  fi
}

echo
echo "── QA/security starvation check ──"
while IFS=' ' read -r seat _role; do
  if is_paused "$seat" 2>/dev/null; then
    continue
  fi
  _check_starvation "$seat"
done < <(python3 - <<'PY'
import yaml, sys
with open("org-chart/agents/agents.yaml") as f:
    data = yaml.safe_load(f)
agents = data.get("agents", [])
for a in agents:
    if a.get("role") in ("tester", "security-analyst"):
        print(a["id"], a["role"])
PY
)

# ── Release efficiency analysis ──────────────────────────────────────────────
# Post-release retrospective: redundant dev passes, executor quarantine rate,
# CEO proxy load, shipping lag, code-review gate, and Gate R5 delay.
# Runs only when SKIP_RELEASE_EFFICIENCY is not set (default: runs always).

_efficiency_exit=0
if [ "${SKIP_RELEASE_EFFICIENCY:-0}" != "1" ]; then
  echo
  efficiency_out="$(python3 ./scripts/release-efficiency-analysis.py 2>/dev/null || true)"
  echo "$efficiency_out"
  if echo "$efficiency_out" | grep -q "^❌ FAIL"; then
    _efficiency_exit=1
  fi
fi

if [ "$_starvation_exit" -ne 0 ] || [ "$_merge_exit" -ne 0 ] || [ "$_efficiency_exit" -ne 0 ]; then
  exit 1
fi

exit 0
