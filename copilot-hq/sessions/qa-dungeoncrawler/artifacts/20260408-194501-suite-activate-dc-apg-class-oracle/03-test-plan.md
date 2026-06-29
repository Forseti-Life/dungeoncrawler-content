# Test Plan: dc-apg-class-oracle

## Coverage summary
- AC items: ~28 (class record, mystery, revelation spells, curse, integration, edge cases)
- Test cases: 17 (TC-ORC-01–17)
- Suites: playwright (character creation, encounter)
- Security: AC exemption granted (no new routes)

---

## TC-ORC-01 — Class record and spellcasting
- Description: HP 8+Con/level; key ability Cha; Expert Will; Trained Fortitude/Reflex; divine spontaneous spellcasting; all material components → somatic; 5 cantrips + 2 1st-level spells
- Suite: playwright/character-creation
- Expected: stats correct; material_component_required = false for all oracle spells; starts with 5 cantrips + 2 spells
- AC: Fundamentals-1–5

## TC-ORC-02 — Cantrips auto-heighten; spell slots follow sorcerer progression
- Description: Cantrips heighten to half class level rounded up; spell slots per day match sorcerer progression
- Suite: playwright/character-creation
- Expected: cantrip.effective_level = ceil(level / 2); spell_slots match TABLE 2-3
- AC: Fundamentals-6–7

## TC-ORC-03 — Signature Spells at L3
- Description: At L3, one spell per accessible spell level can be cast at any available level
- Suite: playwright/character-creation
- Expected: at L3, one signature_spell slot per spell level; selected spell castable at any higher slot
- AC: Fundamentals-8

## TC-ORC-04 — Mystery selection: fixed at creation, 8 options
- Description: Oracle selects one mystery at creation; cannot change; 8 options available
- Suite: playwright/character-creation
- Expected: mystery selector shows 8 options; after selection, mystery is locked; change_mystery = blocked
- AC: Mystery-1–2

## TC-ORC-05 — Revelation spells: cursebound trait and focus pool
- Description: All revelation spells have cursebound trait; focus pool starts at 2 FP; Refocus = 10 min = +1 FP
- Suite: playwright/character-creation
- Expected: oracle.focus_pool_start = 2; all revelation spells have cursebound = true; refocus restores 1 FP
- AC: Revelation-1–4

## TC-ORC-06 — Revelation spells at L1: initial + domain choice
- Description: Oracle learns exactly 2 revelation spells at L1 — mystery's initial (fixed, no choice) + one domain spell (player choice)
- Suite: playwright/character-creation
- Expected: first revelation spell is forced (mystery-specific); second is player choice from domain list
- AC: Revelation-4, Edge-3

## TC-ORC-07 — Curse stage machine: 4 stages
- Description: Stage 0 (basic, passive/constant) → minor → moderate → overwhelmed; casting revelation spell advances by 1
- Suite: playwright/encounter
- Expected: initial curse_stage = 0 (basic); each revelation cast advances by 1; at moderate → overwhelmed triggers; overwhelmed blocks all revelation casting
- AC: Curse-1–5, Integration-2

## TC-ORC-08 — Curse overwhelmed state
- Description: Overwhelmed: cannot cast or sustain revelation spells until next daily prep
- Suite: playwright/encounter
- Expected: at overwhelmed: revelation_cast = blocked; sustain_revelation = blocked; resets at daily_prep
- AC: Curse-5

## TC-ORC-09 — Curse refocus: moderate → minor reset
- Description: Refocusing at moderate or higher: curse resets to minor (not basic) + restores 1 FP
- Suite: playwright/encounter
- Expected: refocus_at_moderate → curse_stage = minor; FP += 1; not reset to basic
- AC: Curse-6, Edge-4

## TC-ORC-10 — Curse daily reset to basic
- Description: 8 hours rest + daily preparations resets curse to basic stage
- Suite: playwright/encounter
- Expected: after daily_prep: curse_stage = basic
- AC: Curse-7

## TC-ORC-11 — Curse irremovable
- Description: Oracular curse cannot be removed, mitigated, or suppressed by any spell or item (remove curse has no effect)
- Suite: playwright/encounter
- Expected: remove_curse targeting oracle returns failure; curse_stage unchanged
- AC: Curse-9, Edge-2

## TC-ORC-12 — Curse traits: curse, divine, necromancy
- Description: Oracle curse has curse, divine, and necromancy traits
- Suite: playwright/character-creation
- Expected: curse.traits includes [curse, divine, necromancy]
- AC: Curse-8

## TC-ORC-13 — Per-mystery curse progressions: unique effects
- Description: All 8 mysteries define unique 4-stage curse progressions (not a shared generic widget)
- Suite: playwright/character-creation
- Expected: each of 8 mysteries has distinct curse_stage effects in data; UI renders them uniquely
- AC: Curse-3, MysterySpells-1, Integration-1

## TC-ORC-14 — Per-mystery granted content
- Description: Each mystery provides: initial revelation spell, advanced revelation spell, greater revelation spell, domain spell choices
- Suite: playwright/character-creation
- Expected: all 8 mysteries have complete spell sets; initial revelation always locked; advanced/greater accessible at correct levels
- AC: MysterySpells-1–8

## TC-ORC-15 — Focus pool cap: stays at 2 unless feats expand it
- Description: Oracle focus pool does not auto-grow beyond 2 unless a feat explicitly adds focus spells
- Suite: playwright/character-creation
- Expected: oracle.focus_pool_max = 2 (standard cap); grows only when focus_spell feats taken; max cap at 3 per standard rules
- AC: Integration-4

## TC-ORC-16 — Edge: attempt revelation spell with 0 FP
- Description: Attempt to cast revelation spell at 0 FP → blocked even if curse stage allows it
- Suite: playwright/encounter
- Expected: revelation_cast blocked when focus_points = 0 regardless of curse_stage
- AC: Edge-1

## TC-ORC-17 — Edge: oracle blocked before first revelation cast of the day
- Description: Oracle cannot cast cursebound spells if curse has not been activated today (no curse active yet)
- Suite: playwright/encounter
- Expected: At session start, if no cursebound spell cast today, first revelation spell activates curse at basic; this is already the initial state — verify initial casting path succeeds
- AC: Revelation (initial cast note)
