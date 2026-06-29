- Status: done
- Summary: Wrote `features/dc-cr-class-druid/03-test-plan.md` with 30 test cases (TC-DRU-01 through TC-DRU-30) covering: class identity/HP (8+CON)/WIS key ability, Druidic language auto-grant, Wild Empathy flag, universal anathema (metal armor blocked, shields blocked, violation/atone cycle), all four druidic orders (Animal/Leaf/Storm/Wild — each with one focus spell, Leaf/Storm start at 2 Focus Points), focus pool at-zero guard, prepared primal spellcasting, Primal Hierophant at level 19 (10th-level slot with slot-manipulation restriction), feat progression, Order Explorer partial anathema edge case, Wild Shape form unlock/block, and Form Control duration/spell-level reduction. Eight TCs depend on deferred `dc-cr-spellcasting` or `dc-cr-animal-companion` and must be flagged `pending-dev-confirmation` at Stage 0; 22 TCs are immediately activatable. Feature marked ready, PM notified. Committed at `a6b19b722`.

## Next actions
- At Stage 0: activate immediately-activatable TCs into `qa-suites/products/dungeoncrawler/suite.json`
- At Stage 0: flag TC-DRU-15/16/17/18/19/20 as `pending-dev-confirmation` until `dc-cr-spellcasting` ships; TC-DRU-26/27 until `dc-cr-animal-companion` ships
- PM flag: enumerate exact focus spell name per order (Animal/Leaf/Storm/Wild) before TC-DRU-12 can be fully parameterized
- PM flag: define Wild Shape form catalog (which feat unlocks which forms) before TC-DRU-26 can be fully parameterized

## Blockers
- None

## ROI estimate
- ROI: 12
- Rationale: Druid is a core PF2E class with the unique metal-armor/shield constraint — that anathema enforcement is a high-risk regression path worth catching early; 22 immediately-activatable TCs with no deferred dependencies make this high-value at Stage 0.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-150256-testgen-dc-cr-class-druid
- Generated: 2026-04-07T15:50:56+00:00
