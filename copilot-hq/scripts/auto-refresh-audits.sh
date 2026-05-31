#!/usr/bin/env bash
# Auto-refresh stale site audits (triggered by orchestrator or cron)

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# Configuration
AUDIT_MAX_AGE_SECONDS=86400  # 24h
ALLOW_PROD_QA="${ALLOW_PROD_QA:-0}"

echo "Checking site audit freshness..."

for site in forseti dungeoncrawler; do
    audit_link="sessions/qa-${site}/artifacts/auto-site-audit/latest"
    
    if [ ! -L "$audit_link" ]; then
        echo "  [$site] No audit symlink found; skipping"
        continue
    fi
    
    if [ ! -e "$audit_link" ]; then
        echo "  [$site] Audit symlink broken; needs new audit"
        continue
    fi
    
    # Get actual audit directory
    audit_dir=$(readlink -f "$audit_link" 2>/dev/null || true)
    if [ -z "$audit_dir" ]; then
        echo "  [$site] Could not resolve audit symlink; skipping"
        continue
    fi
    
    # Check age
    audit_mtime=$(stat -c %Y "$audit_dir" 2>/dev/null || echo 0)
    now=$(date +%s)
    age=$((now - audit_mtime))
    
    if [ "$age" -lt "$AUDIT_MAX_AGE_SECONDS" ]; then
        age_hours=$((age / 3600))
        echo "  ✓ [$site] Audit fresh ($age_hours hours old)"
    else
        age_hours=$((age / 3600))
        echo "  ⚠ [$site] Audit stale ($age_hours hours old); refreshing..."
        
        # Trigger refresh
        if [ "$ALLOW_PROD_QA" = "1" ]; then
            if ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh "$site" 2>&1 | tail -5; then
                echo "  ✓ [$site] Audit refresh complete"
            else
                echo "  ✗ [$site] Audit refresh failed"
            fi
        else
            echo "  ⓘ [$site] Refresh skipped (ALLOW_PROD_QA not set); run: ALLOW_PROD_QA=1 bash scripts/auto-refresh-audits.sh"
        fi
    fi
done

echo "Done"
