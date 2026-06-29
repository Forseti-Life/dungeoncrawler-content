# Roadmap Audit — Complete
**Date:** 2026-04-07  
**Seat:** ceo-copilot-2  
**Supervised by:** Board (Keith)

## Summary

Full roadmap audit completed for DungeonCrawler. All 240 pending DB sections are now mapped to named feature stubs in the pipeline.

## Coverage

| Book | Sections | Status |
|---|---|---|
| apg | 85 | ✅ 100% covered |
| b1 / b2 / b3 | 16 | ✅ covered (deferred) |
| core ch03–ch11 | 84 | ✅ 100% covered |
| core ch01 (ancestries) | 1 | 🚧 blocked — DB gap |
| gmg | 23 | ✅ 100% covered |
| gam / gng / som | 32 | ✅ covered (deferred) |
| **Total** | **241** | **240 covered, 1 blocked** |

## Work Done

- Hardened runbook `runbooks/roadmap-audit.md` (4 passes: baseline, structural gaps, QA format, one-at-a-time discipline + dependency mapping)
- Backfilled 60+ feature files with required schema (Work item id, DB sections, Depends on, owners)
- Created new features: `dc-cr-human-ancestry`, `dc-apg-ancestries` (backfilled), all APG class/archetype stubs
- Fixed naming bug: `dc-gam-guns-gears`/`dc-gng-lost-omens` had swapped content — corrected and renamed
- Dispatched 2 dev tasks:
  - `20260407-feature-id-column-dc-requirements` (ROI 9) — add feature_id column to DB
  - `20260407-load-core-ancestry-requirements` (ROI 8) — load core/ch01-ch02 ancestry rows

## Open Blockers

1. **DB gap — ancestry chapters**: `dc_requirements` has zero rows for `core/ch01` and `core/ch02`. Dev task dispatched. Blocks: human ancestry audit + all other core ancestry/heritage sections.
2. **feature_id column missing**: Coverage is tracker-based until dev delivers the DB column task. Machine-verifiable coverage requires that column.

## Artifact

`sessions/pm-dungeoncrawler/artifacts/roadmap-audit-20260407.tsv` — full section-to-feature mapping.

## Next Recommended Actions

1. Dev delivers `feature_id` DB column → run `roadmap-set-feature` drush commands to link implemented sections
2. Dev delivers ancestry DB rows → re-audit core/ch01-ch02
3. PM grooms deferred features (gam, gng, som, bestiaries) at next release cycle
4. P1 class features (Barbarian, Rogue) — dispatch to dev after Fighter ships
