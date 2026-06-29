# Release Cycle Process & Governance Review

## Executive Summary

The HQ release cycle process is **well-designed** with clear stages, ownership, and handoffs. However, there are **3 governance gaps** exposed by today's stagnation incident:

1. **Signoff reminder dispatch is not guaranteed** — orchestrator's signoff-reminder logic didn't fire for forseti-release-q
2. **Dispatch regression broke the PM inbox contract** — orchestrator stopped copying command.md, causing 6 executor quarantines
3. **Push readiness detection is not triggering** — orchestrator didn't dispatch push-ready instruction for release-r

## Release Cycle Governance Model (Authoritative Source)

### Dual-Release Model (Permanent)
- **Current release:** Dev + QA executing (Stages 3-7)
- **Next release:** PM + QA grooming (Stages 0-2)
- Only `post-coordinated-push.sh` may advance the runtime release pointer

### Stage Ownership
| Stage | Owner | Gate | Decision Authority |
|-------|-------|------|-------------------|
| 0 | PM (scope freeze) | SoT: groomed backlog | PM curates scope |
| 1 | PM (backlog intake) | N/A for current release | PM accepts/defers |
| 2 | QA / Dev (triage) | N/A for current release | QA/Dev routing |
| 3-5 | Dev + QA (execution) | Fail/pass repair loop | Escalate at 5 attempts |
| 6 | All PM seats (signoff) | Release candidate + signoffs | **All required PMs must sign** |
| 7 | Release operator (push) | Gates clean + all signoffs | Operator verifies gates |

### Coordination Requirement
**Key Rule:** forseti-release-q and dungeoncrawler-release-r are **coordinated releases**.

From `org-chart/products/product-teams.json`:
- forseti: `"coordinated_release_default": true` → requires pm-dungeoncrawler cosign
- dungeoncrawler: `"coordinated_release_default": true` → requires pm-forseti cosign

**Enforcer:** `scripts/release-signoff-status.sh <release-id>` verifies all required PMs have signed.

## Governance Gaps Identified

### Gap 1: Signoff-Reminder Dispatch Not Guaranteed

**Expected Behavior:**
- When release-q reaches Gate 6 (all dev+qa gates clean), orchestrator should dispatch signoff-reminder to all required PM seats (pm-dungeoncrawler for forseti-release-q)

**Actual Behavior:**
- pm-dungeoncrawler never received a signoff-reminder for forseti-release-q
- pm-dungeoncrawler had received reminders for OTHER releases (release-n, release-p, dungeoncrawler-q) but not THIS one
- Manual CEO dispatch was required

**Root Cause (Hypothesis):**
- The `release_cycle` orchestrator step or a separate signoff-reminder dispatcher didn't create the reminder
- Possible causes:
  1. The gate detection logic is stale or broken
  2. The signoff-reminder templating is missing for forseti-release-q
  3. The coordinated-release gate check doesn't properly identify required cosigners

**Governance Impact:**
- Release signoff can stall indefinitely if orchestrator doesn't remind PMs
- Current backoff: CEO must manually dispatch or Board must escalate
- SLA: "No release signoff in 6h+" should have triggered an earlier CEO inbox item (and did, but CEO didn't catch the root cause)

### Gap 2: PM Inbox Contract Regression

**Expected Behavior:**
- PM inbox items contain a command.md file with the actionable directive
- agent-exec-next reads command.md + optional templates, builds a well-formed prompt
- Agent processes the item and returns a Status header

**Actual Behavior:**
- Orchestrator dispatch_commands created PM inbox items with ONLY templates (00-problem, 01-acceptance, 06-risk) and README
- NO command.md file was created
- agent-exec-next read the README (which contains instructions for PM to fill templates), not a task directive
- Agent returned empty response (confused by instructions meant for humans)
- Executor quarantined after 3 retries

**Root Cause:**
- Orchestrator refactoring broke the legacy "copy command.md" step
- Pre-refactored scripts (ceo-dispatch.sh, ceo-dispatch-next.sh) called dispatch-pm-request.sh, then copied the original command into inbox
- Orchestrator forgot this step

**Governance Impact:**
- Breaks the fundamental PM agent contract (command.md)
- Causes silent executor failures (empty responses, not parsing errors)
- Results in phantom "needs-info" blockers that stagnate the queue
- Hard to diagnose (must trace through executor-failures/ directory)

### Gap 3: Push-Ready Dispatch Not Triggered

**Expected Behavior:**
- When a release is fully signed and all gates pass, orchestrator should dispatch a push-ready/push-trigger instruction to the release operator
- Alternatively: orchestrator should automatically proceed to push

**Actual Behavior:**
- dungeoncrawler-release-r was fully signed (both pm-forseti + pm-dungeoncrawler signed) ✓
- QA gates passed ✓
- But no push instruction was dispatched to pm-dungeoncrawler
- Manual CEO dispatch was required

**Root Cause (Hypothesis):**
- The `coordinated-push` automation in orchestrator may not be wired for the "all gates clean" trigger
- Or: release-r was marked as "decoupled" from release-q, causing the push logic to stall

**Governance Impact:**
- Even when release is fully ready, it won't ship without PM action
- But PM may not know it's ready if no dispatch/reminder is sent
- Manual CEO dispatch required

## Governance Strengths

### Clear Ownership Model
- Each stage has a clear owner (PM, Dev, QA, Security, Release Operator)
- Stages are sequential, not parallel (reduces race conditions)
- Escalation is explicit (5-attempt rule, then escalate to PM)

### Artifact-Centric Decision Making
- Release readiness is verified by checking artifact existence + content:
  - Signoff SoT: `sessions/<pm>/artifacts/release-signoffs/<release-id>.md`
  - Verification SoT: `sessions/qa-*/artifacts/auto-site-audit/<ts>/findings-summary.md`
  - Release candidate SoT: `sessions/<lead_pm>/artifacts/release-candidates/<release-id>/`
- No ambiguous "status fields" — artifacts either exist or don't

### Coordinated Release Gate
- The `coordinated_release_default: true` flag in product-teams.json explicitly marks which products are coordinated
- `release-signoff-status.sh` is the single gate check (exit code 0 = ready)
- No implicit assumptions; all required PMs are discoverable from product registry

### Repair Loop Governance
- Failed tests trigger Dev fix, not endless retries
- 5-attempt limit before escalation (prevents spinning)
- QA explicitly validates each fix (not blind retry)

## Recommendations for Process Improvement

### 1. Add Release Readiness State Machine
**Problem:** The current model relies on artifact existence + orchestrator dispatch. If dispatch fails, release is stuck with no visibility.

**Solution:** Create a release readiness state machine with explicit transitions:
```
release-state: created → scoped → dev-executing → qa-verifying → 
  all-gates-clean (NEW STATE) → pm-signing-required → pm-signed → 
  push-required → pushed → post-release-qa → closed
```

Store current state in `tmp/release-cycle-active/<team>.release-state` (like the current release_id files).

**Enforcement:** Each state transition requires a verification script + event log.

### 2. Implement Signoff-Reminder SLA
**Problem:** Signoff reminders are dispatched once but may be ignored or lost.

**Solution:** Add an SLA rule:
- If a release is in "pm-signing-required" state and hasn't received a signoff within 60 minutes, dispatch a reminder every 30 minutes until signed
- Track dispatch count in `tmp/signoff-reminder-dispatch-count/<release-id>` 
- Escalate to CEO inbox if 3+ reminders sent without action (possible blocker)

**Governance:** This makes signoff delays visible and prevents indefinite stalls.

### 3. Formalize Push-Ready Automation
**Problem:** Push-ready releases rely on CEO manual dispatch; no clear trigger for pm-forseti to execute push.

**Solution:** Define explicit push-ready trigger:
- When ALL of these are true: (1) all gates passed, (2) all required PM signoffs exist, (3) release candidate is complete + lint-clean
- Orchestrator dispatches push-ready/push-trigger to pm-forseti with explicit instructions
- Push instruction includes: signoff verification command + lint check + deploy steps

**Governance:** Makes the push decision deterministic and removes guessing.

### 4. Add Regression Test for Dispatch Contract
**Problem:** The command.md contract regression happened silently. Future refactors may break other contracts.

**Solution:** Add to orchestrator test suite (`orchestrator/tests/test_dispatch_contract.py`):
```python
def test_pm_dispatch_creates_command_md():
    """Verify that dispatch-pm-request creates inbox items with command.md"""
    # Trigger dispatch_commands for a test command with pm: field
    # Verify the created inbox item contains command.md
    # Verify command.md is NOT empty (contains the original command content)
```

**Governance:** Prevents regression without blocking legitimate refactors.

### 5. Document the Product-PM Coordination Matrix
**Problem:** The coordination requirement (pm-forseti cosigns dungeoncrawler, pm-dungeoncrawler cosigns forseti) is implicit in product-teams.json. Easy to forget during setup.

**Solution:** Add a governance doc: `org-chart/RELEASE_COORDINATION_MATRIX.md`
```
# Release Coordination Rules

When releasing:
- forseti-release-*: requires cosign from pm-dungeoncrawler (coordinated_release_default: true)
- dungeoncrawler-release-*: requires cosign from pm-forseti (coordinated_release_default: true)

These are non-negotiable. Releases cannot ship without both signoffs.

Source of truth: org-chart/products/product-teams.json
Verification: scripts/release-signoff-status.sh <release-id>
```

**Governance:** Makes the coordination requirement explicit and discoverable.

## Summary: Governance Maturity

| Aspect | Maturity | Evidence |
|--------|----------|----------|
| **Ownership clarity** | High | Clear stage owners + escalation rules |
| **Decision documentation** | High | Artifact-centric (release candidate + signoffs) |
| **Automation reliability** | Low | Dispatch regressions + missing reminder logic |
| **Visibility into readiness** | Medium | Artifacts exist but no unified state view |
| **SLA enforcement** | Low | Stagnation alert only fires after 6h+ delay |
| **Testability** | Medium | Good for manual verification, poor for automation regression detection |

**Recommendation:** The process design is sound. The gaps are in **automation reliability** (dispatch + reminder logic) and **visibility** (state machine + metrics). Implement recommendations 1-4 to improve maturity to "high" across all dimensions.
