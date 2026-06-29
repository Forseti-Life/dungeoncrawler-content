# Test Plan Design: dc-apg-focus-spells

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:54:16+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-apg-focus-spells/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-apg-focus-spells "<brief summary>"
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

# Acceptance Criteria: APG Focus Spells

## Feature: dc-apg-focus-spells
## Source: PF2E Advanced Player's Guide, Chapter 5 (Focus Spells — APG)

---

## Oracle Revelation Spells

- [ ] Each oracle mystery defines: initial revelation spell, advanced revelation spell, greater revelation spell, and domain spell choices
- [ ] All oracle revelation spells have the cursebound trait — casting any one advances the curse stage
- [ ] Each mystery defines a unique 4-stage curse progression (basic/minor/moderate/major/extreme — details per mystery)
- [ ] Mystery curse is implemented as a unique effect per mystery (not a shared generic condition)
- [ ] All 8 mysteries have unique curse progressions: Ancestors, Battle, Bones, Cosmos, Flames, Life, Lore, Tempest

---

## Witch Hexes

- [ ] `Cackle` (1-action hex): extends duration of another active hex by 1 round; functions as free action in some feat-unlocked contexts
- [ ] `Evil Eye` (hex cantrip): no Focus Point cost; imposes –2 status penalty (sustained); ends early if target succeeds at Will save when affected
- [ ] `Phase Familiar` (reaction hex): familiar becomes incorporeal briefly, negating one source of incoming damage

### Hex Rules Enforcement
- [ ] All hexes (except hex cantrips) cost 1 Focus Point
- [ ] Only one hex may be cast per turn — any type (regular hex or hex cantrip)
- [ ] Hex cantrips auto-heighten to half witch level rounded up; no Focus Point cost; still subject to one-hex-per-turn

---

## Bard Composition Focus Spells (APG)

- [ ] `Hymn of Healing`: sustained composition focus spell; heals 2 HP per round while sustained; scales with heightening
- [ ] `Song of Strength`: grants +2 circumstance bonus to Athletics checks for duration
- [ ] `Gravity Weapon`: status bonus to damage = number of weapon damage dice; bonus doubles vs. Large or larger targets

---

## Ranger Warden Spells (APG)

- [ ] Warden spells use the ranger's primal focus pool
- [ ] Refocus for warden spells: 10 minutes spent in nature
- [ ] Warden spell effects defined per individual spell entry (terrain-based and creature-type bonuses)

---

## Focus Pool Integration

- [ ] Oracle focus pool starts at 2 Focus Points (not 1)
- [ ] Witch focus pool starts at 1 Focus Point
- [ ] Each new focus spell source (lesson, revelation, etc.) may expand the focus pool per standard focus pool rules (cap at 3)

---

## Integration Checks

- [ ] Oracle mystery curses: each mystery's curse stages have distinct, non-generic effects (not shared condition widget)
- [ ] Cursebound casting: every revelation spell check confirms cursebound trait and advances curse tracker
- [ ] Cackle requires another active hex to extend; fails gracefully if no hex is sustained
- [ ] Evil Eye sustain tracking: ends immediately on target's successful Will save
- [ ] Song of Strength: circumstance bonus — does not stack with other circumstance bonuses to Athletics
- [ ] Gravity Weapon: damage-die count sourced from weapon's damage dice (e.g., a 2d6 weapon grants +2; upgrades to +4 vs. Large+)

## Edge Cases

- [ ] Cackle as a free action: only available when feat-unlocked; system checks for feat before allowing free-action trigger
- [ ] Phase Familiar: incorporeal state is brief (one hit negated) and does not persist between uses
- [ ] Evil Eye cast, then Cackle to extend it: valid — Cackle is not casting a second hex, it extends a current one (counts as hex use for one-hex-per-turn)
- [ ] Warden spells refocus: Refocus in nature counts toward general focus pool (same FP pool, different refocus activity)
- Agent: qa-dungeoncrawler
- Status: pending
