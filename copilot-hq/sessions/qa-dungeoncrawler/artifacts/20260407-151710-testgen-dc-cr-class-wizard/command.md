# Test Plan Design: dc-cr-class-wizard

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:17:10+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-class-wizard/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-class-wizard "<brief summary>"
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

# Acceptance Criteria: dc-cr-class-wizard

## Gap analysis reference
- DB sections: core/ch03/Wizard (REQs 1505–1550)
- Track B: no existing WizardService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-spellcasting (deferred — groom now, activate when spellcasting ships)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Wizard exists as a selectable playable class with INT as key ability boost at level 1.
- [ ] `[NEW]` Wizard HP = 6 + CON modifier per level (tied lowest with Sorcerer).
- [ ] `[NEW]` Initial proficiencies: Trained Perception; Expert Will, Trained Fortitude and Reflex; untrained all armor; Trained club/crossbow/dagger/heavy crossbow/staff/unarmed; Trained arcane spell attacks/DCs (INT-based).

### Arcane Spellcasting & Spellbook
- [ ] `[NEW]` Wizard prepares spells each day from a personal spellbook; cannot cast from general list without preparation.
- [ ] `[NEW]` Prepared spells can be heightened by casting them in a higher-level slot.
- [ ] `[NEW]` At level 1, spellbook contains 10 cantrips + 10 first-level arcane spells; new spells added by learning (Occultism/Arcana checks or paying scribing cost).
- [ ] `[NEW]` Universalist wizard: each spell level independently gets one free recall per day.

### Arcane Bond (Level 1 — non-thesis-overriding)
- [ ] `[NEW]` Bonded item grants one Drain Bonded Item use per day: recall and cast a previously-cast spell from memory (no slot required, must be castable level).

### Arcane Thesis (Level 1 Subclass)
- [ ] `[NEW]` At level 1, player selects one Arcane Thesis: Improved Familiar Attunement, Metamagical Experimentation, Spell Blending, or Spell Substitution.
- [ ] `[NEW]` Improved Familiar Attunement: gain Familiar feat; familiar has extra abilities (+1 more at 6th, 12th, 18th); Drain Bonded Item draws from familiar instead.
- [ ] `[NEW]` Metamagical Experimentation: gain one 1st-level metamagic wizard feat at 1st; from 4th level, daily prep grants one temporary metamagic wizard feat (≤ half level) until next prep.
- [ ] `[NEW]` Spell Blending: during daily prep, can trade two same-level slots for one slot up to 2 levels higher (different level per bonus); can also trade one slot for two additional cantrips (once per prep).
- [ ] `[NEW]` Spell Substitution: spend 10 uninterrupted minutes to replace one prepared spell slot with a different spell from spellbook.

### Arcane Schools
- [ ] `[NEW]` Specialist wizard selects one arcane school (or Universalist); school grants extra spell slot per level (school-only), extra cantrip slot, one extra school spell in spellbook, initial school spell.
- [ ] `[NEW]` Advanced school spell unlocked via feat at level 8.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Feat progression: class feat at level 1 and every even level (2, 4, 6…20); general feats at 3, 7, 11, 15, 19; skill feats every even level; ancestry feats at 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Drain Bonded Item: only one use per day (without Superior Bond feat); used spell must be one already cast that day.
- [ ] `[NEW]` Spell Blending: bonus slot must be of a level the wizard can cast (≤ highest slot level); cannot create slots above current maximum.
- [ ] `[NEW]` Counterspell (wizard): requires having the specific spell prepared — not just in spellbook (without Clever Counterspell feat).
- [ ] `[NEW]` Metamagic timing: same constraint as sorcerer — any intervening action wastes the metamagic benefit.
- [ ] `[NEW]` Specialist extra slot: can only be used to prepare spells from that school; attempting to prepare non-school spell in the bonus slot is blocked.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Casting a spell not prepared today: blocked (unlike spontaneous casters).
- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Spellbook spell not yet learned: cannot be prepared.
- [ ] `[TEST-ONLY]` Drain Bonded Item beyond once per day: blocked (without feat).

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
- Agent: qa-dungeoncrawler
- Status: pending
