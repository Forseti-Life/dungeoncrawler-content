- Status: done
- Summary: Replaced the stub Investigator class entry in `CLASSES['investigator']` with full APG mechanics. The class record now includes correct proficiencies (Expert Perception/Reflex/Will, Trained Fortitude), armor/weapon training (light armor + rapier), and skill count formula (4 + Int + Society + methodology bonus). Four core abilities are fully encoded: Devise a Stratagem (Fortune trait, stored d20 roll, Int-based attack modifier, free-action reduction when targeting an active lead, discard rules), Pursue a Lead (max 2 leads, oldest-removed-at-cap, 1-min exploration), Clue In (Reaction, share circumstance bonus within 30 ft), and Strategic Strike (precision damage, no-stack rule, 5-step progression L1–L17). All four methodologies (Alchemical Sciences, Empiricism, Forensic Medicine, Interrogation) are implemented with their granted feats, skill proficiencies, and methodology-specific mechanics. All AC edge cases are encoded as explicit data keys. PHP lint clean, drush cr clean.

## Next actions
- QA can now run the test plan at `features/dc-apg-class-investigator/03-test-plan.md`
- No further dev action required for this inbox item

## Blockers
- None

## Commits
- `da945aec3` — feat: Investigator class mechanics (136 insertions, 6 deletions)
- `c8ec77131` — docs: implementation notes

## Rollback
Revert commit `da945aec3`. No schema changes; no migration needed. `drush cr` after revert.

## ROI estimate
- ROI: 40
- Rationale: Completes the Investigator class scope item for release `20260408-dungeoncrawler-release-h`; unblocks QA Gate 2 for Investigator test cases.
