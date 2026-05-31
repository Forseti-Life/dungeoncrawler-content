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
