- Status: done
- Completed: 2026-04-20T16:34:20Z

# Feature: Pro-active "awaiting-your-signoff" dispatch

## Problem
Current signoff-reminder is reactive: it fires after someone signs. If one PM is slow or away, they don't get nudged until someone else signs.

## Solution
In release_cycle_step, after all gates pass but BEFORE any PM signs, dispatch a pro-active "your signoff is ready" item:
- Trigger: all dev+qa gates clean, current_release_id set, NO signoffs yet
- Content: "All gates are clean. Your signoff is required to ship <release-id>."
- ROI: 60 (higher than reactive reminder)
- Cooldown: once per release, no re-dispatch

## Files
- orchestrator/run.py (release_cycle_step)

## Acceptance Criteria
- Pro-active dispatch fires when gates clean but no signoffs yet
- Item includes release ID and gate status summary
- No re-dispatch within same release cycle
- Manual verification: complete all gates, verify PM gets "ready to sign" item

## Risk
Medium: new heuristic, may need tuning
- Agent: dev-forseti
- Status: pending
