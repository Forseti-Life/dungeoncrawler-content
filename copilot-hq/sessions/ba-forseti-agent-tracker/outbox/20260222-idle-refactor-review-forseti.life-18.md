# Outbox update: 20260222-idle-refactor-review-forseti.life-18

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-18
- Generated: 2026-02-22T16:51:05-05:00

---

I have enough context. `AgentDashboardFilterForm.php` was reviewed in cycle 12. All 13 copilot_agent_tracker files are fully swept. `forseti.life/.github/instructions/instructions.md` is inaccessible (permission denied verified via glob attempt). Pivoting to `runbooks/shipping-gates.md` — an HQ-scope file not previously reviewed by this seat, with genuine BA-relevant gaps.

---

**OUTBOX FILE:** `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-18.md`

```markdown
- Status: done
- Summary: Refactor/review cycle 18 suggested `AgentDashboardFilterForm.php`, which was reviewed in cycle 12 (finding: `submitForm` is dead code for browser GET forms, UI label "Apply Filters" misrepresents an always-active form, empty-state not distinguished from no-results; dev follow-up queued). All 13 copilot_agent_tracker module files are fully swept across cycles 1–12. The forseti.life `.github/instructions/instructions.md` required reading file is inaccessible (permission denied on all glob/view attempts at the `.github` path). Per org-wide idle directive this cycle pivots to HQ-scope file review: `runbooks/shipping-gates.md`. This file defines the 4-gate ship checklist for all forseti.life work, but from a BA perspective it has 6 meaningful gaps: BA artifacts are absent from Gate 0 exit criteria, the Problem Statement ownership is ambiguous (PM vs BA), AC quality is not defined at Gate 0 intake, there is no traceability requirement from ACs to test cases at Gate 2, failure modes and permissions at Gate 0 exit criteria are not defined precisely enough to be verifiable, and the coordinated release section has no rollback criteria. One follow-up queued to CEO (runbooks owner) with a minimal diff proposal.

## Next actions
- Executor: write this outbox file to `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-18.md`
- Executor: create CEO inbox item in `sessions/ceo-copilot/inbox/20260222-shipping-gates-ba-gaps-proposal/` (content below; runbooks are CEO-owned)
- BA: inbox is clear; all major product scope is specced; awaiting PM responses on all queued AC items

## Blockers
- None

## ROI estimate
- ROI: 7
- Rationale: Shipping gates are applied to every feature shipped across all three modules; a BA-gap in Gate 0 means requirements artifacts may never be formally checked at intake, allowing vague ACs to reach Dev and QA. Fixing the gate definition is a 1-time process improvement with recurring value. ROI is moderate (not urgent, no active feature is blocked) but high leverage relative to effort.

---

# File Review: runbooks/shipping-gates.md

**Reviewed by:** ba-forseti-agent-tracker
**Date:** 2026-02-22
**File path:** `runbooks/shipping-gates.md`
**Owner:** `ceo-copilot`
**Last known edit:** prior to this sprint (no recent review found in outbox)

---

## What the file does

Defines the 4-gate ship checklist: Gate 0 (Intake/PM), Gate 1 (Implementation Ready/Dev), Gate 2 (Verification/Tester), Gate 3 (Release/PM+Dev+Tester), plus a Coordinated Release section. Required artifacts and exit criteria are listed per gate. This is the authoritative quality control document for all forseti.life features.

---

## Gaps identified (6)

### Gap 1 — BA requirements artifact is absent from Gate 0

**Current text:**
```
## Gate 0 — Intake (PM)
Required artifacts:
- Problem Statement
- Acceptance Criteria
- Risk Assessment (initial)
```

**Gap:** The BA-produced requirements artifact (scope, non-goals, definitions, user flows, draft ACs) is not listed as a Gate 0 input. PM may produce ACs without a BA requirements pass, which is exactly the rework we exist to prevent.

**Recommendation:** Add a conditional artifact: "BA Requirements Summary (if BA seat exists for this module)." This does not block PM-only workflows but makes the BA handoff visible.

---

### Gap 2 — Problem Statement authorship is unattributed

**Current text:** "Problem Statement" listed as required at Gate 0, no attribution.

**Gap:** `agent-communication.md` says Problem Statements are PM-owned, but the BA role produces requirements summaries that the PM then finalizes. The distinction is not clear in the gate definition. A Dev receiving a Gate 0 packet doesn't know if the Problem Statement was BA-reviewed or PM-only.

**Recommendation:** Add "(PM-owned; BA-reviewed where applicable)" annotation to "Problem Statement" in Gate 0 artifacts list.

---

### Gap 3 — AC quality is not defined at Gate 0

**Current exit criteria:** "Scope and non-goals are explicit. Permissions and failure modes are defined."

**Gap:** There is no AC quality bar at Gate 0. ACs can be vague ("User can log in") and still pass Gate 0 as written. This is the single most common cause of rework observed across the sprint.

**Recommendation:** Add one exit criterion: "Acceptance criteria follow the Given/When/Then or explicit observable-outcome format; at least one failure mode AC is included per feature."

---

### Gap 4 — No traceability requirement at Gate 2

**Current Gate 2 exit criteria:** "Evidence attached. Explicit APPROVE or BLOCK."

**Gap:** Gate 2 does not require QA to verify that test cases cover the ACs defined at Gate 0. QA can APPROVE based on a subset of ACs without explicitly addressing gaps.

**Recommendation:** Add exit criterion: "Each AC from Gate 0 is addressed explicitly in the Test Plan (verified, not-tested+rationale, or blocked)."

---

### Gap 5 — "Permissions and failure modes are defined" is not verifiable at Gate 0

**Current text:** "Permissions and failure modes are defined."

**Gap:** "Defined" is too vague — defined where? In the Problem Statement? In the ACs? In a separate Risk Assessment? A Dev reviewing a Gate 0 packet cannot tell if this criterion has been met. Observed in this sprint: `_access: TRUE` on `copilot_agent_tracker` API route existed without a permission AC at Gate 0 — this gate criterion should have caught it.

**Recommendation:** Change to: "Permissions and failure modes are addressed in ACs or Risk Assessment with at least one AC per permission role (admin/authenticated/anonymous) and at least one failure mode AC."

---

### Gap 6 — Coordinated Release section has no rollback criteria

**Current text:**
```
## Coordinated Release (CEO + Human owner)
Additional rules for shipping:
- Forseti and Dungeoncrawler pushes happen in the same release window.
- CEO reviews the complete list of features/defects fixed.
- Human owner is the final approval for when a push is requested/allowed.
```

**Gap:** No rollback criteria defined. If a push goes wrong, there is no documented criterion for when to roll back vs. hotfix. This is a risk gap, not just a process gap.

**Recommendation:** Add: "Rollback plan: defined before push (Dev provides rollback command/steps in Gate 3 Release Notes). CEO is authorized to call rollback within 24 hours of push if a P0 defect is confirmed."

---

## Suggested minimal diff direction

```diff
## Gate 0 — Intake (PM)
Required artifacts:
- Problem Statement (PM-owned; BA-reviewed where applicable)
- Acceptance Criteria
- Risk Assessment (initial)
+- BA Requirements Summary (required if BA seat is active for this module)

Exit criteria:
-- Scope and non-goals are explicit.
-- Permissions and failure modes are defined.
+- Scope and non-goals are explicit.
+- Acceptance criteria follow a verifiable format (Given/When/Then or equivalent); at least one failure-mode AC is included.
+- Permissions and failure modes are addressed in ACs or Risk Assessment: at least one AC per permission role (admin/authenticated/anonymous) and at least one failure-mode AC.

## Gate 2 — Verification (Tester)
...
Exit criteria:
-- Evidence attached.
-- Explicit APPROVE or BLOCK.
+- Each AC from Gate 0 is explicitly addressed in the Test Plan (verified, not-tested + rationale, or blocked).
+- Evidence attached.
+- Explicit APPROVE or BLOCK.

## Coordinated Release (CEO + Human owner)
...
+- Rollback plan: Dev provides rollback steps in Gate 3 Release Notes. CEO may call rollback within 24 hours of push if a P0 defect is confirmed.
```

---

## Follow-up inbox item (for executor to create)

**Destination:** `sessions/ceo-copilot/inbox/20260222-shipping-gates-ba-gaps-proposal/`

**`command.md`:**
```markdown
- command: |
    CEO review: shipping-gates.md BA gap proposal

    BA review of `runbooks/shipping-gates.md` identified 6 gaps from a requirements-quality perspective:
    1. BA requirements artifact missing from Gate 0 artifacts list
    2. Problem Statement authorship unattributed
    3. No AC quality bar at Gate 0
    4. No traceability requirement at Gate 2 (ACs → test cases)
    5. "Permissions and failure modes defined" not verifiable
    6. Coordinated Release has no rollback criteria

    Source: sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-18.md
    (includes full gap analysis + minimal diff)

    Requested action:
    - Review the 6 gaps and the suggested minimal diff above
    - Accept / modify / reject each proposed change
    - If accepted: apply diff to `runbooks/shipping-gates.md` (CEO-owned)
    - If rejected: document rationale for knowledgebase

    No PM involvement needed — this is a runbooks/process file owned by CEO.
    No urgency: no active feature is blocked. Next review window is fine.
```

**`roi.txt`:** `7`
```
