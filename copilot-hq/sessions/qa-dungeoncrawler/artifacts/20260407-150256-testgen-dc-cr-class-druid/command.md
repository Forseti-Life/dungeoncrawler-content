# Test Plan Design: dc-cr-class-druid

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:02:56+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-class-druid/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-class-druid "<brief summary>"
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

# Acceptance Criteria: dc-cr-class-druid

## Gap analysis reference
- DB sections: core/ch03/Druid (REQs 1116–1171)
- Track B: no existing DruidService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-spellcasting (deferred — groom now, activate when spellcasting ships), dc-cr-animal-companion (planned)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Druid exists as a selectable playable class with Wisdom as key ability boost at level 1.
- [ ] `[NEW]` Druid HP = 8 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Trained Perception; Expert Will, Trained Fortitude and Reflex; Trained light and medium armor (metal armor and shields forbidden); Trained primal spell attacks/DCs (Wisdom).
- [ ] `[NEW]` Druid automatically knows Druidic language at level 1.
- [ ] `[NEW]` Wild Empathy: Druid can use Diplomacy on animals.

### Universal Anathema
- [ ] `[NEW]` Metal armor and shields cannot be equipped by Druid characters; system prevents equipping or provides a blocking warning.
- [ ] `[NEW]` Teaching Druidic to non-druids flagged as anathema; all anathema violations remove primal spellcasting and order benefits until atone ritual completed.

### Druidic Order (Subclass)
- [ ] `[NEW]` At level 1, player selects one Order: Animal, Leaf, Storm, or Wild.
- [ ] `[NEW]` Each order grants one order focus spell; Leaf and Storm orders start with 2 Focus Points (others start with 1).

### Primal Spellcasting (Prepared)
- [ ] `[NEW]` Druid uses prepared primal spellcasting; must prepare spells each day.
- [ ] `[NEW]` Spell attacks and DCs scale with Wisdom modifier.
- [ ] `[NEW]` Spell slots scale per advancement table.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Level 19: Primal Hierophant — one 10th-level prepared primal spell slot (cannot be used with slot-manipulation features).

### Feat Progression
- [ ] `[NEW]` Druid gains a class feat at level 1 and every even-numbered level (2, 4, 6…20).
- [ ] `[NEW]` General feats at levels 3, 7, 11, 15, 19; skill feats at every even level; ancestry feats at levels 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Order Explorer: joining a second order grants access to its 1st-level feats; violating that order's anathema removes only those feats, not the main primal connection.
- [ ] `[NEW]` Wild Shape forms: each unlock feat adds specific forms; attempting unlocked forms that aren't taken yet is blocked.
- [ ] `[NEW]` Form Control: duration extends correctly; spell level reduction (–2, min 1) applied when metamagic is used.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Metal armor equipment blocked for druid characters.
- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Preparing more spells than available slots blocked.
- [ ] `[TEST-ONLY]` Focus pool at 0: order focus spells blocked.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
- Agent: qa-dungeoncrawler
- Status: pending
