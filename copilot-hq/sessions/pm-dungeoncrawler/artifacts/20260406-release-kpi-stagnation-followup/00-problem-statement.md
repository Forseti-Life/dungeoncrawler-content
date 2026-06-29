# Problem Statement (PM-owned)

## Context
- What is changing? This is a follow-up to `20260406-release-kpi-stagnation` (artifacts: `sessions/pm-dungeoncrawler/artifacts/20260406-release-kpi-stagnation/`). That item identified and fixed a Release: field mis-tagging: 5 in_progress features were tagged `Release: 20260406-dungeoncrawler-release-b` instead of `Release: 20260406-dungeoncrawler-release-next`. After correction (commit `f0d240c1`), 4 features are properly scoped to release-next (background-system, character-class, heritage-system, skill-system). Dev outbox confirms all 4 are implemented; QA has suite-activate inbox items for all 4. Root cause of the mis-tagging: `pm-scope-activate.sh` was run during a transition window when release-b was still the active release ID — the script correctly stamped release-b at the time; the active release was later changed to release-next without re-running activation. This represents a **process gap**: no procedure ensures features are re-tagged when the active release ID changes mid-cycle.
- Why now? Release-next 24h auto-close fires at ~2026-04-07T04:47Z (approx. 18h from now). With 4 dev-complete features and QA suite-activate items queued, there is a viable path to Gate 2 APPROVE before close. The process gap must be documented to prevent recurrence in release-b.

## Goals (Outcomes)
- Gate 2 QA verification in progress for all 4 release-next features before auto-close.
- Process gap documented and encoded in seat instructions: "run pm-scope-activate.sh only after active release ID is confirmed."
- Lesson learned written to knowledgebase.
- release-b activation plan confirmed: 8 ready features (4 P0 foundation resets from `0abb2db8` + 4 others).

## Non-Goals (Explicitly out of scope)
- New feature scoping (release-next is at cap).
- Infrastructure/Forseti pipeline.
- Features beyond the 4 currently scoped to release-next.

## Users / Personas
- pm-dungeoncrawler: needs clean Gate 2 signal before 24h close and process gap closed.
- qa-dungeoncrawler: needs to run suite-activate and Gate 2 verification for 4 features.
- dev-dungeoncrawler: all 4 features confirmed implemented; no dev work needed.

## Constraints
- Security: none.
- Performance: N/A.
- Accessibility: N/A.
- Backward compatibility: metadata-only changes; no DB schema impact.

## Success Metrics
- `grep -rl "Status: in_progress" features/dc-*/ | xargs grep -l "Release: 20260406-dungeoncrawler-release-next" | wc -l` = 4 (already met).
- QA Gate 2 APPROVE evidence exists in `sessions/qa-dungeoncrawler/outbox/` for at least 1 release-next feature before auto-close.
- Lesson learned committed to `knowledgebase/lessons/`.
- Seat instructions updated with pm-scope-activate timing constraint.

## Dependencies
- qa-dungeoncrawler: suite-activate inbox items already queued (20260406-052034-suite-activate-*); must be executed before 24h close.
- dev-infra: `20260406-orchestrator-age-empty-release-guard` (ROI 30) provides backstop if close fires before Gate 2 APPROVEs.

## Risks
- If 24h close fires before any Gate 2 APPROVE, release-next closes empty. Backstop: empty-release guard (dev-infra item pending). Mitigation: QA must prioritize these 4 suite-activates now.
- If QA suite-activate items for old release-b are duplicated, QA may have stale items. Risk: very low — inbox items exist for current release-next dates.

## Knowledgebase check
- `sessions/pm-dungeoncrawler/artifacts/20260406-release-kpi-stagnation/` — root cause analysis and fix (Release: field mis-tagging, commit `f0d240c1`).
- `sessions/pm-dungeoncrawler/artifacts/release-kpi-stagnation/` and `release-kpi-stagnation-followup/` — prior cycle (2026-03-22/26): testgen throughput was the bottleneck then; now bottleneck is timing/tagging process gap.
