# Test Plan: dc-cr-human-ancestry

## Coverage summary
- AC items: 18 (14 happy path, 3 edge cases, 2 failure modes) + 1 DB gap task
- Test cases: 18 (TC-HUM-01–18)
- Suites: playwright (character creation flows)
- Security: AC exemption granted (no new routes)
- PM NOTE: core/ch01 and core/ch02 DB rows not yet loaded. Dev DB-load task must complete before this feature reaches implementation. TC-HUM-18 documents this dependency.

---

## TC-HUM-01 — Core stats: HP, size, speed
- Description: Human ancestry grants HP 8, Medium size, Speed 25 ft
- Suite: playwright/character-creation
- Expected: character.hp_from_ancestry = 8; size = Medium; speed = 25
- AC: Stats-1

## TC-HUM-02 — Two free ability boosts (unique scores)
- Description: Player chooses any two different ability scores for boosts; system enforces uniqueness
- Suite: playwright/character-creation
- Expected: exactly 2 free boosts allocated; duplicate selection blocked
- AC: Stats-2, Edge-1, Failure-1

## TC-HUM-03 — No ability flaw
- Description: Human ancestry applies no ability flaw
- Suite: playwright/character-creation
- Expected: no ability_flaw entries on character after Human ancestry selection
- AC: Stats-3

## TC-HUM-04 — Traits
- Description: Human and Humanoid traits applied
- Suite: playwright/character-creation
- Expected: character.traits includes [Human, Humanoid]
- AC: Stats-4

## TC-HUM-05 — Starting languages by Int modifier
- Description: Common granted; each point of positive Int modifier adds one bonus language from Regional Languages list
- Suite: playwright/character-creation
- Expected: character.languages includes Common; bonus_language_slots = max(0, int_modifier); only Regional Languages selectable
- AC: Stats-5, Stats-6

## TC-HUM-06 — Standard vision (no special senses)
- Description: Humans have standard vision by default (not low-light or darkvision)
- Suite: playwright/character-creation
- Expected: character.senses = [standard_vision] only; no low_light_vision or darkvision unless granted by heritage
- AC: Senses-1

## TC-HUM-07 — Feat: Adapted Cantrip (spellcasting gate)
- Description: Grants one cantrip from a different tradition; requires spellcasting class feature
- Suite: playwright/character-creation
- Expected: cantrip added to spell list from a different tradition; feat blocked if no spellcasting class feature
- AC: Feat-1

## TC-HUM-08 — Feat: Cooperative Nature
- Description: +4 circumstance bonus to Aid actions
- Suite: playwright/character-creation
- Expected: aid_action.bonus = +4 circumstance
- AC: Feat-2

## TC-HUM-09 — Feat: General Training
- Description: One bonus non-skill general feat taken immediately
- Suite: playwright/character-creation
- Expected: bonus_feat_slot opens; only non-skill general feats selectable; feat applied immediately
- AC: Feat-3

## TC-HUM-10 — Feat: Haughty Obstinacy
- Description: CritFail vs. coercion/control improves to Failure; if target Will > spell DC, save as normal
- Suite: playwright/character-creation
- Expected: coercion/control crit_fail → outcome = failure; target_will > spell_dc: normal save (no improvement)
- AC: Feat-4

## TC-HUM-11 — Feat: Natural Ambition
- Description: One bonus 1st-level class feat from own class; 2nd-level class feat blocked
- Suite: playwright/character-creation
- Expected: bonus 1st-level class feat slot opens; only own class + level 1 feats selectable; level 2 feat attempt blocked
- AC: Feat-5, Edge-2, Failure-2

## TC-HUM-12 — Feat: Natural Skill
- Description: Trained in two additional skills of choice
- Suite: playwright/character-creation
- Expected: 2 additional skill training slots granted; player may choose any skills
- AC: Feat-6

## TC-HUM-13 — Feat: Unconventional Weaponry
- Description: Trained in one uncommon weapon; can select feats requiring that weapon
- Suite: playwright/character-creation
- Expected: one uncommon weapon proficiency added at trained; associated weapon feats unlocked for that weapon
- AC: Feat-7

## TC-HUM-14 — Heritage: Half-Elf
- Description: Low-light vision; can take elf ancestry feats in addition to human feats
- Suite: playwright/character-creation
- Expected: senses.low_light_vision = true; elf_ancestry_feat_pool accessible
- AC: Heritage-1

## TC-HUM-15 — Heritage: Half-Orc
- Description: Low-light vision; can take orc ancestry feats in addition to human feats
- Suite: playwright/character-creation
- Expected: senses.low_light_vision = true; orc_ancestry_feat_pool accessible
- AC: Heritage-2

## TC-HUM-16 — Heritage: Skilled Heritage
- Description: Trained in one additional skill; expert in one skill at level 5
- Suite: playwright/character-creation
- Expected: +1 skill training at level 1; selected skill advances to expert automatically at level 5
- AC: Heritage-3

## TC-HUM-17 — Heritage: Versatile Heritage
- Description: One additional general feat at 1st level
- Suite: playwright/character-creation
- Expected: extra general feat slot at level 1; any general feat selectable
- AC: Heritage-4

## TC-HUM-18 — DB Gap dependency: core/ch01–ch02 must be loaded
- Description: Validate that dc_requirements contains rows for core/ch01 and core/ch02 before dev implementation starts
- Suite: automation/data-integrity
- Expected: SELECT COUNT(*) FROM dc_requirements WHERE section LIKE 'core/ch01%' > 0 AND section LIKE 'core/ch02%' > 0
- Roles covered: dev-dungeoncrawler (pre-implementation gate)
- PM NOTE (Action Required): DB-load task must be dispatched to dev-dungeoncrawler before this feature enters dev. This TC is a pre-implementation gate, not a functional test.
