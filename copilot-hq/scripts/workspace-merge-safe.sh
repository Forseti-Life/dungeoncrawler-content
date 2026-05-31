#!/usr/bin/env bash
# workspace-merge-safe.sh — Pre-merge artifact backup hook for workspace snapshot merges.
#
# Usage:
#   ./scripts/workspace-merge-safe.sh <branch-or-commit> [--dry-run]
#
# What it does:
#   1. Creates a timestamped backup of sessions/ to /tmp/workspace-merge-backup-<ts>/
#   2. Records all current inbox/outbox paths in the backup manifest
#   3. Executes: git merge --no-edit <branch-or-commit>
#   4. Post-merge: compares sessions/ against pre-merge state; warns on any deletions
#
# If --dry-run is passed: performs backup and integrity check but does NOT run git merge.
#
# Exit codes:
#   0 — merge completed and post-merge check passed (or --dry-run)
#   1 — merge aborted (backup failed, or merge conflict/error)
#   2 — merge completed but post-merge integrity check found deletions (WARNING state)
#
# Owner: dev-infra
# See: runbooks/orchestration.md § Pre-merge safety gate

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# ---- args ----------------------------------------------------------------
MERGE_TARGET="${1:-}"
DRY_RUN=0
if [ "${2:-}" = "--dry-run" ] || [ "${1:-}" = "--dry-run" ]; then
  DRY_RUN=1
  # Allow invocation with just --dry-run (no target — useful for pre-merge check only)
  [ "${1:-}" = "--dry-run" ] && MERGE_TARGET=""
fi

if [ -z "$MERGE_TARGET" ] && [ "$DRY_RUN" -eq 0 ]; then
  echo "Usage: $0 <branch-or-commit> [--dry-run]" >&2
  exit 1
fi

# ---- backup --------------------------------------------------------------
TS="$(date +%Y%m%dT%H%M%S)"
BACKUP_DIR="/tmp/workspace-merge-backup-${TS}"
SESSIONS_DIR="$ROOT_DIR/sessions"

echo "==> workspace-merge-safe: pre-merge backup starting"
echo "    target:     ${MERGE_TARGET:-<none — dry-run only>}"
echo "    backup_dir: $BACKUP_DIR"
echo "    dry_run:    $DRY_RUN"

if [ ! -d "$SESSIONS_DIR" ]; then
  echo "WARN: sessions/ directory not found at $SESSIONS_DIR — skipping backup" >&2
else
  mkdir -p "$BACKUP_DIR"
  # Copy sessions/ preserving structure; use -a for metadata preservation.
  cp -a "$SESSIONS_DIR" "$BACKUP_DIR/sessions"
  echo "==> backup complete: $(find "$BACKUP_DIR/sessions" -type f | wc -l) files"
fi

# ---- pre-merge manifest --------------------------------------------------
MANIFEST_FILE="$BACKUP_DIR/manifest-pre-merge.txt"
mkdir -p "$BACKUP_DIR"
{
  echo "# Pre-merge sessions/ manifest"
  echo "# Generated: $(date -Iseconds)"
  echo "# Target:    ${MERGE_TARGET:-<dry-run>}"
  echo ""
  if [ -d "$SESSIONS_DIR" ]; then
    find "$SESSIONS_DIR" -type f | sort
  else
    echo "(sessions/ not found)"
  fi
} > "$MANIFEST_FILE"
echo "==> manifest written: $MANIFEST_FILE ($(wc -l < "$MANIFEST_FILE") lines)"

# List unprocessed inbox items — these are highest-risk items to lose.
echo ""
echo "==> Unprocessed inbox items (no outbox counterpart):"
_unprocessed=0
if [ -d "$SESSIONS_DIR" ]; then
  while IFS= read -r inbox_item; do
    agent="$(basename "$(dirname "$(dirname "$inbox_item")")")"
    item_name="$(basename "$(dirname "$inbox_item")")"
    outbox_file="$SESSIONS_DIR/${agent}/outbox/${item_name}.md"
    if [ ! -f "$outbox_file" ]; then
      echo "    UNPROCESSED: sessions/${agent}/inbox/${item_name}/"
      _unprocessed=$((_unprocessed + 1))
    fi
  done < <(find "$SESSIONS_DIR" -mindepth 3 -maxdepth 3 -name "command.md" 2>/dev/null | sort)
fi
if [ "$_unprocessed" -eq 0 ]; then
  echo "    (none — all inbox items have outbox counterparts)"
fi
echo ""

if [ "$DRY_RUN" -eq 1 ]; then
  echo "==> DRY RUN: skipping git merge — pre-merge check complete"
  echo "    backup available at: $BACKUP_DIR"
  exit 0
fi

# ---- git merge -----------------------------------------------------------
echo "==> executing: git merge --no-edit $MERGE_TARGET"
if ! git merge --no-edit "$MERGE_TARGET"; then
  echo "ERROR: git merge failed; aborting. Backup preserved at $BACKUP_DIR" >&2
  exit 1
fi
echo "==> git merge complete"

# ---- post-merge integrity check ------------------------------------------
echo ""
echo "==> post-merge integrity check..."
DELETED_FILES=()
if [ -d "$BACKUP_DIR/sessions" ]; then
  while IFS= read -r backed_up_file; do
    # Compute path relative to backup sessions dir.
    relative="${backed_up_file#"$BACKUP_DIR/sessions/"}"
    live_path="$SESSIONS_DIR/$relative"
    if [ ! -f "$live_path" ]; then
      DELETED_FILES+=("sessions/$relative")
    fi
  done < <(find "$BACKUP_DIR/sessions" -type f | sort)
fi

if [ "${#DELETED_FILES[@]}" -eq 0 ]; then
  echo "==> integrity check PASSED: no sessions/ files deleted by merge"
  echo "    backup available at: $BACKUP_DIR (safe to remove after review)"
  exit 0
else
  echo ""
  echo "WARNING: post-merge integrity check found ${#DELETED_FILES[@]} deleted file(s):"
  for f in "${DELETED_FILES[@]}"; do
    echo "    DELETED: $f"
  done
  echo ""
  echo "==> backup preserved at: $BACKUP_DIR"
  echo "==> To restore deleted files:"
  echo "    cp -r $BACKUP_DIR/sessions/ $ROOT_DIR/"
  echo "    git add -f sessions/ && git commit -m 'restore: recover sessions/ files lost in workspace merge'"
  echo ""
  echo "WARNING: merge completed but sessions/ artifact loss detected — manual review required"
  exit 2
fi
