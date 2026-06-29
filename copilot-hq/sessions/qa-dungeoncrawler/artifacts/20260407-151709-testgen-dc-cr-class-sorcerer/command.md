# Test Plan Design: dc-cr-class-sorcerer

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:17:09+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-class-sorcerer/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-class-sorcerer "<brief summary>"
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

# Acceptance Criteria: dc-cr-class-sorcerer

## Gap analysis reference
- DB sections: core/ch03/Sorcerer (REQs 1458–1504)
- Track B: no existing SorcererService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-spellcasting (deferred — groom now, activate when spellcasting ships)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Sorcerer exists as a selectable playable class with CHA as key ability boost at level 1.
- [ ] `[NEW]` Sorcerer HP = 6 + CON modifier per level (lowest of the spellcasting classes).
- [ ] `[NEW]` Initial proficiencies: Trained Perception; Expert Will, Trained Fortitude and Reflex; untrained all armor; Trained unarmed attacks and simple weapons; Trained spell attacks/DCs (tradition determined by bloodline).

### Bloodline (Level 1 Subclass)
- [ ] `[NEW]` At level 1, player selects a Bloodline which determines spell tradition (arcane/divine/occult/primal), bonus spells, and blood magic effect.
- [ ] `[NEW]` Draconic bloodline: player also selects a dragon type (10 options); dragon type determines damage type for bloodline spells.
- [ ] `[NEW]` Elemental bloodline: player also selects an element (air/earth/fire/water); element determines damage type.
- [ ] `[NEW]` Bloodline spells cannot be removed from the spell repertoire.
- [ ] `[NEW]` Blood magic triggers on casting bloodline focus spells or bloodline spells via spell slot when target fails save or spell attack succeeds (area: designate one target).

### Spontaneous Spellcasting (Spell Repertoire)
- [ ] `[NEW]` Sorcerer uses spontaneous casting: selects spells from known repertoire at time of casting (no daily preparation).
- [ ] `[NEW]` Material components can be replaced with somatic components; no component pouch needed.
- [ ] `[NEW]` Spell slots and spell repertoire are tracked independently.
- [ ] `[NEW]` Cantrips auto-heighten to half level rounded up.
- [ ] `[NEW]` New spell levels unlock at odd levels (3rd, 5th, 7th, etc.) per advancement table.
- [ ] `[NEW]` Signature spells (level 3+): one per spell level designated; can be heightened freely to any castable level without learning higher-level versions.
- [ ] `[NEW]` Spells and signature designations can be swapped via downtime retraining.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Feat progression: class feat at level 1 and every even level (2, 4, 6…20); general feats at 3, 7, 11, 15, 19; skill feats every even level; ancestry feats at 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Metamagic timing: any action (including free/reaction) between metamagic action and Cast a Spell wastes the benefit.
- [ ] `[NEW]` Cross-tradition spell (Crossblooded Evolution): correctly cast as bloodline tradition, not original tradition.
- [ ] `[NEW]` Bloodline spell slot restriction: bloodline spells take a slot from the correct level; cannot be freely added beyond repertoire.
- [ ] `[NEW]` Blood magic selection: for area spells, player can designate one target from those affected.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Casting spell not in repertoire: blocked.
- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Spell slot count does not exceed advancement table values.
- [ ] `[TEST-ONLY]` Bloodline spells: cannot be removed from repertoire.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
- Agent: qa-dungeoncrawler
- Status: pending
