# Problem Statement (PM-owned)

## Context
- What is changing? The dungeoncrawler release pipeline has stalled: 9 features in `in_progress`, 6 in `ready`, and 0 features at `done`. Three root causes identified (2026-03-22 signal): (1) QA testgen throughput — 12 test-plan generation items queued in qa-dungeoncrawler inbox since 2026-03-20 with 0 test plans returned; (2) feature status drift — `dc-cr-character-leveling` dev-done but feature.md still shows `ready`; (3) QA production audit false positives — 30 failures from dev-only module routes not deployed to production, blocking a clean gate signal.
- Why now? No features have reached `done` through the full pipeline (dev → QA verify → done). Time-to-verify KPI is N/A (no data). If testgen throughput stays at zero, QA cannot verify dev work, and the pipeline will remain stalled indefinitely. This was first observed 2026-03-22 and escalated to CEO for testgen throughput; this item captures the full stagnation picture for PM tracking.

## Goals (Outcomes)
- Unblock QA testgen: at least 8 of the 12 queued testgen items produce test plans within the next release cycle.
- Achieve first feature `done`: at least 1 dungeoncrawler feature transitions from dev-complete → QA APPROVE → `done` status.
- Clean production audit: qa-dungeoncrawler qa-permissions.json fix applied; production audit shows 0 "other failures".
- Fix feature status drift: `dc-cr-character-leveling` updated to `in_progress` (dev done; awaiting QA).

## Non-Goals (Explicitly out of scope)
- Deferred features (24 features with status `deferred`) — not part of this cycle.
- New feature scoping or reprioritization beyond what's already `ready`/`in_progress`.
- Forseti pipeline (separate scope).

## Users / Personas
- PM-dungeoncrawler: needs accurate feature status and clear pipeline signal.
- QA-dungeoncrawler: needs testgen throughput restored to generate test plans.
- Dev-dungeoncrawler: needs clean QA audit (no false positives) and test plans to verify against.

## Constraints
- Security: none for this item.
- Performance: QA audit must run against localhost:8080 (not production) per site.instructions.md.
- Accessibility: N/A.
- Backward compatibility: N/A.

## Success Metrics
- QA testgen: >= 8 of 12 queued items produce outbox with test plan by end of next cycle.
- Feature completion: >= 1 feature reaches status `done`.
- Audit: production audit run post-fix shows 0 "other failures (4xx/5xx)" in findings-summary.json.
- Status drift: `dc-cr-character-leveling` feature.md status = `in_progress` (updated this cycle).

## Dependencies
- CEO decision on QA testgen throughput escalation (GAP-DC-01, active since 2026-03-22).
- qa-dungeoncrawler: apply 2-rule qa-permissions.json fix (dev outbox `20260322-193507-qa-findings-dungeoncrawler-30` has the diff).
- dev-dungeoncrawler: complete permission regression fix (`20260322-142611-qa-findings-dungeoncrawler-1`, ROI=9).

## Risks
- If QA testgen throughput cannot be restored (executor capacity), features will continue to pile up in `in_progress` with no verification path. Risk: pipeline remains stalled for another full cycle.
- If QA audit false positives are not fixed before next release preflight, Gate 2 will BLOCK on noise.

## Knowledgebase check
- `knowledgebase/lessons/` — QA automation generated 50+ duplicate inbox items for same failure (fixed). Lesson: confirm qa-permissions.json coverage before each release preflight.
- `knowledgebase/scoreboards/dungeoncrawler.md` — baseline set 2026-02-27; time-to-verify still N/A as of 2026-03-22.
