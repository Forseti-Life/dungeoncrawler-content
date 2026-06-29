# Test Plan Design: dc-cr-class-cleric

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:02:17+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-class-cleric/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-class-cleric "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

# Acceptance Criteria: dc-cr-class-cleric

## Gap analysis reference
- DB sections: core/ch03/Cleric (REQs 1052–1115)
- Track B: no existing ClericService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-spellcasting (deferred — groom now, activate when spellcasting ships)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Cleric exists as a selectable playable class with Wisdom as key ability boost at level 1.
- [ ] `[NEW]` Cleric HP = 8 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Trained Perception; Expert Will, Trained Fortitude and Reflex; Trained deity's favored weapon; untrained in all armor by default (doctrine may override); Trained divine spell attacks/DCs (Wisdom).

### Deity & Anathema
- [ ] `[NEW]` At level 1, player selects a deity with approved alignment; anathema violation removes divine connection until atone ritual completed.

### Doctrine (Subclass)
- [ ] `[NEW]` At level 1, player selects a Doctrine: Cloistered Cleric or Warpriest.
- [ ] `[NEW]` Cloistered Cleric: focuses on spell power; faster spell DC/attack progression, domain emphasis.
- [ ] `[NEW]` Warpriest: gains armor and weapon proficiencies; slower spell DC/attack progression.

### Divine Spellcasting (Prepared)
- [ ] `[NEW]` Cleric uses prepared divine spellcasting (not spontaneous); must prepare spells each day.
- [ ] `[NEW]` Religious symbol acts as divine focus replacing material components.
- [ ] `[NEW]` Spell slots and cantrips scale per advancement table.
- [ ] `[NEW]` Wisdom modifier used for spell attacks and DCs.

### Divine Font
- [ ] `[NEW]` Cleric gains Divine Font based on deity: Healing Font (prepare heal spells in bonus slots = 1 + CHA) or Harmful Font (harm spells = 1 + CHA).
- [ ] `[NEW]` Font choice is locked unless deity allows both and Versatile Font feat is taken.
- [ ] `[NEW]` Bonus divine font slots are at the highest spell level the cleric has access to.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Level 19: Miraculous Spell — one 10th-level spell slot for a prepared divine spell (cannot be used with slot-manipulation features).

### Feat Progression
- [ ] `[NEW]` Cleric gains a class feat at level 1 and every even-numbered level (2, 4, 6…20).
- [ ] `[NEW]` General feats at levels 3, 7, 11, 15, 19; skill feats at every even level; ancestry feats at levels 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Anathema violation: divine connection suspended; spellcasting still works but domain spells and deity abilities disabled until atone.
- [ ] `[NEW]` Healing Font with Versatile Font: both heal and harm can fill font slots.
- [ ] `[NEW]` Harmful Font without evil alignment (deity allows): correctly permitted if deity explicitly allows it.
- [ ] `[NEW]` Font slot count: capped at 1 + CHA modifier; minimum 1.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Invalid deity/alignment combination rejected.
- [ ] `[TEST-ONLY]` Font slots do not exceed 1 + CHA modifier.
- [ ] `[TEST-ONLY]` Preparing more spells than available slots blocked.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
- Agent: qa-dungeoncrawler
- Status: pending
