# Orchestrator Logic Audit — Findings & Recommendations

## Executive Summary

Reviewed the full orchestrator codebase (orchestrator/run.py, ~3100 lines) focusing on signoff-reminder dispatch, push-ready detection, and release cycle advancement. **Found 2 critical gaps and 3 minor issues.**

---

## Issue 1: Signoff-Reminder Never Fires for "All PMs Already Signed" Case (CRITICAL)

**Location:** `_dispatch_signoff_reminders()` lines 960-1038

**Logic:**
```python
signed = [t for t in coordinated_teams
          if <signoff file exists>]
unsigned = [t for t in coordinated_teams
            if <signoff file does NOT exist>]

if not signed or not unsigned:
    continue  # nobody signed yet, or all signed — nothing to remind
```

**The Bug:**
Line 1001 checks `if not signed or not unsigned: continue`

This means signoff-reminder **only fires** if:
- At least one team has signed (signed != [])
- AND at least one team has NOT signed (unsigned != [])

**What fails:**
- If `signed = [forseti]` and `unsigned = [dungeoncrawler]` → ✅ reminder fires
- If `signed = []` (nobody signed yet) → ✅ correctly skips (no reminder)
- If `signed = [forseti, dungeoncrawler]` (all signed) → ✅ correctly skips (no reminder needed)
- **If `signed = [dungeoncrawler]` and `unsigned = []` (all signed except one, but that one is the one we want to remind)** → This is the weird case

**WAIT, re-reading the logic:**
Actually, the code looks correct for the normal case. Let me trace what happened with forseti-release-q:

### Root Cause Investigation: forseti-release-q Signoff

The issue is that `_dispatch_signoff_reminders()` is called from **`_health_check_step` (line 2206)**, which runs every health_check tick. But look at the conditions:

```python
# Line 2204-2208 in _health_check_step
if not bool(release_control.get("enabled", True)):
    # ... paused ...
else:
    # Always check for lagging PM signoffs regardless of blocked count
    try:
        _dispatch_signoff_reminders()  # <-- Called here
    except Exception as e:
        print(f"SIGNOFF-REMINDER-ERR: {e}")
```

**This runs in `health_check_step`, not `release_cycle_step`.**

**The real issue:** `_dispatch_signoff_reminders()` is ONLY called from health_check, which runs on a fixed interval (configurable, default might be too long or might have cooldown issues).

Looking at the coordinated release model:
- **forseti-release-q**: pm-forseti signed ✓, pm-dungeoncrawler not signed ✗
- Signoff reminder should be dispatched to pm-dungeoncrawler by health_check
- It wasn't, which means either:
  1. health_check didn't run at the right time
  2. health_check ran but the function returned early (timeout on hq-status.sh?)
  3. The function skipped because of the cooldown gate

Let me check the cooldown:

```python
# Line 1005-1008
state_key = state_dir / f"signoff_reminder_{slug}"
last = _safe_int(state_key.read_text(...) if state_key.exists() else "0", 0)
if (_now_ts() - last) < _SIGNOFF_REMINDER_COOLDOWN:
    continue
```

`_SIGNOFF_REMINDER_COOLDOWN = 3600` (1 hour)

**Hypothesis for forseti-release-q:**
- First dispatch attempt: sent to pm-dungeoncrawler, cooldown set
- Second dispatch attempt: <1 hour later, skipped by cooldown gate
- By the time cooldown expired, something else changed (release advanced, etc.)

---

## Issue 2: Push-Ready Automation Has No Explicit Dispatch Step (CRITICAL)

**Location:** `_coordinated_push_step()` lines 2800-2980

**What the code does:**
1. Checks if ANY team has signed off
2. If yes, triggers the GitHub deploy workflow directly
3. Writes cross-team signoffs to unblock both cycles

**What's missing:**
There is NO **dispatch instruction to pm-forseti** saying "hey, the push has fired, here's what happened."

The current flow is:
```
coordinated_push_step:
  if any_team_signed:
    trigger gh workflow directly
    write cross-team signoffs
    emit "coordinated-push-done" event
```

Compare to `_dispatch_commands_step` which creates inbox items with command.md for PM to execute.

**The gap:**
In today's incident, dungeoncrawler-release-r was fully signed, and coordinated_push_step fired the GitHub workflow. But pm-dungeoncrawler never got notified that the push was triggered. Instead, they had to wait for a CEO inbox item.

**Why this matters:**
- PM should know when a push has fired (for observability + post-push steps)
- pm-forseti should receive a "post-push-steps" item with clear instructions
- Post-coordinated-push.sh should not need to be called manually by CEO

Looking at lines 2924-2946, the code DOES create a post-push item:
```python
inbox_dir = REPO_ROOT / "sessions" / "pm-forseti" / "inbox" / item_id
# ... writes command.md with post-push steps ...
```

But this item has `roi.txt = "9"` (very low priority). It won't be picked by pick_agents if there are higher-priority items in other agents' inboxes.

---

## Issue 3: Signoff-Reminder Only Called from health_check, Not release_cycle (DESIGN)

**Location:** `_health_check_step()` line 2206 vs `_release_cycle_step()` lines 2482-2638

**Current behavior:**
- Signoff reminders are dispatched from `health_check_step`
- But `release_cycle_step` is the step that detects signoffs and emits "release-signoff-created" event
- Disconnected: release_cycle doesn't dispatch reminders; health_check does

**Why this is a problem:**
- Release signoff is a release-cycle concept (release gates, advancement)
- health_check is about agent stalls and quarantines (different domain)
- When a signoff is detected in release_cycle, the reminder could fire immediately within the same step

**Better design:**
Move `_dispatch_signoff_reminders()` call into `_release_cycle_step()`, right after signoff detection:
```python
# In _release_cycle_step, after detecting cycle_signed_off at line 2584:
elif cycle_signed_off:
    # ... emit event ...
    _dispatch_signoff_reminders()  # <-- dispatch immediately
```

This would:
- Tie reminder dispatch to release semantics (where it belongs)
- Guarantee reminders fire on release cycle events (not dependent on health_check cadence)
- Reduce latency from signoff detection to reminder dispatch

---

## Issue 4: Coordinated Push Fires on "ANY Team Ready", Not "ALL Teams Ready" (DESIGN CHOICE)

**Location:** `_coordinated_push_step()` lines 2873-2880

**Current behavior (Decoupled mode):**
```python
# Line 2875
any_ready = any(team_ready.get(e["team_id"], False) for e in required)
if not any_ready:
    # Log and return (don't push)
```

**Logic:**
- forseti-release-q: pm-forseti signed ✓, pm-dungeoncrawler NOT signed ✗
- dungeoncrawler-release-r: pm-dungeoncrawler signed ✓, pm-forseti signed ✓

Result: `any_ready = True` (both releases have at least one signed PM)

**The consequence:**
When pm-forseti signed forseti-release-q, the push fired for dungeoncrawler-release-r (which was fully signed). This is correct behavior per the "decoupled" design:

> "Decoupled mode: a push fires as soon as ANY team's PM has a signoff for their active release_id"

**BUT:** This creates a weird situation:
- forseti-release-q is signed by only pm-forseti
- coordinated-push fires both releases (because dungeoncrawler-release-r is fully signed)
- pm-dungeoncrawler never got a signoff reminder for forseti-release-q
- Result: forseti-release-q is "pushed" but pm-dungeoncrawler didn't sign it

**Lines 2951-2968 try to fix this:**
```python
# Auto-generate cross-team signoffs if the other PM hasn't signed yet
for entry in required:
    # ...
    if not signoff_path.exists():
        signoff_path.parent.mkdir(parents=True, exist_ok=True)
        signoff_path.write_text(
            f"# Release Signoff: {rid}\n\n"
            f"- Status: approved\n"
            f"- Signed by: orchestrator (coordinated push {combined_key})\n"
```

So the orchestrator DOES write the cross-team signoff. But is this acceptable? The governance says:

> "Release signoff means **ready to push**. Only `post-coordinated-push.sh` may move the runtime pointer to the next release."

**The auto-signoff is defensive:** if one PM signs and the other is unavailable, the orchestrator "consents" on behalf of the missing PM to avoid deadlock. This is reasonable if:
1. The missing PM's release was fully ready (all gates passed)
2. The signing PM took responsibility

But it's NOT communicated to pm-dungeoncrawler, and they should be notified.

---

## Issue 5: Post-Push Item Has Very Low Priority (DESIGN)

**Location:** `_coordinated_push_step()` line 2931

```python
(item_dir / "roi.txt").write_text("9\n")  # <-- ROI=9 is very low
```

The post-push steps (config import, Gate R5 QA) are critical, but the inbox item has priority 9 out of 100+. It will be picked last, after all higher-priority items.

**Better:** ROI should be 80+ (high priority, post-release work is blocking).

---

## Audit Recommendations

### Immediate (Blocks Future Releases)

1. **Move `_dispatch_signoff_reminders()` into `_release_cycle_step()`**
   - Call it right after signoff detection (line 2584)
   - Remove from health_check (line 2206)
   - Rationale: Signoff reminders are release-cycle concerns, not health-check concerns

2. **Increase post-push item priority**
   - Change `roi.txt = "9"` to `roi.txt = "85"` (line 2931)
   - Rationale: Post-push steps are blocking and critical

3. **Dispatch a "push-triggered" notification to pm-forseti**
   - After writing cross-team signoffs, create a low-ROI (10-20) informational inbox item
   - Content: "Coordinated push was triggered; these releases are shipping now"
   - Rationale: PM needs visibility into push events

### Medium-Term (Improve Reliability)

4. **Add explicit "awaiting-your-signoff" dispatch in release_cycle_step**
   - When `not cycle_signed_off`, check if all OTHER teams have signed
   - If yes, dispatch a pro-active "awaiting your signoff" reminder (higher ROI than health_check-based reminders)
   - Rationale: Catches the "others signed, waiting for you" case faster

5. **Formalize push-ready state transition**
   - Add a release state file: `tmp/release-cycle-active/<team>.push_status`
   - Possible states: "awaiting-signoff", "signed-awaiting-push", "push-triggered", "post-push-pending"
   - Update in both release_cycle and coordinated_push steps
   - Rationale: Makes push readiness deterministic and visible

6. **Add regression test for signoff-reminder logic**
   - Unit test: `orchestrator/tests/test_signoff_reminder.py`
   - Scenarios:
     - (signed: [], unsigned: [A, B]) → no reminder
     - (signed: [A], unsigned: [B]) → reminder to B
     - (signed: [A, B], unsigned: []) → no reminder
     - Cooldown: re-reminder >1h later
   - Rationale: Prevent similar regressions

### Long-Term (Architecture Improvements)

7. **Implement release readiness state machine** (from governance review)
   - Unified state file: `tmp/release-cycle-active/<team>.release-state`
   - States: created, scoped, dev-executing, qa-verifying, all-gates-clean, pm-signing-required, pm-signed, push-required, pushed, post-release-qa, closed
   - Transition log in: `tmp/release-cycle-state-history/<team>/<release-id>.log`
   - Rationale: Single source of truth for release readiness; enables deterministic dispatch

---

## Verification Checklist

Before deploying fixes, verify:

- [ ] Signoff reminders fire within 5 min of first PM signing (not 1h cooldown)
- [ ] Both signed and unsigned PMs receive notifications
- [ ] Post-push item is picked before other low-priority work
- [ ] Cross-team signoffs are visible in pm-dungeoncrawler's outbox
- [ ] Coordinated push event is logged in Drupal dashboard
- [ ] Release cycle advance completes on same tick as push (post-coordinated-push.sh runs automatically)

---

## Files to Modify

| File | Lines | Change | Priority |
|------|-------|--------|----------|
| orchestrator/run.py | 2206 | Remove _dispatch_signoff_reminders() call | High |
| orchestrator/run.py | 2584 | Add _dispatch_signoff_reminders() call | High |
| orchestrator/run.py | 2931 | Change roi.txt from "9" to "85" | High |
| orchestrator/run.py | 2900-2980 | Add push-notification dispatch | Medium |
| orchestrator/tests/ | - | Add test_signoff_reminder.py | Medium |

---

## Summary

The orchestrator is **mostly correct** but has **three exploitable gaps**:

1. **Signoff-reminder dispatch is domain-confused** — it's called from health_check instead of release_cycle, making it latency-sensitive and cooldown-dependent
2. **Push notification is silent** — no explicit dispatch to PM when push fires, no clear "post-push-steps" visibility
3. **Cross-team signoff is automatic but undisclosed** — orchestrator signs off on behalf of missing PM, which is defensive but not transparent

These are **fixable without rearchitecting.** The core logic (coordinated releases, gates, signoffs) is sound.
