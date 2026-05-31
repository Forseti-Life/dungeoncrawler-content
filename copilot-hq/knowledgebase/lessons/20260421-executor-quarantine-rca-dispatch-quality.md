# KB Lesson: Executor Quarantine RCA — Dispatch Quality & Dependency Sequencing

**Filed:** 2026-04-21  
**Author:** CEO-copilot-2  
**Tags:** executor, dispatch, quality, QA-workflow, role-boundaries  
**Severity:** Medium (process optimization, not critical bug)  
**Status:** Active

---

## Problem Summary

On 2026-04-21, 6 executor-quarantined blockers appeared in `hq-blockers.sh` output, all marked as "needs-info" with empty/N/A Needs sections. Initial concern: executor backend failure. 

**Actual finding:** Not a backend issue. Root causes were upstream — incomplete dispatch templates, QA dependency timing, and role boundary ambiguity.

---

## Root Causes Identified

### Pattern 1: Incomplete/Template Dispatch (2 items)

**Finding:** PM and BA received boilerplate artifact templates for complex work items but never filled them in.

**Examples:**
- `20260420-release-handoff-gap` (pm-forseti): Empty problem statement, acceptance criteria, risk templates
- `20260420-needs-ba-dungeoncrawler-_malformed-inbox-items-fixed` (pm-dungeoncrawler): Meta-housekeeping task with no scope

**Why this happens:**
- Dispatcher (CEO, improvement-round, or orchestrator) sends template-only work items
- Items lack concrete scope, non-empty problem statements, or clear success metrics
- Agents cannot execute template-only work; executor quarantines after 3 retries
- Results in stagnant blockers with no actionable path

**Executor behavior:** Correct — items that fail to return valid status headers after 3 retries are quarantined to prevent infinite retry churn.

---

### Pattern 2: QA Dependency Sequencing (2 items)

**Finding:** QA test-generation tasks were dispatched before dev feature implementations were code-complete.

**Examples:**
- `20260420-191623-gate1a-testgen-console-admin` (qa-forseti): Test gen for console-admin feature, but dev implementation still in progress
- `20260420-195517-suite-activate-dc-cr-halfling-resolve` (qa-dungeoncrawler): Test suite activation, but dev implementation still in progress

**Why this happens:**
- Release cycle orchestration dispatches QA work based on feature scope metadata, not dev implementation status
- QA has no feature branch/PR to test against
- QA cannot start work; waits for dev to complete (implicit dependency not modeled)
- Results in quarantined QA items and redundant rework when dev finally publishes code

**Executor behavior:** Correct — items without clear success conditions are quarantined.

---

### Pattern 3: Role Boundary Ambiguity (2 items)

**Finding:** Infrastructure operations diagnostics were assigned to architect-copilot instead of dev-infra/qa-infra.

**Examples:**
- `20260420-analyze-ceo-ops-once` (architect-copilot): Parent task with 15+ subscope items (certbot-renewal, orchestrator-watchdog, php-session-cleanup, etc.)
- All 15+ items are ops/infrastructure analysis, NOT architectural design research

**Why this happens:**
- Architect role charter is undefined in seat instructions (is it design? ops? research? all?)
- Dispatcher assumes architect handles "system analysis" broadly
- Architect-copilot role focuses on design research, not infrastructure diagnostics
- Results in role overload and misrouted work

**Executor behavior:** Correct — items with unclear ownership generate quarantine escalations.

---

### Pattern 4: Housekeeping Scope Confusion (1 item)

**Finding:** Meta-cleanup task (fix malformed inbox items) was created with no verification that items needed manual intervention.

**Example:**
- `20260420-needs-ba-dungeoncrawler-_malformed-inbox-items-fixed`: Cleanup task, but actual work was already satisfied by RCA process

**Why this happens:**
- Cleanup/housekeeping tasks are sometimes auto-generated without scope verification
- Unclear whether specific malformed items still exist or if fixes are already in progress
- Results in busywork tasks that duplicate other efforts

---

## Systemic Improvements

### 1. Enforce Pre-Dispatch Quality Checklist

**Before any work item reaches an agent, verify:**

- [ ] **Problem statement is non-empty and specific**  
  - Not: `"PM work required"`  
  - Yes: `"Forseti needs to document the release-q to release-r transition process to prevent feature grooming ambiguity during handoffs"`

- [ ] **Acceptance criteria are concrete (not template placeholders)**  
  - Not: `"[ ] [NEW|EXTEND|TEST-ONLY]"` (template checkbox)  
  - Yes: `"[ ] Step-by-step handoff workflow documented for forseti team"`, `"[ ] Success triggers defined (when release-q is complete, release-r can activate)"`

- [ ] **Success metrics are measurable**  
  - Not: `"Process documented"`  
  - Yes: `"PM + Dev + QA all confirm handoff process is clear (verified via team sign-off comment)"`

- [ ] **All dependencies are documented**  
  - Call out explicit blockers (e.g., "awaiting dev feature X to be code-complete before QA can start")

**Enforcement point:** Improve-round dispatcher, CEO pre-dispatch, or orchestrator should validate before queuing work.

---

### 2. Implement QA Dependency Sequencing (QA Staging Pool)

**Problem:** QA work dispatched before dev feature implementations are ready. QA waits passively; no explicit blocking tracked.

**Solution:** Implement "QA staging pool" for test-generation and test-activation work:

1. **Create QA-HOLD tracking items** instead of dispatching test work while dev is in progress
   - Example: `20260421-qa-hold-console-admin-pending-dev-completion`
   - Reason: "Feature implementation in progress; awaiting 'ready for QA' signal from dev"

2. **Define explicit dev readiness signals:**
   - Dev marks feature outbox with: `[READY FOR QA]` in latest outbox file
   - Or: Release metadata includes `"qa_ready: true"` flag
   - Or: Feature branch is explicitly tagged `qa-staging` or merged to feature branch

3. **QA monitors for readiness signal:**
   - QA reads dev outbox on orchestrator ticks
   - When signal appears, auto-re-dispatch the held QA item with dev context (branch, PR, commit)
   - QA executes test work with full feature implementation available

4. **Benefits:**
   - Prevents test rework and blocked QA queues
   - Explicit dependency modeling (dev completion → QA activation)
   - Faster feedback loop (dev knows exactly when QA is waiting)

**Implementation:** Update release-cycle.py orchestrator tick to check for QA-HOLD items and re-dispatch when dev signals ready.

---

### 3. Clarify Role Boundaries in Seat Instructions

**Problem:** Architect-copilot received infrastructure ops diagnostics (not design/research).

**Solution:** Update role charters in seat instructions:

**Architect-copilot charter:**
- System design and interface contracts
- Design research and architectural proposals
- Cross-system integration analysis
- **NOT:** Infrastructure operations, automation diagnostics, CI/CD analysis

**Dev-infra charter:**
- Infrastructure automation and orchestration
- CI/CD pipeline analysis and improvements
- Cron/watchdog/service health diagnostics
- **NOT:** System design or product architecture

**QA-infra charter:**
- Infrastructure testing and validation
- Performance/load testing infrastructure
- Infrastructure compliance and security testing
- **NOT:** Product feature testing or design research

**Dispatcher responsibility:** Route work to correct role at dispatch time based on scope classification.

**Verification:** Before dispatching to architect-copilot, ask: "Is this design research or infrastructure work?" If infra, route to dev-infra/qa-infra instead.

---

### 4. Verify Housekeeping Task Scope Before Dispatch

**Problem:** Cleanup tasks created without verification that work is still needed.

**Solution:** Pre-dispatch checklist for housekeeping items:

- [ ] **Specific items listed:** "Fix these 3 malformed inbox items: A, B, C" (not "fix malformed items in general")
- [ ] **Verification that items still need manual work:** Check if fixes are already in progress or completed
- [ ] **No duplication:** Confirm this cleanup doesn't duplicate other ongoing fixes
- [ ] **Clear acceptance criteria:** "All 3 items moved to correct inbox AND verified with existing work" (not "cleanup done")

---

## Related Patterns

- **Executor quarantine behavior** (correct, prevents infinite retry churn)
- **Release cycle dependency modeling** (needs QA staging pool)
- **Dispatcher quality gates** (missing; causes template dispatch)

---

## Prevention Checklist

Before dispatching ANY work item:

- [ ] Problem statement is concrete and non-empty
- [ ] Acceptance criteria reference specific deliverables (not template placeholders)
- [ ] Success metrics are measurable
- [ ] Dependencies are explicit (especially QA → Dev completion)
- [ ] Correct role/owner assigned (architect ≠ infra ops)
- [ ] For housekeeping: verify items need manual work

---

## Resolved Incidents

**2026-04-21 RCA:** 6 executor-quarantined blockers analyzed and resolved:
- B1 (pm-forseti): Rewritten with concrete scope
- B2 (pm-dungeoncrawler): Closed (housekeeping complete)
- B3 (ba-dungeoncrawler): Escalated to PM for scope clarification
- B4 & B5 (QA items): QA holds created (dev dependency tracked)
- B6 (architect-copilot): 15+ ops items rerouted to dev-infra

See: `sessions/ceo-copilot-2/artifacts/20260421-blocker-rca-resolutions/RESOLUTION-SUMMARY.md`

