# Suite Activation: dc-apg-spells

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T19:45:51+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-apg-spells"`**  
   This links the test to the living requirements doc at `features/dc-apg-spells/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-apg-spells-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-apg-spells",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-apg-spells"`**  
   Example:
   ```json
   {
     "id": "dc-apg-spells-<route-slug>",
     "feature_id": "dc-apg-spells",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-apg-spells",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-apg-spells

## Coverage summary
- AC items: ~35 (8 named spells with full mechanics, system extensibility, heighten scaling)
- Test cases: 17 (TC-SPL-01–17)
- Suites: playwright (encounter, character creation)
- Security: AC exemption granted (no new routes)

---

## TC-SPL-01 — System: APG spells added to existing traditions without structural changes
- Description: APG adds spells to arcane/divine/occult/primal lists; no structural change required; heightened variants use parameter tables
- Suite: playwright/character-creation
- Expected: APG spells selectable from tradition spell lists; heighten parameters stored as tables (not hardcoded per rank)
- AC: System-1

## TC-SPL-02 — Animate Dead: summon undead, level cap per rank, sustain/auto-end
- Description: 3-action (M+S+V); creates one undead ≤ level cap per rank table; sustained; auto-ends at 1 min if not sustained; no damage/save
- Suite: playwright/encounter
- Expected: summon_level cap per rank table enforced; duration = sustained max 1 min; no save prompt
- AC: AnimateDead-1–5

## TC-SPL-03 — Animate Dead: summoned creature follows summon-trait constraints
- Description: Summoned undead obeys summoned-trait constraints; disappears when spell ends
- Suite: playwright/encounter
- Expected: summoned undead removed on spell end; no persistent undead after duration; summon-trait restrictions applied
- AC: AnimateDead-3–4

## TC-SPL-04 — Blood Vendetta: reaction trigger, bleed damage, Will save outcomes
- Description: Reaction on incoming piercing/slashing/bleed; base 2d6 persistent bleed on attacker; Will save; crit success = unaffected; success = half; failure = full + weakness 1 to piercing/slashing while bleeding; crit failure = double + same weakness
- Suite: playwright/encounter
- Expected: reaction fires on correct trigger; save outcomes produce correct bleed amounts and weakness conditions
- AC: BloodVendetta-1–4

## TC-SPL-05 — Blood Vendetta: bleeding creature requirement, heighten scaling
- Description: Caster must be capable of bleeding (not undead/construct); heighten: +2d6 per +2 ranks
- Suite: playwright/encounter
- Expected: undead/construct casters blocked from casting; heightened bleed dice scale correctly
- AC: BloodVendetta-2, 5

## TC-SPL-06 — Déjà Vu: records next-turn action sequence on failed save
- Description: Will save; on fail: engine records target's next turn actions exactly; following turn target must repeat sequence (same targets/direction); illegal action → substitute + Stupefied 1 that turn
- Suite: playwright/encounter
- Expected: turn recording triggers on failed save; repeat forced next turn; each illegal action in sequence → Stupefied 1; no damage component
- AC: DejaVu-1–4

## TC-SPL-07 — Final Sacrifice: minion death, 20-ft fire damage, Reflex save, cold swap
- Description: Requires permanent minion; minion slain immediately; 6d6 fire (basic Reflex, 20 ft); cold/water trait minion → cold damage; temporary minion auto-fails
- Suite: playwright/encounter
- Expected: permanent minion required; minion destroyed before resolution; cold swap applies; temporary minion blocks cast
- AC: FinalSacrifice-1–6

## TC-SPL-08 — Final Sacrifice: evil trait on non-mindless, heighten scaling
- Description: Non-mindless creature sacrifice adds evil trait; heighten: +2d6 per +1 rank
- Suite: playwright/encounter
- Expected: mindless = no evil tag; non-mindless = evil trait recorded; heighten damage scales per rank
- AC: FinalSacrifice-4, 7

## TC-SPL-09 — Heat Metal: three target types, per-type rules
- Description: Unattended: no save; worn/carried/metal creature: 4d6 fire + 2d4 persistent fire (Reflex); held item: Release improves degree by 1 step
- Suite: playwright/encounter
- Expected: unattended → no save prompt; worn/carried → Reflex save; Release action available for held items before result
- AC: HeatMetal-1–4

## TC-SPL-10 — Heat Metal: persistent fire binds to item, save outcomes, heighten
- Description: Persistent fire attached to item; damages holder until extinguished; crit success = none; success = half+none persistent; failure = full+full; crit fail = double; heighten: +2d6 initial +1d4 persistent per rank
- Suite: playwright/encounter
- Expected: persistent fire state tracked on item; correct outcome table; heighten parameter table driven
- AC: HeatMetal-5–7

## TC-SPL-11 — Mad Monkeys: sustained area, mode selection at cast, mode persists
- Description: Sustained up to 1 min; area repositionable 5 ft on Sustain; mode (Burglary/Din/Gymnastics) selected at cast; same mode for duration
- Suite: playwright/encounter
- Expected: mode locked at cast; reposition 5 ft per Sustain; area visualization updates
- AC: MadMonkeys-1–2

## TC-SPL-12 — Mad Monkeys: Flagrant Burglary, Raucous Din, Tumultuous Gymnastics modes
- Description: Burglary: Steal 1/round (Thievery = spell DC – 10); Din: Fortitude save rounds (deafened outcomes); Gymnastics: Reflex save (flat check for manipulate); stolen items drop on spell end
- Suite: playwright/encounter
- Expected: Thievery modifier = (spell_dc – 10); deafened durations per save tier; flat check 5 vs. manipulate on Gymnastics fail; stolen items dropped at spell end
- AC: MadMonkeys-3–5

## TC-SPL-13 — Mad Monkeys: Calm Emotions overlay suppresses effects
- Description: Calm Emotions active in same area suppresses Mad Monkeys mischief effects while both overlap
- Suite: playwright/encounter
- Expected: calm_emotions + mad_monkeys overlap → mischief saves skipped while both active
- AC: MadMonkeys-6

## TC-SPL-14 — Pummeling Rubble: 15-ft cone, bludgeoning, Reflex, forced movement
- Description: 2d4 bludgeoning in 15-ft cone; Reflex; crit success = none; success = half; failure = full + pushed 5 ft; crit failure = double + pushed 10 ft; movement directly away from caster
- Suite: playwright/encounter
- Expected: cone targeting correct; damage per tier; push distances correct; blocked by walls/obstacles
- AC: PummelingRubble-1–4

## TC-SPL-15 — Pummeling Rubble: heighten scaling
- Description: Heighten: +2d4 damage per +1 spell rank
- Suite: playwright/encounter
- Expected: heightened damage dice grow per rank table; parameter-driven not hardcoded
- AC: PummelingRubble-4

## TC-SPL-16 — Vomit Swarm: 30-ft cone, piercing + Sickened on fail, heighten
- Description: 2d8 piercing in 30-ft cone; basic Reflex; fail/crit fail → Sickened 1; heighten: +1d8 per rank
- Suite: playwright/encounter
- Expected: Sickened 1 only on fail/crit fail (not success); no persistent swarm entity; heighten table driven
- AC: VomitSwarm-1–3

## TC-SPL-17 — System: heightened spell parameter tables are data-driven
- Description: All scaling spells (Blood Vendetta, Final Sacrifice, Heat Metal, Pummeling Rubble, Vomit Swarm, Animate Dead) use stored heighten parameter tables, not hardcoded rank checks
- Suite: playwright/character-creation
- Expected: adding a new heighten rank requires only data entry; no code change needed for existing scaling logic
- AC: System-1 (heighten parameter tables)

### Acceptance criteria (reference)

# Acceptance Criteria: APG New Spells

## Feature: dc-apg-spells
## Source: PF2E Advanced Player's Guide, Chapter 5 (Spell System: New Spells)

---

## System Requirements

- [ ] System supports adding new spells to existing spell lists (arcane/divine/occult/primal) without structural changes
- [ ] APG adds new spells at all levels 1–9 across all four traditions
- [ ] New spell categories include: animation (undead), elemental projectiles, social manipulation, debilitation, divination, shadow/darkness, natural phenomena
- [ ] Heightened spell variants apply graduated effects per spell level — system supports heightened spell parameter tables

---

## Specific New Spells (Detailed Requirements)

### Animate Dead (3-action, Arcane/Divine/Occult)
- [ ] 3-action spell with material, somatic, and verbal components
- [ ] Targets a summon point within 30 feet; creates exactly one common undead summon
- [ ] Summoned creature level capped by spell rank: rank 1→L-1, rank 2→L1, rank 3→L2, rank 4→L3, rank 5→L5, rank 6→L7, rank 7→L9, rank 8→L11, rank 9→L13, rank 10→L15
- [ ] Summoned creature obeys summoned-trait constraints and disappears when spell ends
- [ ] No damage roll and no saving throw; purely summon-based
- [ ] Duration: sustained each round; auto-ends after 1 minute if not sustained

### Blood Vendetta (Reaction, Arcane/Occult/Primal)
- [ ] Reaction with trigger: incoming piercing, slashing, or bleed damage against the caster
- [ ] Requires caster to be capable of bleeding (not undead, constructs, etc.)
- [ ] Base effect: 2d6 persistent bleed damage to the triggering attacker, resolved by Will save
- [ ] Save outcomes: critical success unaffected; success half persistent bleed; failure full persistent bleed + weakness 1 to piercing/slashing while bleeding persists; critical failure same + double persistent bleed
- [ ] Heightened scaling: +2d6 persistent bleed per +2 spell rank

### Déjà Vu (2-action, Occult)
- [ ] 2-action Will-save spell against one target within 100 feet
- [ ] On failed save: engine records exact action order and targets from the target's **next** turn
- [ ] Turn after that: target is forced to repeat the same sequence (same targets, same movement direction)
- [ ] For each action that cannot be legally repeated: target may use a legal substitute and gains Stupefied 1 until end of that turn
- [ ] No direct damage component

### Final Sacrifice (2-action, Arcane/Divine)
- [ ] Requires a valid target with minion trait that is summoned by or permanently controlled by the caster
- [ ] Target minion is immediately slain as a mandatory cost
- [ ] Creatures within 20 feet of the minion take 6d6 fire damage; basic Reflex save
- [ ] If minion has the cold or water trait, damage type swaps to cold
- [ ] Casting on a non-mindless creature applies evil-trait classification
- [ ] Casting on a temporarily controlled minion (via temporary command) automatically fails
- [ ] Heightened scaling: +2d6 damage per +1 spell rank

### Heat Metal (2-action, Arcane/Primal)
- [ ] Supports target types: unattended metal item, worn/carried metal item, creature made primarily of metal
- [ ] Unattended item: no saving throw; environmental interactions flagged for GM adjudication
- [ ] Worn/carried item or metal creature: 4d6 fire + 2d4 persistent fire; Reflex save
- [ ] Held item: wielder may Release to improve degree of success by one step after roll
- [ ] Persistent fire bound to the heated item; damages any creature holding/wearing it until extinguished
- [ ] Save outcomes: critical success unaffected; success half initial + no persistent; failure full initial + full persistent; critical failure double initial + double persistent
- [ ] Heightened scaling: +2d6 initial fire + +1d4 persistent fire per +1 spell rank

### Mad Monkeys (2-action, Primal/Occult)
- [ ] Creates a sustained area effect for up to 1 minute; area can be repositioned 5 feet on Sustain
- [ ] Mischief mode selected at cast time; mode persists for duration
- [ ] **Flagrant Burglary mode**: attempts one Steal action per round against one creature in area; uses Thievery modifier = (spell DC – 10); stolen items drop in chosen square when spell ends
- [ ] **Raucous Din mode**: Fortitude save each round; crit success: unaffected + 10-min immunity; success: unaffected; failure: deafened 1 round; crit failure: deafened 1 minute
- [ ] **Tumultuous Gymnastics mode**: Reflex save each round; crit success: unaffected + 10-min immunity; success: unaffected; failure: DC 5 flat check to perform manipulate actions for 1 round (lose action on failed flat); crit failure: same until spell ends even outside area
- [ ] Calm emotions overlay suppresses mischief effects while both effects overlap

### Pummeling Rubble (2-action, Arcane/Primal)
- [ ] Deals 2d4 bludgeoning in a 15-foot cone; Reflex save
- [ ] Save outcomes: critical success unaffected; success half damage; failure full damage + pushed 5 feet away from caster; critical failure double damage + pushed 10 feet away
- [ ] Forced movement pushes directly away from caster origin; respects movement blocking constraints
- [ ] Heightened scaling: +2d4 damage per +1 spell rank

### Vomit Swarm (2-action, Arcane/Occult/Primal)
- [ ] 2d8 piercing damage in a 30-foot cone; basic Reflex save
- [ ] Targets failing (or critically failing) save become Sickened 1
- [ ] Swarm manifestation is visual/flavor-only; no persistent summon entity
- [ ] Heightened scaling: +1d8 piercing damage per +1 spell rank

---

## General New Spell Catalog (Remaining)

- [ ] All remaining APG arcane/divine/occult/primal spells added to their respective tradition spell lists by level
- [ ] Each spell entry includes: level, traditions, components (V/S/M), area/target, duration, save type, heightened parameters
- [ ] Spell list UI updates to display APG spells alongside CRB spells (no separate APG-only list)

---

## Integration Checks

- [ ] Animate Dead level cap table enforced at cast time (cannot summon a creature above the allowed level for the spell rank used)
- [ ] Blood Vendetta trigger validation: reaction fires only when caster is eligible (can bleed)
- [ ] Déjà Vu round tracking: state machine correctly sequences "record" turn then "replay" turn
- [ ] Final Sacrifice evil-trait classification applied to session log metadata when applicable
- [ ] Heat Metal item targeting: correct target type detection (unattended vs. worn vs. creature)
- [ ] Mad Monkeys mode is a one-time choice per casting; cannot switch mid-duration

## Edge Cases

- [ ] Animate Dead when no valid undead available of correct level: fails gracefully with error message
- [ ] Blood Vendetta on a construct or undead: blocked at cast time (cannot bleed check)
- [ ] Final Sacrifice on a temporary-control minion: fail silently and end control effect without explosion
- [ ] Déjà Vu's replay: if target has no valid actions to repeat (e.g., all targets dead), each replaced action triggers Stupefied 1
- Agent: qa-dungeoncrawler
- Status: pending
