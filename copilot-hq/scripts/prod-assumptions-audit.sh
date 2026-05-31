#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEFAULT_HQ_DIR="${HQ_DEPLOY_DIR:-${REPO_DEPLOY_DIR:-$HOME/forseti.life}/copilot-hq}"
HQ_DIR="${1:-$DEFAULT_HQ_DIR}"

find_hq_dir() {
  if [ -d "$HQ_DIR" ]; then
    printf '%s\n' "$HQ_DIR"
    return 0
  fi

  for d in \
    "${HQ_DEPLOY_DIR:-}" \
    "${REPO_DEPLOY_DIR:-$HOME/forseti.life}/copilot-hq" \
    "$HOME/forseti.life/copilot-hq" \
    "$HOME/copilot-sessions-hq" \
    "/var/www/html/copilot-sessions-hq" \
    "/opt/copilot-sessions-hq"
  do
    [ -n "$d" ] || continue
    if [ -d "$d" ]; then
      printf '%s\n' "$d"
      return 0
    fi
  done

  found="$(find /home -maxdepth 3 -type d -name 'copilot-sessions-hq' 2>/dev/null | head -1 || true)"
  if [ -n "$found" ]; then
    printf '%s\n' "$found"
    return 0
  fi

  return 1
}

if ! HQ_DIR="$(find_hq_dir)"; then
  echo "ERROR: could not locate copilot-hq runtime directory on this host" >&2
  exit 1
fi

echo "=== Host context ==="
date -Iseconds
echo "user=$(whoami)"
echo "host=$(hostname -f 2>/dev/null || hostname)"
echo "pwd=$PWD"
echo "HQ_DIR=$HQ_DIR"

echo
echo "=== Web roots ==="
ls -ld /var/www/html /var/www/html/forseti /var/www/html/dungeoncrawler 2>/dev/null || true

echo
echo "=== HQ dir ownership/permissions ==="
ls -ld "$HQ_DIR" "$HQ_DIR/scripts" "$HQ_DIR/sessions" "$HQ_DIR/orchestrator" "$HQ_DIR/tmp" 2>/dev/null || true

echo
echo "=== Git working copy checks ==="
if [ -d "$HQ_DIR/.git" ] || [ -f "$HQ_DIR/.git" ]; then
  echo ".git present in HQ_DIR"
  git -C "$HQ_DIR" rev-parse --is-inside-work-tree 2>/dev/null || true
  git -C "$HQ_DIR" remote -v 2>/dev/null || true
  git -C "$HQ_DIR" status --short 2>/dev/null | head -20 || true
else
  echo ".git NOT present in HQ_DIR (deploy copy mode)"
fi

echo
echo "=== Runtime path assumptions ==="
if [ -x "$HQ_DIR/scripts/hq-automation.sh" ]; then
  "$HQ_DIR/scripts/hq-automation.sh" status || true
else
  echo "hq-automation.sh missing"
fi

if [ -x "$HQ_DIR/scripts/org-control.sh" ]; then
  "$HQ_DIR/scripts/org-control.sh" status --one-line || true
fi

if [ -x "$HQ_DIR/scripts/verify-hq-runtime.sh" ]; then
  "$HQ_DIR/scripts/verify-hq-runtime.sh" --strict || true
fi

echo
echo "=== Release-cycle state files ==="
ls -la "$HQ_DIR/tmp/release-cycle-active" 2>/dev/null || echo "no $HQ_DIR/tmp/release-cycle-active"
[ -f "$HQ_DIR/tmp/release-cycle-control.json" ] && cat "$HQ_DIR/tmp/release-cycle-control.json" || echo "no $HQ_DIR/tmp/release-cycle-control.json"
[ -f /var/tmp/copilot-sessions-hq/release-cycle-control.json ] && {
  echo "--- /var/tmp control ---"
  cat /var/tmp/copilot-sessions-hq/release-cycle-control.json
} || true

echo
echo "=== Active cron for this user ==="
crontab -l 2>/dev/null || echo "no user crontab"

echo
echo "=== Recent runtime logs ==="
ls -lt "$HQ_DIR/inbox/responses" 2>/dev/null | head -20 || true
tail -n 20 "$HQ_DIR/inbox/responses/orchestrator-latest.log" 2>/dev/null || true
tail -n 20 "$HQ_DIR/inbox/responses/hq-automation-watchdog.log" 2>/dev/null || true

echo
echo "DONE: production assumptions audit complete"
