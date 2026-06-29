#!/usr/bin/env bash
# Prune executor failures older than TTL; aggregate patterns

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

FAILURES_DIR="tmp/executor-failures"
TTL_SECONDS=86400  # 24h
FAILURE_THRESHOLD=10  # If >N failures in 24h, trigger escalation

if [ ! -d "$FAILURES_DIR" ]; then
    exit 0  # No failures directory, nothing to clean
fi

echo "Cleaning up executor failures..."

# Delete old failures
now=$(date +%s)
deleted=0
while IFS= read -r failure_file; do
    mtime=$(stat -c %Y "$failure_file" 2>/dev/null || echo 0)
    age=$((now - mtime))
    
    if [ "$age" -gt "$TTL_SECONDS" ]; then
        rm -f "$failure_file"
        deleted=$((deleted + 1))
    fi
done < <(find "$FAILURES_DIR" -maxdepth 1 -name "*.md" -type f)

if [ "$deleted" -gt 0 ]; then
    echo "  Deleted $deleted old failure logs (>24h)"
fi

# Count recent failures
recent_count=$(find "$FAILURES_DIR" -maxdepth 1 -name "*.md" -type f -mtime -1 2>/dev/null | wc -l)

if [ "$recent_count" -gt "$FAILURE_THRESHOLD" ]; then
    echo "  ⚠ High failure rate: $recent_count failures in last 24h (threshold: $FAILURE_THRESHOLD)"
    
    # Aggregate by agent
    echo "  Failure breakdown:"
    find "$FAILURES_DIR" -maxdepth 1 -name "*.md" -type f -mtime -1 -exec basename {} \; | sed 's/^[0-9TZ:-]*-//;s/\.md$//' | sort | uniq -c | sort -rn | awk '{print "    " $2 ": " $1 " failure(s)"}'
    
    # Could dispatch escalation here, but for now just alert
    echo "  → Consider investigating: bash scripts/hq-blockers.sh"
else
    echo "  Recent failures: $recent_count (within threshold)"
fi

echo "Done"
