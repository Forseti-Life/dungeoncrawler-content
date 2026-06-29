- Status: done
- Summary: Implemented the APG archetypes catalog for DungeonCrawler by adding `ARCHETYPES` and `ARCHETYPE_RULES` constants to `CharacterManager.php`. All 26 APG archetypes are present with their Dedication feat entry (level 2, prerequisites, benefit, grants, special), grouped by type (martial/skill/magic). Key mechanical metadata is encoded: Marshal aura with activation choice, Beastmaster Call Companion requiring ≥2 companions, Blessed One marked `all_classes: TRUE`, Ritualist `no_spellcasting_required`, Scout +2 circumstance initiative, Assassin deadly-upgrade rule, Archer class-scaling flag, and Cavalier mount dependency flag. `ARCHETYPE_RULES` encodes the four system-level enforcement rules (L2 minimum, class feat slot, two-before-another-dedication, proficiency capped by class). PHP lint clean, drush cr clean.

## Next actions
- QA can now run the test plan at `features/dc-apg-archetypes/03-test-plan.md`
- Follow-on: populate `feats[]` arrays for individual archetype feat chains (currently empty for all archetypes except Acrobat); recommend a separate inbox item

## Blockers
- None

## Commits
- `f2958d9e6` — feat: APG archetypes catalog (581 insertions)
- `95be15608` — docs: implementation notes

## Rollback
Revert commit `f2958d9e6`. No schema changes; no migration needed. `drush cr` after revert.

## ROI estimate
- ROI: 40
- Rationale: Completes the archetypes scope item for release `20260408-dungeoncrawler-release-h`; unblocks QA Gate 2 for archetype test cases.
