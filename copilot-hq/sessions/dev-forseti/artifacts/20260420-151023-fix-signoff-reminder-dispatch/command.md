- Status: done
- Completed: 2026-04-20T15:28:13Z

# Fix: Move signoff-reminder dispatch to release_cycle_step

## Problem
Signoff-reminder dispatch is domain-confused: called from health_check_step (line 2206) instead of release_cycle_step. This causes latency-sensitive dispatch depending on health_check cadence + 1h cooldown.

Forseti-release-q never got a reminder because health_check cadence missed the window, then 1h cooldown blocked retry.

## Solution
1. Remove _dispatch_signoff_reminders() call from _health_check_step (line 2206)
2. Add _dispatch_signoff_reminders() call in _release_cycle_step after signoff detection (line 2584)
3. Keep the 1h cooldown but couple it to release_cycle tick (more responsive)

## Files
- orchestrator/run.py

## Acceptance Criteria
- Signoff-reminder dispatch is called from release_cycle_step, not health_check_step
- Reminders fire within 1 tick of signoff detection (not dependent on health_check cadence)
- Tests pass: orchestrator/tests/test_release_cycle_control.py
- Manual verification: sign off a release, verify reminder fires to unsigned PM within 60s

## Risk
Low: this is moving a function call, not changing logic
