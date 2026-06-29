- Status: done
- Summary: Implemented all APG ancestry mechanics for DungeonCrawler. Enriched the five APG ancestry records (Catfolk, Kobold, Orc, Ratfolk, Tengu) with `special` arrays covering land-on-your-feet, draconic_exemplar, and sharp_beak. Added the new `KOBOLD_DRACONIC_EXEMPLAR_TABLE` constant with all 10 dragon types. Updated all five APG ancestry heritage entries in HERITAGES with full mechanical data (unarmed attacks, resistances, special rules) and corrected heritage lists per the AC (Catfolk nine-lives replaces winter; Kobold spellscale/strongjaw/venomtail replace dracomancer/spelunker; Orc Grave heritage added; Tengu stormtossed/taloned replace dogtooth/mountainkeeper). Added the new `VERSATILE_HERITAGES` constant with all five heritages (Aasimar, Changeling, Dhampir, Duskwalker, Tiefling), sense upgrade rule, Uncommon gating, and per-heritage feats. Added three APG backgrounds (haunted, fey_touched, returned) including auto-grant for Diehard. Updated TRAIT_CATALOG with five new versatile heritage traits. PHP lint clean, drush cr clean.

## Next actions
- QA can now run the test plan at `features/dc-apg-ancestries/03-test-plan.md`
- No further dev action required for this inbox item

## Blockers
- None

## Commits
- `3c5ee2838` — feat: APG ancestries, versatile heritages, and backgrounds (415 insertions, 31 deletions)
- `cd3daaeb8` — docs: implementation notes

## Rollback
Revert commit `3c5ee2838`. No schema changes; no migration needed. `drush cr` after revert restores cache.

## ROI estimate
- ROI: 40
- Rationale: Completes the ancestries scope item for release `20260408-dungeoncrawler-release-h`; unblocks QA Gate 2 for ancestry/heritage test cases.
