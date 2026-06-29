# Test Plan Design: dc-apg-class-witch

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:54:16+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-apg-class-witch/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-apg-class-witch "<brief summary>"
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

# Acceptance Criteria: Witch Class Mechanics (APG)

## Feature: dc-apg-class-witch
## Source: PF2E Advanced Player's Guide, Chapter 2

---

## Class Fundamentals

- [ ] Class record: HP 6 + Con per level, key ability Intelligence
- [ ] Saves: Trained Fortitude, Trained Reflex, Expert Will
- [ ] No armor proficiency; trained only in unarmored defense
- [ ] Spellcasting tradition determined by patron theme (not fixed at class level)

---

## Patron Theme (Select at L1; Cannot Change)

- [ ] Patron theme determines: spell tradition, patron skill, hex cantrip, familiar's granted spell
- [ ] Themes: Curse (occult), Fate (occult), Fervor (divine), Night (occult), Rune (arcane), Wild (primal), Winter (primal)
- [ ] Patron skill added as Trained skill automatically

---

## Familiar

- [ ] Witch **must** have a familiar; it is a class-locked feature, not optional
- [ ] Familiar gains bonus familiar abilities at L1, L6, L12, L18 (one extra each level milestone)
- [ ] All witch spells are stored in the familiar; communing with familiar is required to prepare spells
- [ ] Familiar death: does NOT erase known spells; replacement familiar provided at next daily prep with all same spells
- [ ] Witch uses prepared (not spontaneous) spellcasting

---

## Spell Learning and Repertoire

- [ ] Familiar starts with 10 cantrips + 5 first-level spells + 1 patron-granted spell
- [ ] Each class level gained: familiar learns 2 new spells chosen by player from tradition's spell list
- [ ] Familiar can learn spells by consuming scrolls (1 hour per spell)
- [ ] Witch can use Learn a Spell to create a consumable written version for familiar to absorb
- [ ] Two witch familiars can teach each other spells via Learn a Spell activity (both familiars present; standard cost applies)
- [ ] Witch cannot prepare spells from another witch's familiar directly

---

## Hexes (Focus Spells)

- [ ] Hexes are focus spells using the focus pool and focus spell rules
- [ ] Only one hex may be cast per turn; attempting a second hex in the same turn fails (actions wasted)
- [ ] Witch starts with focus pool of 1 Focus Point
- [ ] Refocus = 10 minutes communing with familiar; restores 1 Focus Point
- [ ] Hex cantrips (e.g., Evil Eye) do **not** cost Focus Points (free to cast)
- [ ] Hex cantrips still obey the one-hex-per-turn restriction
- [ ] Hex cantrips auto-heighten to half witch level rounded up
- [ ] Hex cantrips are a separate pool from prepared cantrips (do not take up cantrip slots)

---

## Witch Lessons (Tiered Feat Mechanism)

- [ ] Each lesson grants: one hex (added to focus spell options) + one spell added to familiar
- [ ] Lesson tiers: Basic (L2+), Greater (L6+), Major (L10+)
- [ ] **Basic Lessons**: Dreams (veil of dreams + sleep), Elements (elemental betrayal + element choice), Life (life boost + spirit link), Protection (blood ward + mage armor), Vengeance (needle of vengeance + phantom pain)
- [ ] **Greater Lessons**: Mischief (deceiver's cloak + mad monkeys), Shadow (malicious shadow + chilling darkness), Snow (personal blizzard + wall of wind)
- [ ] **Major Lessons**: Death (curse of death + raise dead), Renewal (restorative moment + field of life)

---

## Notable Hexes

- [ ] `Cackle` (1-action): extends another active hex's duration by 1 round (free action in some contexts)
- [ ] `Evil Eye` (hex cantrip): no FP cost; imposes –2 status penalty (sustained); ends early if target succeeds at Will save
- [ ] `Phase Familiar` (reaction hex): familiar becomes incorporeal briefly, avoiding damage

---

## Integration Checks

- [ ] Witch spell preparation uses familiar as the spell repository (visual: "communing with familiar")
- [ ] Patron skill displayed and granted automatically at character creation
- [ ] Familiar spell list grows by 2 per level-up
- [ ] One-hex-per-turn rule enforced across both hex cantrips and regular hexes
- [ ] Focus pool starts at 1; grows with feats that add focus spells
- [ ] Lesson feats are gated by tier level requirements (Basic/Greater/Major)

## Edge Cases

- [ ] Witch familiar dies mid-session: prepared spells for the day remain available; new familiar with same spells restored at next daily prep
- [ ] Two warlocks present: spell transfer via Learn a Spell only — no direct familiar-to-familiar preparation
- [ ] Cackle on a hex cantrip: extends the cantrip's duration (if the cantrip is sustained); does not break one-hex-per-turn rule
- [ ] Evil Eye ends early on Will save success: immediate termination, no partial duration
- Agent: qa-dungeoncrawler
- Status: pending
