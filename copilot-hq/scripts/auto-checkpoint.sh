#!/usr/bin/env bash
set -euo pipefail

# Auto checkpoint commit + push for multiple repos.
# Only commits when there are changes.

if [ -n "${AUTO_CHECKPOINT_REPOS:-}" ]; then
  mapfile -t REPOS <<<"${AUTO_CHECKPOINT_REPOS}"
else
  REPOS=(
    "/home/ubuntu/forseti.life"
    "/home/ubuntu/forseti.life/copilot-hq"
  )
fi

ISO="$(date -Iseconds)"
LOCKFILE="${AUTO_CHECKPOINT_LOCKFILE:-/tmp/forseti-auto-checkpoint.lock}"

exec 9>"$LOCKFILE"
if ! flock -n 9; then
  echo "[$ISO] SKIP (auto-checkpoint already running): $LOCKFILE"
  exit 0
fi

is_dirty() {
  git --no-pager status --porcelain=v1 | grep -q .
}

denylist_present() {
  # Basic safety net to avoid accidentally committing secrets/local settings.
  git --no-pager status --porcelain=v1 | awk '{print $2}' | grep -E -q '(^|/)(settings\.php|settings\.local\.php|services\.local\.yml)$|(^|/)\.env($|\.)|\.(pem|key)$'
}

too_many_changes() {
  local n
  n=$(git --no-pager status --porcelain=v1 | wc -l | awk '{print $1}')
  local max
  max="${AUTO_CHECKPOINT_MAX_CHANGES:-5000}"
  [ "$n" -gt "$max" ]
}

has_volatile_latest_pointers() {
  # Avoid committing moving symlinks like sessions/**/artifacts/**/latest.
  # These are operational conveniences, not audit trail.
  git --no-pager status --porcelain=v1 | awk '{print $2}' | grep -E -q '^(sessions/[^/]+/artifacts/.*/latest(\..*)?)$'
}

for repo in "${REPOS[@]}"; do
  if [ ! -d "$repo/.git" ]; then
    echo "[$ISO] SKIP (not a git repo): $repo"
    continue
  fi

  cd "$repo"

  if ! is_dirty; then
    echo "[$ISO] CLEAN: $repo"
    continue
  fi

  if [ -e "$repo/.git/index.lock" ]; then
    echo "[$ISO] BLOCKED (git index.lock present): $repo"
    continue
  fi

  if denylist_present; then
    echo "[$ISO] BLOCKED (denylist match): $repo"
    continue
  fi

  if too_many_changes; then
    echo "[$ISO] BLOCKED (too many changes; adjust AUTO_CHECKPOINT_MAX_CHANGES if intentional): $repo"
    continue
  fi

  if has_volatile_latest_pointers; then
    echo "[$ISO] BLOCKED (volatile artifacts/latest pointer present): $repo"
    continue
  fi

  git add -A

  # If add resulted in nothing (rare), skip.
  if git diff --cached --quiet; then
    echo "[$ISO] CLEAN(after add): $repo"
    continue
  fi

  msg="Auto checkpoint: $ISO"
  git commit -m "$msg" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>" -q
  git push -q

  echo "[$ISO] PUSHED: $repo"
done
