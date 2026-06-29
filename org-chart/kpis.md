# KPIs (Org + Role + Site)

This doc defines **measurable value-add metrics** for each organizational level and each role.

Principles:
- Prefer **leading indicators** (cycle time, verification latency, audit freshness) over vanity metrics (volume).
- Metrics must be **auditable from repo artifacts** (inbox/outbox/artifacts, dashboards, scoreboards).
- Track **few metrics that drive behavior**; use targets as guardrails, not punishments.

## Organizational levels

### Institution (org-wide)
**Value we add:** ship outcomes safely by keeping cycle time low, quality high, and costs bounded through clear ownership and strong verification.

**Cadence:** weekly review.

| KPI | Definition | Target | Primary evidence |
|---|---|---:|---|
| End-to-end cycle time (median) | Inbox item created → outbox `Status: done` | trending down | `sessions/*/inbox/**`, `sessions/*/outbox/**` |
| Blocked rate | % of items ending `blocked/needs-info` without a specific “Decision needed” + “Recommendation” | 0% | outbox format compliance |
| Escaped defects | Production/user-reported defects attributable to shipped changes | 0 | site scoreboards + PM notes |
| Post-merge regressions | Regressions found after merge | 0 | site scoreboards |
| Audit freshness | Age of `sessions/<qa-seat>/artifacts/auto-site-audit/latest/` outputs | <= 24h (or timer interval) | QA artifacts |
| Instruction friction closure | Recurring failure modes that end with an instruction/proposal/lesson | >= 1 when friction repeats | `knowledgebase/lessons/`, `knowledgebase/proposals/` |

### Site/Product (per website_scope)
**Value we add:** keep a product stable and improving by catching regressions early and tightening feedback loops.

**Cadence:** weekly scoreboard update in `knowledgebase/scoreboards/<site>.md`.

Recommended site KPIs (rolling 7 days):
- Post-merge regressions (count)
- Reopen rate (issues/PRs reopened)
- Time-to-verify (median) from “ready for QA” → QA APPROVE/BLOCK
- Escaped defects (count)
- Audit freshness (hours)

### Seat (per agent-id)
**Value we add:** execute one role within one scope reliably (high evidence, low churn, predictable throughput).

**Cadence:** daily (implicit via outbox); weekly rollup optional.

Seat KPIs are derived from:
- Timeliness: response time, cycle time, verify latency
- Quality: evidence completeness, false-positive rate, reopen/regression rate
- Flow: escalation quality, delegation quality

## Role KPIs

### CEO (Copilot Orchestration)
**Value I add:** maintain organizational throughput by preventing stalls/collisions, enforcing methodology, and keeping quality signals (QA/security/audits) flowing into prioritized work.

**Cost KPIs**
- Delegation completeness: % delegations including AC + verification + ROI (target: 100%; evidence: inbox item contents)
- Collision incidents: concurrent edits causing churn (target: 0; evidence: diff churn/postmortems)

**Quality KPIs**
- Escalation quality: % blocked/needs-info with Decision needed + Recommendation (target: 100%; evidence: outboxes)
- Audit uptime: fresh `auto-site-audit/latest` outputs where configured (target: 100%; evidence: QA artifact timestamps)

**Speed KPIs**
- Stale work %: items with no outbox update in 24h (target: 0%; evidence: `sessions/**` timestamps)
- Time-to-unblock (median): needs-info raised → decision provided (target: trending down; evidence: timestamps)

### Product Manager
**Value I add:** convert ambiguity into testable acceptance criteria, keep Dev/QA working on the highest-ROI items, and make ship/no-ship decisions with explicit risk.

**Cost KPIs**
- Reopen rate: reopened due to AC/scope gaps (target: < 10%; evidence: scoreboards/inbox history)
- Delegation churn: # times a task is re-delegated due to unclear scope (target: trending down; evidence: inbox threads)

**Quality KPIs**
- AC completeness: % items with testable AC + verification method (target: 100%; evidence: inbox artifacts)
- Risk closure: high/critical risks documented + accepted/mitigated (target: 100%; evidence: risk artifacts)

**Speed KPIs**
- Time-to-triage: new finding → delegated item with ROI (target: < 24h; evidence: timestamps)
- QA latency: Dev ready → QA APPROVE/BLOCK (target: median < 24h; evidence: timestamps)

### Business Analyst
**Value I add:** reduce rework by making requirements concrete (examples, edge cases, assumptions) before Dev/QA spend time.

**Cost KPIs**
- Rework avoidance: rework cycles attributable to missing requirements (target: trending down; evidence: reopen notes)
- Question quality: % questions that are answerable (specific, bounded) (target: trending up; evidence: outboxes)

**Quality KPIs**
- Example-driven specs: % outputs with concrete examples (URLs/inputs/outputs) (target: 100%; evidence: BA outboxes)
- Edge-case coverage: % outputs that list failure modes/permissions/data gaps (target: 100%; evidence: BA outboxes)

**Speed KPIs**
- BA cycle time: assignment → BA outbox `done` (target: trending down; evidence: timestamps)
- Time-to-clarify: question raised → answer integrated into spec/AC draft (target: trending down; evidence: timestamps)

### Software Developer
**Value I add:** implement the smallest safe diff that meets acceptance criteria with reproducible verification.

**Cost KPIs**
- Scope discipline: follow-ups logged instead of scope creep (target: trending up; evidence: outboxes)
- WIP control: # concurrent tasks per dev seat (target: 1; evidence: inbox state)

**Quality KPIs**
- Regression rate: regressions attributable to change (target: 0; evidence: scoreboards)
- Verification completeness: % dev outboxes with explicit verify commands/URLs (target: 100%; evidence: dev outboxes)

**Speed KPIs**
- Lead time: assigned → implemented + verified (target: trending down; evidence: outboxes/PRs)
- Time-to-first-safe-diff: assignment → first runnable change (target: trending down; evidence: commit timestamps)

### Tester (QA)
**Value I add:** independently verify acceptance criteria and failure modes with evidence, preventing escaped defects and regressions.

**Cost KPIs**
- False-positive rate: defects later invalidated (target: trending down; evidence: reopen notes)
- Retest churn: # repeated verification runs due to unclear steps (target: trending down; evidence: QA notes)

**Quality KPIs**
- Evidence quality: % reports with repro + expected/actual + artifacts (target: 100%; evidence: QA outboxes/artifacts)
- Escaped defects: defects found after ship that QA should have caught (target: 0; evidence: scoreboards)

**Speed KPIs**
- Time-to-verify: ready → APPROVE/BLOCK (target: median < 24h; evidence: timestamps)
- Audit freshness: `auto-site-audit/latest` age (target: <= timer interval; evidence: timestamps)

### Security Analyst
**Value I add:** surface credible risks early with practical mitigations and verification plans, reducing incident probability and blast radius.

**Cost KPIs**
- Mitigation adoption: % mitigations accepted/implemented (target: trending up; evidence: PM/dev follow-through)
- Review focus: % time spent on high-ROI surfaces (auth, PII, automation) (target: trending up; evidence: outboxes)

**Quality KPIs**
- Verification plans: % findings with concrete verification plan (target: 100%; evidence: security outboxes)
- Recurrence rate: same risk class repeats without guardrail (target: trending down; evidence: KB lessons/proposals)

**Speed KPIs**
- Time-to-signal: new change → security risk review summary (target: trending down; evidence: timestamps)
- Time-to-mitigation-decision: finding → PM decision (accept/mitigate) (target: trending down; evidence: timestamps)

## How we measure (minimum standard)
- Every outbox update already includes `Status`, `Summary`, Next actions, Blockers, and ROI.
- KPI rollups should be updated weekly in:
  - `knowledgebase/scoreboards/<site>.md` (site-level)
  - Optional: a CEO weekly rollup in the master session.
