# Test Plan Design: dc-cr-class-rogue

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:15:07+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-class-rogue/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-class-rogue "<brief summary>"
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

# Acceptance Criteria: dc-cr-class-rogue

## Gap analysis reference
- DB sections: core/ch03/Rogue (REQs 1392–1457)
- Track B: no existing RogueService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-character-creation ✓, dc-cr-conditions (in-progress Release B)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Rogue exists as a selectable playable class with DEX as default key ability boost at level 1 (racket may allow STR or CHA instead).
- [ ] `[NEW]` Rogue HP = 8 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Trained Perception; Expert Reflex AND Expert Will at level 1 (unique double Expert saves), Trained Fortitude; Stealth + racket skills + 7 + INT additional skills; Trained simple weapons, hand crossbow, rapier, sap, shortbow, shortsword; Trained light armor.
- [ ] `[NEW]` Rogue gains a skill feat every level (not just every 2 levels); skill increases every level from 2nd onward.

### Rogue's Racket (Level 1 Subclass)
- [ ] `[NEW]` At level 1, player selects one Racket: Ruffian, Scoundrel, or Thief.
- [ ] `[NEW]` Ruffian: sneak attack with any simple weapon (not just agile/finesse); crit hit + flat-footed target also applies weapon critical specialization (simple weapons with die ≤ d8); Trained Intimidation and medium armor; can choose STR as key ability.
- [ ] `[NEW]` Scoundrel: successful Feint → target flat-footed against your melee attacks until end of next turn (critical success: flat-footed to ALL melee); Trained Deception and Diplomacy; can choose CHA as key ability.
- [ ] `[NEW]` Thief: finesse melee weapon attacks can use DEX modifier for damage instead of STR; Trained Thievery.

### Sneak Attack (Level 1)
- [ ] `[NEW]` Sneak attack adds precision damage only when the target is flat-footed.
- [ ] `[NEW]` Sneak attack precision damage is ineffective against creatures without vital organs or weak points.
- [ ] `[NEW]` Sneak attack dice scale with level: 1d6 at level 1, +1d6 every 4 levels.

### Surprise Attack (Level 1)
- [ ] `[NEW]` When rolling initiative using Deception or Stealth, creatures that haven't acted yet are flat-footed against the rogue for the first round.

### Debilitations (unlocked via feats)
- [ ] `[NEW]` Debilitations are mutually exclusive: applying a new debilitation replaces the previous one.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Feat progression: class feat at level 1 and every even level (2, 4, 6…20); general feats at 3, 7, 11, 15, 19; skill feats every level (not just even); ancestry feats at 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Ruffian sneak attack weapon restriction: d10/d12 simple weapons (e.g., greatclub) do NOT qualify for crit specialization bonus — only ≤d8.
- [ ] `[NEW]` Thief DEX-to-damage: applies only to finesse melee weapons, not ranged or non-finesse.
- [ ] `[NEW]` Scoundrel Feint result tracking: flat-footed status from Feint correctly expires at end of the rogue's next turn.
- [ ] `[NEW]` Sneak attack vs immune targets: damage correctly suppressed for creatures without vital anatomy (oozes, constructs, etc.).
- [ ] `[NEW]` Debilitation replacement: new application immediately ends previous; no stacking.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Sneak attack without flat-footed condition: precision damage not applied.
- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Key ability override blocked if racket doesn't permit it.
- [ ] `[TEST-ONLY]` Skill feat cadence: rogue receives one per level (not every other level).

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
- Agent: qa-dungeoncrawler
- Status: pending
