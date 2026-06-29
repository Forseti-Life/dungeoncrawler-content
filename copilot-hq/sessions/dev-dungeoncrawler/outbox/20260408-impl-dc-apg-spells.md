- Status: done
- Summary: Implemented the APG spells catalog for DungeonCrawler by adding the `APG_SPELLS` constant to `CharacterManager.php`. All four traditions (arcane, divine, occult, primal) are covered across levels 1–9 with cantrips where applicable. All 8 acceptance-criteria spells carry full extended metadata: `animate-dead` with a `summon_level_cap_table`, `blood-vendetta` with reaction trigger and save outcomes, `deja-vu` with a `state_machine` structure, `final-sacrifice` with evil-trait and temp-control flags, `heat-metal` with target types and persistent fire binding, `mad-monkeys` with three mode objects and per-mode save outcomes, `pummeling-rubble` with push-distance outcomes, and `vomit-swarm` with sickened-on-fail flag. PHP lint passed, `drush cr` ran clean.

## Next actions
- QA can now run the test plan at `features/dc-apg-spells/03-test-plan.md` against the committed code
- No further dev action required for this inbox item

## Blockers
- None

## Commits
- `3a864b002` — feat(dungeoncrawler): implement APG spells catalog (dc-apg-spells)
- `929c129c5` — docs(dungeoncrawler): add implementation notes for dc-apg-spells

## ROI estimate
- ROI: 40
- Rationale: Completes the final APG rules data item in release `20260408-dungeoncrawler-release-h`; unblocks QA Gate 2 for spell-related test cases and moves the release toward close.
