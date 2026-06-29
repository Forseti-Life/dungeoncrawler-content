# Test Plan Design: dc-apg-spells

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:54:16+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-apg-spells/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-apg-spells "<brief summary>"
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
