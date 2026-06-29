#!/usr/bin/env bash
# Shared helper for organizational priority weighting.
# Intended to be sourced by HQ scripts (cwd should be repo root).

# shellcheck shell=bash

# Organizational priority weighting (from org-chart/priorities.yaml).
# Formula:
#   effective = base_roi + floor(base_roi * org_score / ORG_PRIORITY_DIVISOR)

: "${ORG_PRIORITY_DIVISOR:=100}"
: "${ORG_PRIORITY_BONUS_MAX:=100000}"

org_priority__load_tsv() {
  # Sets ORG_PRIORITIES_TSV to lines: "<key>\t<score>".
  # No-op if already set.
  if [ -n "${ORG_PRIORITIES_TSV:-}" ]; then
    return 0
  fi

  ORG_PRIORITIES_TSV="$(python3 - <<'PY' 2>/dev/null || true
import pathlib
try:
  import yaml  # type: ignore
except Exception:
  yaml = None

p = pathlib.Path('org-chart/priorities.yaml')
if not p.exists():
  raise SystemExit(0)

text = p.read_text(encoding='utf-8', errors='ignore')
pr = {}
if yaml:
  try:
    data = yaml.safe_load(text) or {}
    if isinstance(data, dict):
      pr = data.get('priorities') or {}
  except Exception:
    pr = {}
else:
  in_pr = False
  for ln in text.splitlines():
    s = ln.strip()
    if not s or s.startswith('#'):
      continue
    if s == 'priorities:':
      in_pr = True
      continue
    if not in_pr:
      continue
    if ln.startswith('  ') and ':' in ln:
      k, v = s.split(':', 1)
      try:
        pr[k.strip()] = int(v.strip())
      except Exception:
        pass

for k, v in (pr or {}).items():
  try:
    print(f"{str(k).strip()}\t{int(v)}")
  except Exception:
    pass
PY
)"
  export ORG_PRIORITIES_TSV
}

org_priority_key_for_item() {
  local item_dir="$1" item_name="$2"
  local key_file="$item_dir/org_priority.txt"
  local key=""

  if [ -f "$key_file" ]; then
    key="$(head -n 1 "$key_file" 2>/dev/null | tr -d '\r' | tr -d ' \t' || true)"
  fi
  if [ -n "$key" ]; then
    echo "$key"
    return 0
  fi

  # Heuristic inference from item id/name.
  local lower
  lower="$(printf '%s' "$item_name" | tr '[:upper:]' '[:lower:]')"
  if echo "$lower" | grep -qE 'agent-management|agent-tracker|copilot-agent-tracker|copilot_agent_tracker'; then
    echo "agent-management"; return 0
  fi
  if echo "$lower" | grep -qE 'jobhunter|job_hunter'; then
    echo "jobhunter"; return 0
  fi
  if echo "$lower" | grep -qE 'dungeoncrawler'; then
    echo "dungeoncrawler"; return 0
  fi

  echo ""
}

org_priority_score_for_key() {
  local key="$1"
  [ -n "$key" ] || { echo 0; return 0; }

  org_priority__load_tsv
  [ -n "${ORG_PRIORITIES_TSV:-}" ] || { echo 0; return 0; }

  printf '%s\n' "$ORG_PRIORITIES_TSV" | awk -F'\t' -v k="$key" '$1==k{print $2; exit}'
}

org_priority_bonus_for_item() {
  local item_dir="$1" item_name="$2" base_roi="$3"

  local key score
  key="$(org_priority_key_for_item "$item_dir" "$item_name")"
  score="$(org_priority_score_for_key "$key")"

  [[ "$score" =~ ^-?[0-9]+$ ]] || score=0
  [[ "$base_roi" =~ ^[0-9]+$ ]] || base_roi=1

  if [ "$score" -le 0 ] 2>/dev/null; then
    echo 0
    return 0
  fi

  if ! [[ "$ORG_PRIORITY_DIVISOR" =~ ^[0-9]+$ ]] || [ "$ORG_PRIORITY_DIVISOR" -le 0 ] 2>/dev/null; then
    echo 0
    return 0
  fi

  local bonus
  bonus=$((base_roi * score / ORG_PRIORITY_DIVISOR))

  if [[ "$ORG_PRIORITY_BONUS_MAX" =~ ^[0-9]+$ ]] && [ "$ORG_PRIORITY_BONUS_MAX" -ge 0 ] 2>/dev/null && [ "$bonus" -gt "$ORG_PRIORITY_BONUS_MAX" ] 2>/dev/null; then
    bonus="$ORG_PRIORITY_BONUS_MAX"
  fi

  echo "$bonus"
}
