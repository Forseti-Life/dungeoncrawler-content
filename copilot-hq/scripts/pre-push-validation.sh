#!/usr/bin/env bash
# Pre-push validation: merge health, escalation quality, etc.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

source scripts/lib/escalations.sh

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
if [ -n "$(git status --short 2>/dev/null || true)" ]; then
    echo -e "${RED}✗ FAIL${NC} Uncommitted changes detected:"
    git status --short | head -10
    if [ $(git status --short | wc -l) -gt 10 ]; then
        echo "  ... and $(( $(git status --short | wc -l) - 10 )) more"
    fi
    failures=$((failures + 1))
else
    echo -e "${GREEN}✓ PASS${NC} Merge health clean"
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
