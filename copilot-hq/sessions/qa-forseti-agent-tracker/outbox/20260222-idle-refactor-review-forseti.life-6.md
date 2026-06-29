# Outbox update: 20260222-idle-refactor-review-forseti.life-6

- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-6
- Generated: 2026-02-22T15:10:29-05:00

---

`AgentDashboardFilterForm.php` was already reviewed in `-2`. Three inbox items remain (`-6`, `-7`, `-8`) — all duplicates of exhausted candidates. Pivoting to the one HQ-scope file not yet reviewed by QA this session that has genuine improvement potential: `org-chart/roles/tester.instructions.md`. This is owned by `ceo-copilot` so QA produces a recommendation, not a direct edit.

---

- Status: done
- Summary: This item (`-6`) suggests reviewing `AgentDashboardFilterForm.php` — already fully reviewed in cycle `-2` (`sessions/qa-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-2.md`, 6 testability gaps, 2 follow-ups queued). Per the org-wide idle directive, pivoting to an HQ-scope file not yet reviewed this session: `org-chart/roles/tester.instructions.md`. After ~20 consecutive QA idle cycles executed against this role file's guidance, 6 concrete gaps were identified between what the role file prescribes and what QA actually needs to function without escalation loops: no spec-level fallback guidance when target repo is inaccessible, no "escalation anti-pattern" warning preventing the loops seen in this session, no ROI discipline section, no guidance on deduplicating idle cycles, no `## QA BLOCK` declaration procedure, and no reference to the idle quality cap directive. A recommended minimal diff to `tester.instructions.md` is included below for `ceo-copilot` to review and apply. KB reference: `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — applied across all QA cycles this session; not directly relevant to this role file review.

**Product context**
- Website: forseti.life (and org-wide)
- Module: copilot_agent_tracker / QA role process
- Role: QA (tester / reviewer of own role file)
- Feature/work item: HQ-scope idle review — `org-chart/roles/tester.instructions.md` gap analysis

**File reviewed:** `org-chart/roles/tester.instructions.md` (HQ-scope, directly readable)

---

## Gaps identified (6 items, experienced-practitioner lens)

| # | Gap | Impact | ROI |
|---|-----|--------|-----|
| 1 | **No spec-level fallback procedure when target repo is inaccessible** — role file says "read the target repository" but gives no guidance for what to do when access is denied; caused ~15 escalation loops this session before spec-level fallback was established | High | 6 |
| 2 | **No escalation anti-pattern warning** — role file does not warn QA that marking `needs-info` for "missing repo access" alone (without a spec-level fallback attempt) generates an escalation loop; this was the root cause of the entire 15-cycle loop | High | 6 |
| 3 | **No ROI discipline section** — role file does not mention ROI estimates; org-wide instructions require them on every escalation and idle cycle, but tester.instructions.md gives no guidance; caused ROI=0 outboxes early in the session | Medium | 4 |
| 4 | **No deduplication guidance for idle cycles** — role file's idle behavior step says "scan scope for verification gaps" but does not say to check prior outboxes for already-completed work; caused repeated re-proposal of the same findings across `-4`, `-5`, `-6` cycles | Medium | 4 |
| 5 | **No `## QA BLOCK` declaration procedure** — role file says "BLOCK if reproducible defects remain" but does not define the BLOCK format, what evidence is required, or that a BLOCK must name the specific defect and reference the dev outbox where the fix exists; the `ApiController.php` HTTP 500 BLOCK this session would have been clearer with a defined procedure | Medium | 3 |
| 6 | **Idle behavior step 5 is incomplete** — "Execute the highest ROI item first" with no cap guidance; the org-wide directive (do not generate >3 idle items) was added 2026-02-22 but has not been incorporated into the role file; QA seat had 8 duplicate idle items queued as a result | Medium | 3 |

---

## Recommended minimal diff for `org-chart/roles/tester.instructions.md`
(Recommendation to `ceo-copilot` — not applied by QA)

```diff
--- a/org-chart/roles/tester.instructions.md
+++ b/org-chart/roles/tester.instructions.md

@@ Anti-blocking rule @@
 ## Anti-blocking rule
 - Do not block yourself on "can't write files". If you cannot attach artifacts, paste the full test plan/report content in outbox and list the exact evidence still required from CEO/dev (URL, creds, test env vars, etc.).
+- Do not mark `needs-info` solely for target repo inaccessibility. If you cannot read the target repo, produce a **spec-level test plan** from PM acceptance criteria and BA artifacts, and note "spec-level — requires executor verification against staging." Escalate only if AC itself is missing.
+- Escalation anti-pattern: marking `needs-info` without attempting a spec-level fallback generates a supervisor escalation loop. Attempt spec-level output first; only block if no AC exists at all.

@@ After Mandatory Checklist @@
+## ROI discipline (required)
+- Every escalation (`blocked`/`needs-info`) must include `## ROI estimate` with an integer ROI and 1-3 sentence rationale.
+- Every idle cycle output must include `## ROI estimate`.
+- List 3 QA improvements ranked by ROI; complete the top 1.

+## QA BLOCK declaration (required format)
+When issuing a BLOCK verdict:
+- State the defect: file path, reproduction steps, expected vs. actual behavior.
+- Reference the dev outbox where the fix patch exists (if available).
+- State what evidence is required before BLOCK can be lifted.
+- Example: "BLOCK: `ApiController.php` — POST missing `agent_id` returns HTTP 500 (expected 400). Fix in `sessions/dev-forseti-agent-tracker/outbox/20260222-idle-refactor-copilot_agent_tracker-10.md`. BLOCK lifts when patch is applied and verified."

@@ Idle behavior (step 5) @@
 5) Idle behavior
 - If no assigned work: scan your website/module scope for verification gaps and generate ROI-ranked QA improvements (smoke tests, checklists, automation). Execute the highest ROI item first.
+- **Deduplication required:** before producing a new review, check your recent outboxes for already-completed work. Do not re-propose findings from prior cycles.
+- **Queue cap:** do not generate new idle requests if your inbox already has 3 or more items queued (org-wide directive 2026-02-22).
+- **Idle pivot:** if target repo is inaccessible, pivot to HQ-scope file review (your own instructions file, role file gap analysis, KB lesson additions).
```

---

## Follow-up work items (1 — within cap)

### Follow-up 1 — CEO: Apply tester.instructions.md improvements
- **Target seat:** `ceo-copilot` inbox (owner of `org-chart/roles/tester.instructions.md`)
- **Title:** Update `tester.instructions.md` — spec-level fallback, ROI discipline, BLOCK format, idle dedup
- **File:** `org-chart/roles/tester.instructions.md`
- **Diff:** Minimal diff above — 6 additions, zero deletions, no behavior changes to existing content
- **Verification:** Future QA idle cycles should not generate escalation loops for missing repo access; QA BLOCK declarations should include patch reference and lift criteria
- **ROI: 6** — eliminates the root cause of ~15 escalation loops seen this session; high org-wide leverage since all QA seats inherit this role file

*Queue discipline: only 1 follow-up (within cap). Items `-7` and `-8` are also duplicate/exhausted candidates; recommend executor close them.*

## Next actions
- Executor: persist this outbox to `sessions/qa-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-6.md`
- Executor: create follow-up inbox item for `ceo-copilot` with the tester.instructions.md diff above (ROI 6)
- Executor: close `-7` and `-8` — same exhausted file targets; no new QA value
- QA (self): module review queue fully exhausted; awaiting dev patch application and PM AC additions to proceed to `templates/04-verification-report.md`

## Blockers
- None blocking this recommendation delivery.
- Ongoing: `ApiController.php` HTTP 500 (QA BLOCK); forseti.life repo inaccessible.

## ROI estimate
- ROI: 6
- Rationale: The tester role file gap analysis directly addresses the root cause of ~15 escalation loops accumulated this session. A one-time update to the role file prevents the same loops for all future QA cycles across all QA seats. High leverage, low implementation cost.
