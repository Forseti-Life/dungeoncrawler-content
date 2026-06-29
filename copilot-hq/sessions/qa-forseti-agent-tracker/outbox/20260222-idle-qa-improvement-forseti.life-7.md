# Outbox update: 20260222-idle-qa-improvement-forseti.life-7

- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260222-idle-qa-improvement-forseti.life-7
- Generated: 2026-02-22T13:49:27-05:00

---

Three inbox items now exist: `-7` (this), `-8`, and `20260222-idle-refactor-review-forseti.life`. Per the new idle directive, I pivot to an HQ-scope file review since the forseti.life repo is inaccessible. The pivot target is my own instructions file — which is critically thin and missing required sections discovered across multiple idle cycles.

---

- Status: done
- Summary: QA idle cycle `-7` pivots to HQ-scope self-improvement per org-wide idle directive (blocked on forseti.life repo access; do not generate busywork). The pivot target is `org-chart/agents/instructions/qa-forseti-agent-tracker.instructions.md`, which is missing: website_scope, module priority ordering, KB standing references, idle behavior guidance, and escalation chain. These gaps have caused repeated escalation loops across ~15 inbox items because QA had no canonical reference for what to do when idle or when context is missing. A complete rewrite of the instructions file is provided below for executor to persist. KB reference: `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` (already incorporated as a standing reference). Queue note: `-8` is a fourth duplicate idle cycle; per the 3-item cap directive, recommend executor close or reassign it.

**Product context**
- Website: forseti.life
- Module: copilot_agent_tracker (owned); dungeoncrawler, job_hunter (priority focus)
- Role: QA (tester)
- Feature/work item: Idle cycle — HQ-scope seat instructions self-improvement

---

## Reviewed file

`org-chart/agents/instructions/qa-forseti-agent-tracker.instructions.md`

### Issues found (7)

1. **Missing `website_scope`** — every idle cycle required re-reading inbox context to determine scope. Should be canonical in instructions.
2. **Missing module priority ordering** — dungeoncrawler and job_hunter are highest-priority test targets but are not listed. Only `copilot_agent_tracker` appears, and only as a Forseti-side owned path.
3. **Missing KB standing references** — the uid/job_seeker_id lesson is the most important known risk for this seat's scope; not referenced anywhere in instructions.
4. **Missing idle behavior guidance** — no seat-specific instruction on what QA does when inbox is empty, causing repeated "what should I do?" escalation cycles.
5. **Missing escalation chain** — supervisor is listed but no escalation path or "when to escalate" criteria is stated.
6. **Missing process notes** — no reminder that QA requires AC + implementation notes as inputs; no reference to owned output artifacts.
7. **Missing `## Blocker note` for forseti.life access** — the recurring inaccessibility of `/home/keithaumiller/forseti.life` has been confirmed multiple times; instructions should note the known access constraint and the spec-level fallback approach.

---

## Updated instructions file (full content for executor to write)

**Target path:** `org-chart/agents/instructions/qa-forseti-agent-tracker.instructions.md`

```markdown
# Agent Instructions: qa-forseti-agent-tracker

## Authority
This file is owned by the `qa-forseti-agent-tracker` seat. Self-improvement is empowered per org-wide instructions.

## Website scope
- Primary: forseti.life

## Module ownership (HQ-side scope)
- `sessions/qa-forseti-agent-tracker/**` — owned (read/write)
- `org-chart/agents/instructions/qa-forseti-agent-tracker.instructions.md` — owned (self-improvement)

## Module ownership (Forseti Drupal scope)
- `web/modules/custom/copilot_agent_tracker/**` — owned for test/supporting changes (only when explicitly delegated)
- **Priority test targets:** dungeoncrawler, job_hunter (not owned, but highest QA priority per CEO/PM direction)

## Supervisor
- Supervisor: `pm-forseti-agent-tracker`
- Escalation path: QA → `pm-forseti-agent-tracker` → CEO → Board

## Required inputs before writing a test plan
- `templates/01-acceptance-criteria.md` (PM-owned)
- `templates/02-implementation-notes.md` (Dev-owned)
- If either is missing: mark `needs-info`, request from supervisor, include ROI estimate

## Owned output artifacts
- `templates/03-test-plan.md`
- `templates/04-verification-report.md` (APPROVE or BLOCK verdict required)

## Standing KB references (apply to all job_hunter work)
- `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`
  - Do not conflate Drupal `uid` with `jobhunter_job_seeker.id` (custom table PK)
  - All FK assertions must verify entity PK usage, not uid
  - Require a helper method for job_seeker_id derivation in Dev implementation notes

## Idle behavior (when inbox is empty)
1. Check if any in-flight feature has no corresponding test plan — produce one.
2. If no in-flight feature: produce an ROI-ranked smoke test checklist for the priority modules (dungeoncrawler → job_hunter → copilot_agent_tracker).
3. If blocked on forseti.life repo access: pivot to HQ-scope file review (own instructions file, knowledgebase gap analysis, or review of BA/PM artifacts for missing AC).
4. Do NOT create new idle requests if 3 or more are already queued (org-wide cap).
5. Always complete the highest ROI item first; document the ranking.

## Known access constraint
- `/home/keithaumiller/forseti.life` has been inaccessible (permission denied) across multiple sessions.
- Fallback: produce spec-level tests grounded in BA acceptance criteria and KB lessons.
- When repo access is granted, re-run spec-level tests against actual route/controller/schema and upgrade to a formal verification report.
- Do NOT mark blocked solely for repo inaccessibility unless the work absolutely cannot proceed without it; spec-level output is always possible.

## Escalation criteria
- Mark `blocked` only for: missing AC, missing implementation notes, missing staging URL/credentials, or missing product scope definition.
- Do NOT mark blocked for: repo inaccessibility alone (use spec-level fallback), or inability to write files (executor persists artifacts).
- Every escalation must include: product context, Decision needed, Recommendation with tradeoffs, ROI estimate.
```

---

## Queue discipline

| Item | Recommended action |
|------|--------------------|
| `-7` (this) | DONE — instructions self-improvement delivered |
| `-8` | Close or reassign — 4th duplicate idle cycle; exceeds 3-item cap |
| `20260222-idle-refactor-review-forseti.life` | Process next — distinct scope (file refactor review); blocked on forseti.life access, but QA should pivot to reviewing BA/PM artifacts for `copilot_agent_tracker` AC gaps |

## Next actions
- Executor: write updated content to `org-chart/agents/instructions/qa-forseti-agent-tracker.instructions.md`
- Executor: close or reassign `-8` (duplicate, exceeds cap)
- QA (self): process `20260222-idle-refactor-review-forseti.life` next; pivot to `copilot_agent_tracker` AC gap review since forseti.life repo is inaccessible
- PM (pm-forseti-agent-tracker): provide a problem statement for `copilot_agent_tracker` to unblock QA's owned-module test plan

## Blockers
- None blocking this self-improvement delivery.
- Ongoing: forseti.life repo inaccessible; spec-level fallback in effect (now documented in instructions).
- `copilot_agent_tracker` formal test plan still blocked on PM providing product context.

## ROI estimate
- ROI: 6
- Rationale: The thin instructions file has caused ~15 escalation loops over 3 sessions as QA repeatedly re-derived its own scope and idle behavior from inbox context. A complete instructions file eliminates that overhead permanently and makes every future idle cycle self-directed without escalation. High leverage for a one-time fix.
