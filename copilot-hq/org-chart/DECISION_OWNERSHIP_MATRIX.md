# Organizational Decision & Ownership Matrix

## Authority
- This document is owned by the `ceo-copilot` seat.

Purpose: define who is expected to resolve which issue types independently, and when escalation is required.

Use this with:
- `runbooks/shipping-gates.md`
- `runbooks/conflict-resolution.md`
- `runbooks/passthrough-request.md`

## How to use
- Step 1: Match the work item to an issue type in the matrix below.
- Step 2: Follow the owner (`A`) and executor (`R`) columns.
- Step 3: If the escalation trigger is met, escalate immediately using the standard heading (`## Needs from Supervisor`, `## Needs from CEO`, or `## Needs from Board`).

## Quick triage flow (30-second classifier)
1. Is this a security/privacy or production-impacting issue?
	- Yes → Escalate immediately per matrix and start containment.
	- No → continue.
2. Is the change inside your owned scope and issue type clearly mapped?
	- Yes → resolve directly and record verification evidence.
	- No → continue.
3. Is this a scope/intent/acceptance ambiguity?
	- Yes → PM decision (or supervisor PM if cross-owner deadlock).
	- No → continue.
4. Is this ownership boundary/cross-module conflict?
	- Yes → passthrough to owning PM/agent; escalate if unresolved after one cycle.
	- No → continue.
5. Is this an access/env/credentials blocker?
	- Yes → escalate after one execution cycle with explicit Decision needed + Recommendation.

## RACI legend
- `R` = Responsible (does the work)
- `A` = Accountable (final decision owner)
- `C` = Consulted (must be consulted before decision)
- `I` = Informed (notified after decision)

## Issue-type decision matrix (org-wide)

| Issue type | R | A | C | I | Resolve without escalation? | Escalate when |
|---|---|---|---|---|---|---|
| Code defect in owned module | Dev | PM | QA | PM | Yes | Requires out-of-scope file edits or policy exception |
| Failing QA check with known fix in owned module | Dev | PM | QA | PM | Yes | Same failure repeats without progress across cycles |
| Acceptance criteria ambiguity / product intent conflict | PM | PM | Dev, QA | PM | Yes | Tradeoff impacts scope, deadline, or risk posture |
| Cross-module dependency or ownership boundary conflict | Owning PM + Owning Dev | PM (if unresolved) | Requesting PM, QA | Affected teams | No (if unresolved in one handoff) | No agreement after one passthrough cycle |
| Security/privacy finding (authz, data exposure, secrets) | Dev | Security Analyst + PM | QA | Human owner (as needed) | Limited | Severity high/critical, unclear mitigation, or compliance impact |
| Release gate failure (QA BLOCK at Gate 2/4) | Dev (fix), QA (verify) | PM | — | Human owner (if persistent) | Yes (single-cycle fix path) | 3 unclean cycles, repeated identical failures, or no viable rollback |
| Production outage / severe regression | Dev | PM | QA, Security | Human owner | No | Immediate human escalation after containment starts |
| Missing access/credentials/environment path | Agent owning task | PM | — | Affected team | No | Blocker persists >1 execution cycle |
| Process/rule ambiguity (who decides?) | Current seat | PM | Relevant role owner | Team | No | Any ambiguity that blocks execution >1 cycle |
| Coordinated release go/no-go (multi-site) | PMs + Dev + QA | Release operator PM (`pm-forseti`) | — | Human owner | No | Any required signoff missing or rollback plan incomplete |

## Role autonomy matrix (what each role should resolve on their own)

| Role | Expected to resolve independently | Must escalate |
|---|---|---|
| CEO | Queue orchestration, conflict triage, sequencing, escalation routing | Human-owner decision, risk acceptance outside policy, external access constraints |
| PM | Scope clarifications, acceptance criteria updates, release readiness recommendation, triage prioritization | Cross-owner deadlocks, policy exceptions, repeated unclean-release pattern |
| Dev | Implementation in owned scope, tests, rollback notes, reproducible fix proposals | Out-of-scope ownership, missing env/access, unresolved product intent questions |
| QA | Verification, PASS/BLOCK evidence, reproducible defect reports, regression signal | Scope/intent decisions, risk acceptance, repeated failed-fix loops per policy |
| BA | Requirement decomposition, traceability, clarification packs | Conflicting business goals needing prioritization decision |
| Security Analyst | Security triage, risk classification, mitigation recommendations | Risk acceptance or business tradeoff requiring PM/CEO/human owner |
| Capability agents (`agent-explore`, `agent-code-review`, `agent-task-runner`) | Discovery, review, execution support artifacts | Any ownership decision or policy exception |

## Escalation triggers (standard)
- Repeated blocked/needs-info with no decision after 1 cycle: escalate to supervisor.
- Three consecutive blocked/needs-info outcomes on same flow: escalate to supervisor’s supervisor.
- Security/compliance or production-impacting risk: escalate immediately to CEO and, when needed, human owner.

## Required escalation payload
Every escalation should include:
- Matrix issue type: `<exact issue type row from this document>`
- Decision needed
- Recommendation (with tradeoffs)
- Evidence links (artifact paths / failing checks)
- ROI estimate

This keeps escalations actionable and prevents “stalled but undocumented” loops.
