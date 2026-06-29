- Status: done
- Completed: 2026-04-20T15:42:01Z

# Fix: Increase post-push item priority

## Problem
Post-push item (config import, Gate R5 QA) has ROI=9 (very low). It gets picked last, delaying post-release QA.

Post-release work is critical and blocking.

## Solution
Change roi.txt from "9" to "85" for the post-push inbox item.

## Files
- orchestrator/run.py line 2931

## Acceptance Criteria
- roi.txt = "85" in post-push item creation
- Post-push item is picked before other non-critical work
- Manual verification: trigger a push, observe post-push item picked early

## Risk
Low: only changes priority, not logic
