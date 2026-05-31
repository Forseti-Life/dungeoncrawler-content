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
