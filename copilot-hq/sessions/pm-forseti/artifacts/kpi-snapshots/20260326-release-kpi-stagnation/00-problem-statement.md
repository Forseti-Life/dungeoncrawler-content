# Problem Statement (PM-owned)

## Context
- **What is changing?** Scoreboards for forseti.life and dungeoncrawler have not been updated since 2026-02-28 — a 26-day gap. During this period, two additional releases shipped (2026-03-22 coordinated release: `forseti-release-next` + `dungeoncrawler-release-next`), and two active process gaps were identified (GAP-DC-STALL-01: QA fix-pickup failure; GAP-DC-01: QA testgen throughput bottleneck). None of these are reflected in the scoreboards.
- **Why now?** The `20260326-dungeoncrawler-release-b` cycle is starting. Release-readiness decisions for the next cycle must be grounded in current quality signal, not month-old data. The 4-day stall on `20260322-dungeoncrawler-release-b` (GAP-DC-STALL-01) and the QA testgen bottleneck (GAP-DC-01) are both active risks that lack scoreboard visibility.

## Goals (Outcomes)
- Scoreboards for forseti.life and dungeoncrawler are updated to reflect all releases shipped since 2026-02-28.
- Active process gaps (GAP-DC-STALL-01, GAP-DC-01) and their status are recorded in the scoreboard.
- Weekly cadence resumed; scoreboards are ≤ 7 days stale going forward.

## Non-Goals (Explicitly out of scope)
- This does not fix the underlying gaps (GAP-DC-STALL-01, GAP-DC-01) — those are separate escalations already in flight.
- Not a KPI redesign or new metric addition.

## Users / Personas
- pm-forseti and pm-dungeoncrawler: primary consumers of scoreboard data for release-readiness signoff.
- ceo-copilot: uses scoreboards for escalation triage and cycle health assessment.

## Constraints
- Security: none
- Performance: none
- Accessibility: none
- Backward compatibility: scoreboards are append-only by convention; add new sections, do not delete history.

## Success Metrics
- Both scoreboards updated with ≥1 entry covering the 2026-03-22 coordinated release outcomes.
- GAP-DC-STALL-01 and GAP-DC-01 appear in the dungeoncrawler scoreboard guardrails/failure-modes sections.
- Next scheduled scoreboard update no more than 7 days after this one.

## Dependencies
- Gate R5 audit evidence from `20260322-post-push-20260322-dungeoncrawler-release.md` (committed `ca3c9279a`).
- QA outbox evidence for the 2026-03-22 release (qa-forseti and qa-dungeoncrawler).

## Risks
- Without current scoreboard data, pm-forseti cannot assess whether the 3-unclean-releases escalation trigger has been reached for either site.
- GAP-DC-STALL-01 has no scoreboard visibility; if it recurs it will appear novel rather than as a repeat pattern.

## Knowledgebase check
- Related lessons: see `knowledgebase/lessons/` — no specific "stale scoreboard" lesson found, but `knowledgebase/proposals/` has instructions-change proposals from 2026-02-28 cycle that prompted scoreboard creation. None found addressing scoreboard cadence enforcement.
