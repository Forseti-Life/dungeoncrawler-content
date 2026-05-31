# Acceptance Criteria: dc-cr-goblin-ancestry

## Gap analysis reference
- DB sections: core/ch02 (Goblin ancestry, goblin heritages, goblin ancestry feats)
- Depends on: dc-cr-ancestry-system

---

## Happy Path

### Character creation availability
- [ ] `[NEW]` Goblin appears as a selectable ancestry in character creation.

### Core ancestry stats
- [ ] `[NEW]` Goblin ancestry grants 6 HP, Small size, and 25-foot Speed.
- [ ] `[NEW]` Goblin ancestry grants Dexterity and Charisma boosts plus one free boost.
- [ ] `[NEW]` Goblin ancestry applies Wisdom as the ancestry flaw.

### Ancestry integration
- [ ] `[NEW]` Goblin ancestry links to goblin heritages and the goblin ancestry feat tree.
- [ ] `[NEW]` Goblin ancestry data persists on the character record and is visible in the character sheet.

---

## Edge Cases
- [ ] `[NEW]` The free boost cannot duplicate an ancestry-assigned fixed boost if the character builder enforces PF2e boost restrictions.
- [ ] `[NEW]` Existing non-goblin characters cannot gain goblin ancestry-only feats without ancestry reassignment.

## Failure Modes
- [ ] `[TEST-ONLY]` Character creation rejects invalid ancestry stat payloads for goblin characters.
- [ ] `[TEST-ONLY]` Goblin ancestry cannot be applied to a character without re-running ancestry-dependent recalculation.

## Security acceptance criteria
- Security AC exemption: ancestry data modeling only; no new route surface beyond existing character flows
