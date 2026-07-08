#!/usr/bin/env bash
# Pre-push validation: merge health, escalation quality, etc.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

source scripts/lib/escalations.sh
source scripts/lib/merge-health.sh

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Pre-Push Validation"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

failures=0

# Check 1: Merge health (no uncommitted changes)
echo ""
echo "Check 1: Merge Health"
merge_health_scan "."
if [ "$MERGE_HEALTH_HAS_ISSUES" -eq 1 ]; then
    echo -e "${RED}✗ FAIL${NC} Blocking merge/integration changes detected:"
    merge_health_issue_lines 10 | sed 's/^/  /'
    failures=$((failures + 1))
else
    echo -e "${GREEN}✓ PASS${NC} No blocking merge/integration changes"
    while IFS= read -r detail; do
        [ -n "$detail" ] || continue
        echo "  note: $detail"
    done < <(merge_health_note_lines 10)
fi

# Check 2: No git index lock
echo ""
echo "Check 2: Git Index Lock"
if [ -f ".git/index.lock" ]; then
    lock_age=$(($(date +%s) - $(stat -c %Y .git/index.lock 2>/dev/null || echo 0)))
    if [ "$lock_age" -gt 10 ]; then
        echo -e "${YELLOW}⚠ WARN${NC} Stale git index lock detected (age: ${lock_age}s); removing"
        rm -f ".git/index.lock"
    else
        echo -e "${RED}✗ FAIL${NC} Git index lock exists (age: ${lock_age}s)"
        failures=$((failures + 1))
    fi
else
    echo -e "${GREEN}✓ PASS${NC} No git index lock"
fi

# Check 3: Escalation item quality
echo ""
echo "Check 3: Escalation Item Quality"
malformed_count=0
for inbox in sessions/*/inbox; do
    if [ -d "$inbox" ]; then
        # Count malformed items (status needs-info or blocked, but missing/empty Needs section)
        while IFS= read -r item; do
            if ! validate_escalation_item "$item" 2>/dev/null; then
                malformed_count=$((malformed_count + 1))
                echo "  - $(basename "$item")"
            fi
        done < <(find "$inbox" -maxdepth 2 -name "*.md" -type f 2>/dev/null || true)
    fi
done

if [ "$malformed_count" -gt 0 ]; then
    echo -e "${RED}✗ FAIL${NC} Found $malformed_count malformed escalation item(s) (see above)"
    failures=$((failures + 1))
else
    echo -e "${GREEN}✓ PASS${NC} All escalation items properly formatted"
fi

# Check 4: RoomChat source-of-truth drift (run only when RoomChat paths changed)
echo ""
echo "Check 4: RoomChat Tree Drift"
roomchat_changes="$(git status --porcelain -- \
    src/Controller/RoomChatController.php \
    src/Service/RoomChatService.php \
    src/Service/RoomChatServicePart*.php \
    src/Service/RoomChat/*.php \
    sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/RoomChatController.php \
    sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/RoomChatService.php \
    sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/RoomChatServicePart*.php \
    sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/RoomChat/*.php \
    2>/dev/null || true)"
if [ -n "$roomchat_changes" ]; then
    if ./scripts/check-roomchat-tree-drift.sh >/dev/null 2>&1; then
        echo -e "${GREEN}✓ PASS${NC} RoomChat canonical tree and runtime mirror are in sync"
    else
        echo -e "${RED}✗ FAIL${NC} RoomChat canonical tree and runtime mirror drift detected"
        ./scripts/check-roomchat-tree-drift.sh | sed 's/^/  /' || true
        failures=$((failures + 1))
    fi
else
    echo -e "${GREEN}✓ PASS${NC} No RoomChat path changes in this push scope"
fi

# Summary
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
if [ "$failures" -eq 0 ]; then
    echo -e "${GREEN}✓ All pre-push checks PASSED${NC}"
    exit 0
else
    echo -e "${RED}✗ $failures check(s) FAILED — fix above before pushing${NC}"
    exit 1
fi
