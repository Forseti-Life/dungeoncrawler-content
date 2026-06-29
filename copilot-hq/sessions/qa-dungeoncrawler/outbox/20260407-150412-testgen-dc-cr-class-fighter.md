- Status: done
- Summary: Wrote `features/dc-cr-class-fighter/03-test-plan.md` with 28 test cases (TC-FGT-01 through TC-FGT-28) covering: class identity/HP (10+CON)/STR-or-DEX key ability, Fighter's unique level-1 Expert Perception + Expert Fortitude/Reflex + Expert simple/martial/unarmed weapons, Attack of Opportunity (feature grant, once-per-trigger enforcement, no MAP impact), Press trait (MAP required, cannot Ready, failure ≠ crit-fail), Stance trait (one active, 1-round cooldown, ends on KO/violation/encounter end), Flourish trait (one per turn), Power Attack (counts as 2 MAP attacks, die scaling +1/+2/+3 at levels 1/10/18), full feat/boost schedule, and ACL regression. Four TCs (TC-FGT-04/05/06/21) are conditional on `dc-cr-equipment-system` (in-progress Release B) and should be flagged `pending-dev-confirmation` if that feature hasn't shipped at Stage 0 activation; the remaining 24 TCs activate immediately. Committed at `7ac95706f`.

## Next actions
- At Stage 0: activate 24 immediately-activatable TCs into `qa-suites/products/dungeoncrawler/suite.json`
- At Stage 0: flag TC-FGT-04/05/06/21 as `pending-dev-confirmation` if `dc-cr-equipment-system` has not yet shipped
- PM flag: define all four AoO trigger conditions (manipulate/move/ranged/leave square) in combat system scope before TC-FGT-08 can be fully parameterized
- PM flag: enumerate per-stance "violation" definition before TC-FGT-16 can cover all stance-ending cases

## Blockers
- None

## ROI estimate
- ROI: 14
- Rationale: Fighter has zero spellcasting dependencies and the fewest deferred TCs in this batch (only 4 conditional on equipment); 24 TCs are immediately activatable, making it one of the cleanest classes to ship early in next release scope.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-150412-testgen-dc-cr-class-fighter
- Generated: 2026-04-07T15:53:45+00:00
