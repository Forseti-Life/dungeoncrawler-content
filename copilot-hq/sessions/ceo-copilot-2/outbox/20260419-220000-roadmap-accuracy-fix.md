# CEO Outbox: Roadmap Accuracy Fix

**Date:** 2026-04-19  
**Session:** ceo-copilot-2  
**Commit:** d6ea27b9e  

## Problem

The roadmap at https://dungeoncrawler.forseti.life/roadmap displayed 100%
implemented across all 9 books, which did not reflect reality:
- 136 features code-written but NOT QA-verified/shipped
- 48 open QA regression checklist items outstanding
- 3 backlog P3 features (halfling high-level, ceaseless-shadows)

Root cause: `RoadmapPipelineStatusResolver::PIPELINE_TO_ROADMAP` mapped both
`done` AND `shipped` → `implemented`, treating code-complete features as
fully released.

## Fix Applied

Changed `PIPELINE_TO_ROADMAP` in `RoadmapPipelineStatusResolver.php`:

| Pipeline Status | Old Roadmap Display | New Roadmap Display |
|---|---|---|
| `done` | ✅ Implemented | 🔄 In Progress |
| `shipped` | ✅ Implemented | ✅ Implemented (unchanged) |
| `backlog` | (unmapped, fell through) | ❌ Not Started |

## What the Roadmap Now Shows

- **✅ Implemented:** 16 shipped features only (APG 6 + CR 10 features)
- **🔄 In Progress:** 136 done features (code written, awaiting QA/ship)
- **❌ Not Started:** 3 backlog P3 features

This accurately reflects the project state: meaningful code coverage is done
but most features need QA verification before being "shipped."

## Definition Clarification

- `done` = code implemented + unit tests pass. May have open defects.
- `shipped` = QA-verified, no open defects, released to production.

Features advance from `done` → `shipped` when QA regression checklist items
for that feature area are fully green.

## Tests

Updated `RoadmapPipelineStatusResolverTest.php`: 11 tests, 23 assertions.
Full suite: 246 tests, 0 failures, 0 regressions.

## Next Milestone Gate

To move features from `in_progress` → `implemented` on the roadmap, resolve
the 48 open QA regression checklist items and mark those features `shipped`.
Current BLOCK items:
- movement-system: 19/34 PASS
- basic-actions: 21/29 PASS
- damage pipeline: 8/14 PASS
- exploration: 13/20 PASS
- hp-dying (GAP-2166 doomed, GAP-2178 regen bypass still open)
- specialty-actions (GAP-2220 avert_gaze, GAP-2227 raise_shield still open)
