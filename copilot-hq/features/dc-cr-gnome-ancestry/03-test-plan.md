# Test Plan: dc-cr-gnome-ancestry

## Coverage summary
- AC items: 20 (14 happy path, 3 edge cases, 3 failure modes)
- Test cases: 14 (TC-GNM-01–14)
- Suites: playwright (character creation flows)
- Security: AC exemption granted (no new routes)

---

## TC-GNM-01 — Core stats: HP, size, speed
- Description: Gnome ancestry grants HP 8, Small size, Speed 25 ft
- Suite: playwright/character-creation
- Expected: character.hp_from_ancestry = 8; size = Small; speed = 25
- AC: Core Stats-1

## TC-GNM-02 — Ability boosts and flaw
- Description: Constitution and Charisma get fixed boosts; one free boost; Strength has a flaw
- Suite: playwright/character-creation
- Expected: con +2, cha +2, one player-chosen +2; str –2 applied automatically; free boost cannot be applied to con or cha
- AC: Core Stats-2, Edge Case-3

## TC-GNM-03 — Traits
- Description: Gnome and Humanoid traits applied
- Suite: playwright/character-creation
- Expected: character.traits includes [Gnome, Humanoid]
- AC: Core Stats-4

## TC-GNM-04 — Starting languages
- Description: Common, Gnomish, Sylvan granted automatically
- Suite: playwright/character-creation
- Expected: character.languages includes [Common, Gnomish, Sylvan]
- AC: Core Stats-5

## TC-GNM-05 — Bonus languages by Int modifier
- Description: Each point of positive Int modifier unlocks one bonus language from the restricted list
- Suite: playwright/character-creation
- Expected: bonus_language_slots = max(0, int_modifier); only listed languages selectable
- AC: Core Stats-6, Edge Case-3

## TC-GNM-06 — Low-Light Vision
- Description: Gnome has Low-Light Vision sense
- Suite: playwright/character-creation
- Expected: character.senses includes [low-light-vision]
- AC: Senses-1

## TC-GNM-07 — Heritage unlocked
- Description: Gnome ancestry unlocks exactly five heritages
- Suite: playwright/character-creation
- Expected: heritage_options = [chameleon, fey-touched, sensate, umbral, wellspring]; exactly one must be chosen
- AC: Heritage-1, Heritage-2

## TC-GNM-08 — HP differentiated from Dwarf
- Description: Gnome HP 8, not 10
- Suite: playwright/character-creation
- Expected: hp_from_ancestry = 8 when ancestry = gnome; does not inherit dwarf value
- AC: Failure Modes-1

## TC-GNM-09 — Speed differentiated from Dwarf
- Description: Gnome Speed 25, not 20
- Suite: playwright/character-creation
- Expected: speed = 25 when ancestry = gnome
- AC: Failure Modes-2

## TC-GNM-10 — Str flaw not overrideable
- Description: Strength flaw is applied automatically and cannot be skipped or assigned elsewhere
- Suite: playwright/character-creation
- Expected: str_from_flaw = -2; no UI control to remove it; only free boost cannot target str as a second boost
- AC: Failure Modes-3

## TC-GNM-11 — Small-size Bulk rules apply
- Description: Small size applies inventory Bulk rules for Small creatures
- Suite: playwright/character-creation
- Expected: character.size = Small; bulk_limit uses Small-creature rules
- AC: Edge Case-1

## TC-GNM-12 — First World Magic feat available
- Description: First World Magic (Gnome, 1st) appears in feat selection at character creation
- Suite: playwright/character-creation
- Expected: feat_pool[gnome][level_1] includes dc-cr-first-world-magic
- AC: Ancestry Feats

## TC-GNM-13 — Fey Fellowship feat available
- Description: Fey Fellowship (Gnome, 1st) appears in feat selection
- Suite: playwright/character-creation
- Expected: feat_pool[gnome][level_1] includes dc-cr-fey-fellowship
- AC: Ancestry Feats

## TC-GNM-14 — Gnome Weapon Familiarity: glaive and kukri trained
- Description: Selecting Gnome Weapon Familiarity grants trained proficiency in glaive and kukri
- Suite: playwright/character-creation
- Expected: character.weapon_proficiencies includes [glaive: trained, kukri: trained]
- AC: Ancestry Feats
