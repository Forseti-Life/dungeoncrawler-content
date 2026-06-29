# Problem Statement (PM-owned)

## Context
- What is changing? The dungeoncrawler release pipeline for `20260406-dungeoncrawler-release-next` shows KPI stagnation: release was activated at 2026-04-06T04:47Z with 10 claimed in_progress features, but of those 10, 5 were stale orphaned features from a prior release-b cycle (`Release: 20260319-dungeoncrawler-release-b`, already cleaned up this session). The remaining 5 in_progress features are tagged `Release: 20260406-dungeoncrawler-release-b` (the next queued release ID), not the ACTIVE release ID `20260406-dungeoncrawler-release-next`. Root cause: `pm-scope-activate.sh` was run when `release-b` was the active release, producing the wrong Release: field; no features were activated under the correct release-next ID. Result: 0 features properly scoped to release-next; pipeline cap counting is broken.
- Why now? Auto-close fires at 10 Gate 2 APPROVEs OR 24h elapsed (~2026-04-07T04:47Z, ~18h away). If the mis-tagging is not corrected, the release will close with 0 shipped features.

## Goals (Outcomes)
- Correct Release: field for the 4 valid in_progress features (background-system, character-class, heritage-system, skill-system) → `20260406-dungeoncrawler-release-next`.
- Reset `dc-cr-conditions` to `Status: ready` (not on release-next activation list).
- Release-next scoped count accurately reflects 4 (or more) features in progress.
- Dev-dungeoncrawler inbox items exist for these 4 features; QA suite-activate inbox items confirmed or re-issued.

## Non-Goals (Explicitly out of scope)
- Activating new features beyond correcting the 4 stale-tagged ones (release-b will be the next cycle).
- Deferred features.
- Forseti pipeline.

## Users / Personas
- PM-dungeoncrawler: needs accurate release-next scope count and valid dev/QA pipeline.
- Dev-dungeoncrawler: actively working on these features — no change to dev work, only release tagging.
- QA-dungeoncrawler: needs correct suite-activate coverage for release-next verification.

## Constraints
- Security: none.
- Performance: N/A.
- Accessibility: N/A.
- Backward compatibility: Feature.md field is metadata-only; no DB schema impact.

## Success Metrics
- `grep "Release:" features/dc-cr-background-system/feature.md` → `20260406-dungeoncrawler-release-next`
- Same for character-class, heritage-system, skill-system.
- `grep "Status:" features/dc-cr-conditions/feature.md` → `ready`
- `grep -rl "Status: in_progress" features/dc-*/ | xargs grep -l "Release: 20260406-dungeoncrawler-release-next" | wc -l` ≥ 4
- Dev-dungeoncrawler has active inbox items for all 4 features.

## Dependencies
- dev-dungeoncrawler: background-system, character-class, heritage-system, skill-system already in dev queue (from prior release-b scope).
- qa-dungeoncrawler: suite-activate items for these 4 features (may need re-issue if they were tied to old release-b).

## Risks
- If dev-dungeoncrawler has already completed implementation work for these features under release-b, they should transition to Gate 2 QA verify under release-next — no re-implementation needed.
- QA suite-activate inbox items for old release-b may have already run; re-issue to tie into release-next if Gate 2 hasn't started.

## Knowledgebase check
- `knowledgebase/lessons/` — prior stagnation (2026-03-22): testgen throughput was the bottleneck; here the bottleneck is Release: field mis-tagging from scope-activate running before active release was updated.
- Related: `sessions/pm-dungeoncrawler/artifacts/release-kpi-stagnation/` — prior instance for reference.
