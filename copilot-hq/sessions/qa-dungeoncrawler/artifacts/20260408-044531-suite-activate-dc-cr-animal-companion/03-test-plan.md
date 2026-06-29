# Test Plan: dc-cr-animal-companion

## Coverage summary
- AC items: ~15 (content type, advancement, commanding, distinction from familiar, species specifics)
- Test cases: 10 (TC-ANC-01–10)
- Suites: playwright (character creation, encounter)
- Security: AC exemption granted

---

## TC-ANC-01 — Companion content type: all required fields stored
- Description: Companion stores: companion_id, character_id, companion_type, size, speeds, senses, HP, AC, saves, attack entries, advancement_level (young|mature|nimble|savage)
- Suite: playwright/character-creation
- Expected: all fields present in companion record; no null required fields
- AC: AC-001

## TC-ANC-02 — Companion initialized at "young" for eligible classes
- Description: Ranger, Druid, Beastmaster Archetype characters initialize companion at "young" advancement
- Suite: playwright/character-creation
- Expected: companion.advancement_level = "young" at creation; non-eligible classes blocked from companion
- AC: AC-001

## TC-ANC-03 — Advancement: young → mature → nimble or savage
- Description: Class feature advancement moves companion through tiers; Mature tier updates stats per table
- Suite: playwright/character-creation
- Expected: advancement trigger updates level field; Mature recalculates AC, saves, HP, attack modifier, damage
- AC: AC-002

## TC-ANC-04 — Command an Animal: 1 action, DC check, gives companion 2 actions
- Description: Command an Animal = 1 action; success vs. DC 15 (or creature Will DC) → companion takes 2 actions that turn
- Suite: playwright/encounter
- Expected: Command action costs 1 action; success → companion.actions_this_turn = 2
- AC: AC-003

## TC-ANC-05 — No Command: companion repeats last-turn actions (Stride/Strike only)
- Description: If Command not used, companion takes only Stride and/or Strike (repeating previous turn behavior)
- Suite: playwright/encounter
- Expected: no Command this turn → companion action options limited to Stride/Strike per prior turn
- AC: AC-003

## TC-ANC-06 — Companion vs. familiar: companion has full combat stats
- Description: Animal companion has full combat stats (attack bonus, damage, AC, saves); familiar does not
- Suite: playwright/character-creation
- Expected: companion record has attack_bonus, damage_entries, AC, saves; familiar record lacks attack entries
- AC: AC-004

## TC-ANC-07 — Companion at 0 HP: unconscious, not permanently dead
- Description: Companion at 0 HP falls unconscious; death only via GM decision or failed recovery over days
- Suite: playwright/encounter
- Expected: HP = 0 → companion.state = unconscious (not destroyed); permanent death requires explicit flag
- AC: AC-004

## TC-ANC-08 — Species selection sets base stats, size, speed, senses, natural attacks
- Description: Each species (bear, bird, cat, wolf, etc.) defines base stats; selection populates all base fields
- Suite: playwright/character-creation
- Expected: species selection fills size, speed, senses, and natural attack type; fields not manually editable unless advanced
- AC: AC-005

## TC-ANC-09 — Flier companion: aerial movement rules applied
- Description: Companion with Flier movement (eagle, bat) uses aerial movement rules (elevation, plunging strike)
- Suite: playwright/encounter
- Expected: flier companion has fly_speed; elevation tracked; plunging strike available at height
- AC: AC-005

## TC-ANC-10 — Companion cannot Command Animal without line of effect
- Description: Command an Animal requires the character to be able to communicate with companion (within range and no blocking condition)
- Suite: playwright/encounter
- Expected: Command blocked if companion outside communicable range; edge case documented in implementation
- AC: AC-003 (integration note)
