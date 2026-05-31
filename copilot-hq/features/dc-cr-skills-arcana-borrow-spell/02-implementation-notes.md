Implemented the remaining live roadmap rows for `dc-cr-skills-arcana-borrow-spell`.

Changes made:
- Kept Arcana Recall Knowledge untrained by adding direct regression coverage for the existing `recall_knowledge` action.
- Hardened `borrow_arcane_spell` in `ExplorationPhaseHandler` to require:
  - Trained Arcana
  - an arcane prepared spellcaster
- Added failure retry blocking for Borrow Arcane Spell until the actor completes `daily_prepare`.
- Cleared the retry block during `daily_prepare`, matching the next-preparation-cycle reset rule.
- Added targeted unit coverage for:
  - untrained Arcana Recall Knowledge being allowed
  - trained Arcana gate
  - arcane prepared spellcaster gate
  - retry block reset after daily preparation
