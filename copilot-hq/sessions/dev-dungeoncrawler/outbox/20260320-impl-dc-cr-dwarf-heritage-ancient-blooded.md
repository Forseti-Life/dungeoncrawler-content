Both items are clear. The improvement round outbox already exists (Status: done) and seat instructions have the new rules in place. The ancient-blooded outbox exists (Status: in_progress). Here are the current updates:

---

**Inbox: `20260402-improvement-round-20260322-dungeoncrawler-release-next`**

- Status: done
- Summary: Post-release gap review for `20260322-dungeoncrawler-release-next` is complete. Three dev-process gaps were identified and closed: (1) missing `drush updatedb` execution post-deploy caused 2 CRITICAL production bugs — a post-deploy schema gate rule is now in seat instructions requiring `drush updatedb --status` verification before closing any impl with schema changes; (2) the Bedrock model fix applied to forseti's `ai_conversation` module was not propagated to dungeoncrawler — a cross-site shared module sync rule now requires explicit confirmation in impl outboxes; (3) 8 stale `/home/keithaumiller/` paths in seat instructions were corrected to `/home/ubuntu/`. All three gaps are dev-seat-owned and fully addressed in the seat instructions layer. No new inbox items needed.

## Next actions
- None. All gaps documented and closed in `org-chart/agents/instructions/dev-dungeoncrawler.instructions.md`.

## Blockers
- None.

## ROI estimate
- ROI: 12
- Rationale: Prevents repeat of 2 CRITICAL production bugs (schema deploy gap) and 1 live error (module divergence). The seat instructions changes are already in place.

---

**Inbox: `20260320-impl-dc-cr-dwarf-heritage-ancient-blooded`**

- Status: in_progress
- Summary: Character-creation side of dc-cr-dwarf-heritage-ancient-blooded is fully implemented and committed (commits `bf6fde2d`, `8bae5b30`). CharacterManager now includes `granted_abilities` in the ancient-blooded Dwarf heritage entry, a `HERITAGE_REACTIONS` constant with the full `call-on-ancient-blood` reaction definition, and two static helpers (`getHeritageGrantedAbilities`, `isValidHeritageForAncestry`). CharacterCreationStepController step 2 grants heritage abilities into `character_data['granted_abilities']` on save with rebuild-on-reselect logic, and `validateStepRequirements` case 2 server-side-rejects a heritage that does not match the submitted ancestry. PHP lint clean, drush cr success, no new routes. AC items 3–7 (combat reaction trigger, bonus, stacking, expiry) remain deferred — `CombatEngine::resolveSavingThrow()` does not exist and `CombatActionController::executeReaction()` is a stub; this is a pre-existing encounter-rules gap unrelated to this feature.

## Next actions
- PM (`pm-dungeoncrawler`): hand off to `qa-dungeoncrawler` for Gate 2 verification against AC items 1–2 and edge cases; test plan at `features/dc-cr-dwarf-heritage-ancient-blooded/03-test-plan.md`
- PM: create follow-on feature (`dc-cr-dwarf-heritage-ancient-blooded-combat`) targeting `CombatEngine::resolveSavingThrow()` to unblock AC items 3–7

## Blockers
- Combat-reaction trigger (AC items 3–7): `CombatEngine::resolveSavingThrow()` does not exist. Pre-existing encounter-rules gap.

## Needs from Supervisor
- N/A

## Commits
- `bf6fde2d` — feat: CharacterManager + CharacterCreationStepController implementation
- `8bae5b30` — docs: implementation notes + feature.md status updated to in_progress

## ROI estimate
- ROI: 231
- Rationale: Unlocks the Dwarf Ancient-Blooded heritage path; combat-side cleanly deferred with full infrastructure notes and no debt introduced.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260320-impl-dc-cr-dwarf-heritage-ancient-blooded
- Generated: 2026-04-05T21:13:59+00:00
