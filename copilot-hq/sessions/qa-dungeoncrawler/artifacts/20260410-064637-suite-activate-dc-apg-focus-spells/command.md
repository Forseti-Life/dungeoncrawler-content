- Status: done
- Completed: 2026-04-10T21:14:42Z

# Suite Activation: dc-apg-focus-spells

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-10T06:46:37+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-apg-focus-spells"`**  
   This links the test to the living requirements doc at `features/dc-apg-focus-spells/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-apg-focus-spells-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-apg-focus-spells",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-apg-focus-spells"`**  
   Example:
   ```json
   {
     "id": "dc-apg-focus-spells-<route-slug>",
     "feature_id": "dc-apg-focus-spells",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-apg-focus-spells",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-apg-focus-spells

## Coverage summary
- AC items: ~24 (oracle revelation spells, witch hexes, bard compositions, ranger warden spells, focus pool rules)
- Test cases: 12 (TC-FCS-01–12)
- Suites: playwright (character creation, encounter)
- Security: AC exemption granted (no new routes)

---

## TC-FCS-01 — Oracle: each mystery defines revelation/advanced/greater/domain spells
- Description: All 8 oracle mysteries (Ancestors, Battle, Bones, Cosmos, Flames, Life, Lore, Tempest) define initial/advanced/greater revelation spells and domain spell choices
- Suite: playwright/character-creation
- Expected: mystery selection unlocks exactly the correct set of revelation spells; domain spell options present per mystery
- AC: Oracle-1

## TC-FCS-02 — Oracle: cursebound trait on all revelation spells
- Description: Every oracle revelation spell has the cursebound trait; casting any one advances curse stage
- Suite: playwright/encounter
- Expected: every revelation spell cast → curse_stage += 1; cursebound trait visible in spell info
- AC: Oracle-2

## TC-FCS-03 — Oracle: 4-stage curse progression per mystery (unique effects)
- Description: Each mystery's curse has distinct stage-by-stage effects (not shared generic condition); stages: basic/minor/moderate/major/extreme
- Suite: playwright/encounter
- Expected: curse display shows mystery-specific text at each stage; stage effects are not shared across mysteries
- AC: Oracle-3–4

## TC-FCS-04 — Oracle focus pool starts at 2
- Description: Oracle's initial focus pool = 2 Focus Points (not 1)
- Suite: playwright/character-creation
- Expected: oracle.focus_pool_start = 2; further expansion follows standard rules (cap 3)
- AC: FocusPool-1

## TC-FCS-05 — Witch hexes: Evil Eye (hex cantrip, no FP, sustain, ends on Will save)
- Description: Evil Eye costs 0 FP; imposes –2 status penalty; sustained; ends immediately when target succeeds Will save while affected
- Suite: playwright/encounter
- Expected: FP_cost = 0; status_penalty = –2; sustain mechanic available; Will save success on sustained hex → hex ends immediately
- AC: Hexes-2

## TC-FCS-06 — Witch hexes: Cackle extends active hex duration
- Description: Cackle extends another active hex by 1 round; requires an active hex to extend; fails gracefully if no active hex
- Suite: playwright/encounter
- Expected: Cackle with active hex → hex duration +1 round; Cackle with no active hex → error/no effect (not crash); Cackle does not start a new hex
- AC: Hexes-1, Edge-1

## TC-FCS-07 — Witch hexes: Phase Familiar (reaction, incorporeal, one hit negated)
- Description: Reaction on incoming damage; familiar becomes briefly incorporeal; negates one damage source; does not persist
- Suite: playwright/encounter
- Expected: reaction trigger = incoming_damage; one hit negated; incorporeality state = temporary (single use per activation)
- AC: Hexes-3

## TC-FCS-08 — Witch focus pool starts at 1; hex cantrip one-per-turn rule
- Description: Witch's focus pool = 1 FP; hex cantrips cost 0 FP but still count as "hex used this turn"
- Suite: playwright/character-creation
- Expected: witch.focus_pool_start = 1; hex_cantrip.counts_as_hex_used = true; second hex attempt same turn blocked
- AC: Hexes-8, FocusPool-1

## TC-FCS-09 — Evil Eye + Cackle edge: Cackle as extension is valid (not second hex)
- Description: Cackle extending a sustained Evil Eye hex cantrip is valid; counts as hex-used but is an extension, not a second hex cast
- Suite: playwright/encounter
- Expected: Cackle after Evil Eye: allowed; one_hex_per_turn = fulfilled (Cackle = extend action, not cast); no duplicate hex error
- AC: Edge-3

## TC-FCS-10 — Bard composition focus spells: Hymn of Healing, Song of Strength, Gravity Weapon
- Description: Hymn: sustained heal 2 HP/round (scales); Song of Strength: +2 circ to Athletics; Gravity Weapon: status bonus = weapon damage dice count (doubles vs Large+)
- Suite: playwright/encounter
- Expected: Hymn heals each sustain; Song bonus = circumstance (no stacking); Gravity Weapon doubles vs Large+; scaling via heighten
- AC: Bard-1–3

## TC-FCS-11 — Ranger warden spells: primal focus pool, refocus in nature
- Description: Warden spells use ranger's primal focus pool; Refocus = 10 min in nature; warden effects per individual entry
- Suite: playwright/encounter
- Expected: warden spells draw from same primal focus pool; Refocus activity = "in nature"; spell effects stored per entry
- AC: Ranger-1–2

## TC-FCS-12 — Focus pool expansion: cap at 3 across all sources
- Description: Any new focus spell source (lesson, revelation, warden spell, etc.) may expand focus pool; cap = 3
- Suite: playwright/character-creation
- Expected: adding 4th source does not increase pool beyond 3; UI shows current/max FP; expansion rules applied uniformly
- AC: FocusPool-1 (Integration clause)

### Acceptance criteria (reference)

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
