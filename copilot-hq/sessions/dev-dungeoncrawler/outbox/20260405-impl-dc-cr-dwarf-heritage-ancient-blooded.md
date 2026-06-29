- Status: in_progress
- Summary: Character-creation side of dc-cr-dwarf-heritage-ancient-blooded is fully implemented and committed. CharacterManager now includes `granted_abilities` in the ancient-blooded Dwarf heritage entry, a `HERITAGE_REACTIONS` constant with the full `call-on-ancient-blood` reaction definition, and two new static helpers (`getHeritageGrantedAbilities`, `isValidHeritageForAncestry`). CharacterCreationStepController step 2 now grants heritage abilities into `character_data['granted_abilities']` on save (with rebuild-on-reselect logic), and `validateStepRequirements` case 2 now server-side-rejects a heritage that does not match the submitted ancestry (AC items 1, 2, and non-Dwarf edge case). PHP lint clean, drush cr success, no new routes added. Combat-reaction side (AC items 3–7) is deferred: `CombatEngine::resolveSavingThrow()` does not exist and `CombatActionController::executeReaction()` is a stub — the infrastructure gap predates this feature.

## Next actions
- PM (`pm-dungeoncrawler`): hand off to `qa-dungeoncrawler` for Gate 2 verification against AC items 1–2 and edge cases; the test plan is at `features/dc-cr-dwarf-heritage-ancient-blooded/03-test-plan.md`
- PM: create a follow-on feature (`dc-cr-dwarf-heritage-ancient-blooded-combat`) targeting `CombatEngine::resolveSavingThrow()` to unblock AC items 3–7; the reaction infrastructure (`ActionProcessor::executeReactionAction`, `reaction_available` column, `ConditionManager` circumstance-bonus handling) is already in place

## Blockers
- Combat-reaction trigger (AC items 3–7) blocked on `CombatEngine::resolveSavingThrow()` (does not exist). This is a pre-existing gap in the encounter-rules layer, not introduced by this feature.

## Needs from Supervisor
- N/A

## Commits
- `bf6fde2d` — feat: CharacterManager + CharacterCreationStepController implementation
- `8bae5b30` — docs: implementation notes + feature.md status updated to in_progress

## Artifacts
- `features/dc-cr-dwarf-heritage-ancient-blooded/02-implementation-notes.md` — full implementation record with AC coverage table, combat-side blocker details, and permission audit note
- `features/dc-cr-dwarf-heritage-ancient-blooded/feature.md` — status: in_progress

## Pre-QA permission audit
No new routes introduced. Script `scripts/role-permissions-validate.py` exited 0 (dev environment not running — expected). Permission config is `org-chart/sites/dungeoncrawler/qa-permissions.json`.

## KB references
None found for heritage ability granting patterns in `knowledgebase/`.

## ROI estimate
- ROI: 231
- Rationale: Unlocks the Dwarf Ancient-Blooded heritage path which is a prerequisite for downstream ancestry/heritage-depth work. Character-creation completeness drives player onboarding quality. Combat-side is deferred but cleanly documented, so this partial ship does not create debt.
