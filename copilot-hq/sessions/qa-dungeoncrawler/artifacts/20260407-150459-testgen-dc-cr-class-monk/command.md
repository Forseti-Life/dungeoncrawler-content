# Test Plan Design: dc-cr-class-monk

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:04:59+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-class-monk/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-class-monk "<brief summary>"
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

# Acceptance Criteria: dc-cr-class-monk

## Gap analysis reference
- DB sections: core/ch03/Monk (REQs 1256–1323)
- Track B: no existing MonkService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-character-creation ✓

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Monk exists as a selectable playable class with STR or DEX as key ability boost at level 1 (player chooses).
- [ ] `[NEW]` Monk HP = 10 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Trained Perception; Expert Will, Trained Fortitude and Reflex; Untrained all armor, Expert unarmored defense.
- [ ] `[NEW]` Monk fist base damage = 1d6 (not 1d4); no lethal/nonlethal penalty on unarmed attacks.

### Flurry of Blows (Level 1)
- [ ] `[NEW]` Flurry of Blows is a 1-action ability that makes two unarmed Strikes; usable once per turn.
- [ ] `[NEW]` MAP increases normally after Flurry of Blows (both attacks count).

### Ki Spells & Focus Pool
- [ ] `[NEW]` Ki spells are focus spells; focus pool starts at 1 Focus Point (max 3 with feats); Wisdom is spellcasting ability.
- [ ] `[NEW]` Ki spell feats (Ki Rush, Ki Strike, etc.) each grant +1 Focus Point when taken.

### Stance Unarmed Attacks
- [ ] `[NEW]` Each stance feat provides unique unarmed attack profiles (damage die, traits) that replace or supplement base unarmed attacks while the stance is active.
- [ ] `[NEW]` Stance restriction: only one stance active at a time; entering a new stance ends the previous.
- [ ] `[NEW]` Mountain Stance: +4 item bonus to AC; +2 circumstance vs Shove/Trip; DEX cap to AC = +0; –5 ft Speed; requires touching ground. Item AC stacks with armor potency runes on mage armor / explorer's clothing.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Feat progression: class feat at level 1 and every even level (2, 4, 6…20); general feats at 3, 7, 11, 15, 19; skill feats every even level; ancestry feats at 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Flurry of Blows when only one unarmed attack remains MAP-viable: both strikes still attempted; second may have MAP penalty.
- [ ] `[NEW]` Stunning Fist (requires Flurry of Blows): Fortitude vs class DC check only when BOTH flurry strikes hit same creature; incapacitation rules applied correctly vs higher-level creatures.
- [ ] `[NEW]` Ki spells require active focus points; casting with 0 FP is blocked.
- [ ] `[NEW]` Mountain Stance DEX cap of +0: correctly overrides character DEX bonus to AC even with bonuses from other sources.
- [ ] `[NEW]` Fuse Stance (level 20): correctly grants all effects of both selected stances simultaneously.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Armor equip blocked: monk cannot wear non-explorer's-clothing armor without explicitly training via feat.
- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Flurry of Blows usable only once per turn (second use blocked).
- [ ] `[TEST-ONLY]` Two stances simultaneously: not permitted (without Fuse Stance feat).

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
- Agent: qa-dungeoncrawler
- Status: pending
