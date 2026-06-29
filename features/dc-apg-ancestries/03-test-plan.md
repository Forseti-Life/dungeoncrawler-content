# Test Plan: dc-apg-ancestries

## Coverage summary
- AC items: ~38 (5 new ancestries, 5 versatile heritages, APG backgrounds, integration checks, edge cases)
- Test cases: 24 (TC-APGA-01–24)
- Suites: playwright (character creation)
- Security: AC exemption granted (no new routes)

---

## TC-APGA-01 — Catfolk core stats and passive
- Description: HP 8, Medium, Speed 25, Dex+Cha boosts, Wis flaw, low-light vision; Land on Your Feet halves fall damage and prevents Prone
- Suite: playwright/character-creation
- Expected: ancestry stats correct; fall_damage = ceil(fall_damage / 2); prone_on_landing = false
- AC: Catfolk-1, Catfolk-2

## TC-APGA-02 — Catfolk heritages
- Description: Clawed (claw 1d6 agile/finesse), Hunting (scent 30 ft imprecise), Jungle (ignore veg/rubble difficult terrain), Nine Lives (one-time crit-death mitigation)
- Suite: playwright/character-creation
- Expected: each heritage applies correct passive/ability; Nine Lives one-use flag set
- AC: Catfolk-3

## TC-APGA-03 — Kobold core stats and Draconic Exemplar
- Description: HP 6, Small, Speed 25, Dex+Cha boosts, Con flaw, darkvision; Draconic Exemplar chosen at L1 from 10-dragon table
- Suite: playwright/character-creation
- Expected: stats correct; exemplar selection stored and referenced by other kobold abilities; 10 types available
- AC: Kobold-1, Kobold-2, Kobold-3

## TC-APGA-04 — Kobold heritages
- Description: Cavern (climb stone), Dragonscaled (resistance = level/2 vs exemplar type; ×2 vs breath), Spellscale (1 arcane cantrip), Strongjaw (jaws 1d6), Venomtail (Tail Toxin 1/day)
- Suite: playwright/character-creation
- Expected: each heritage correct; resistance formula uses floor(level/2, min 1); Venomtail daily ability tracked
- AC: Kobold-4–8

## TC-APGA-05 — Orc core stats and heritages
- Description: HP 10, Medium, Speed 25, Str boost, free boost, darkvision; **no ability flaw**; heritage set including Grave Orc (negative healing)
- Suite: playwright/character-creation
- Expected: no flaw applied; Grave Orc: healed_by_negative = true, harmed_by_positive = true
- AC: Orc-1, Orc-2, Orc-3

## TC-APGA-06 — Ratfolk core stats and heritages
- Description: HP 6, Small, Speed 25, Dex+Int boosts, Str flaw, low-light vision; Sewer Rat (disease/filth immunity + stage reduction), Desert Rat (quad Speed 30; ×10 hunger/thirst), Shadow Rat (Intimidation trained; Coerce animals no penalty)
- Suite: playwright/character-creation
- Expected: each heritage correct; Sewer Rat disease stage reduction: 2 on success, 3 on crit (halved for virulent)
- AC: Ratfolk-1–4

## TC-APGA-07 — Tengu core stats and Sharp Beak
- Description: HP 6, Medium, Speed 25, Dex boost + free boost, low-light vision; all tengus have Sharp Beak unarmed (1d6 piercing, brawling, finesse)
- Suite: playwright/character-creation
- Expected: Sharp Beak in unarmed attack list automatically; no selection required
- AC: Tengu-1, Tengu-2

## TC-APGA-08 — Tengu heritages
- Description: Jinxed (curse/misfortune success→crit; doomed flat DC 17 to reduce), Skyborn (0 fall damage, never Prone from fall), Stormtossed (electricity resistance; ignore rain/fog concealment), Taloned (talons 1d4)
- Suite: playwright/character-creation
- Expected: each heritage correct; Skyborn: fall_damage = 0, fall_prone = false (absolute)
- AC: Tengu-3–6

## TC-APGA-09 — Versatile heritages: slot replacement and feat pool
- Description: VH occupies heritage slot; grants VH feat list PLUS original ancestry feat list; sense upgrade: low-light → darkvision when doubled
- Suite: playwright/character-creation
- Expected: selecting VH removes standard heritage options; dual feat pools available; sense upgrade auto-resolves
- AC: VH-1, VH-2, VH-3

## TC-APGA-10 — Versatile heritage: Uncommon gate
- Description: All VHs have Uncommon trait; require GM approval flag to select
- Suite: playwright/character-creation
- Expected: VH selection blocked until GM-approval flag set
- AC: VH-4

## TC-APGA-11 — Aasimar and Changeling versatile heritages
- Description: Aasimar (aasimar trait, LLV upgrade, Lawbringer feat); Changeling (trait, LLV upgrade, Slag May cold-iron claw)
- Suite: playwright/character-creation
- Expected: traits applied; Lawbringer: emotion effect fail → crit success; Slag May: cold iron material on claw
- AC: Aasimar-1–2, Changeling-1–2

## TC-APGA-12 — Dhampir versatile heritage
- Description: Dhampir trait, negative healing, LLV upgrade; Dhampir Fangs (1d6 piercing grapple unarmed)
- Suite: playwright/character-creation
- Expected: negative healing same as Grave Orc; treated as undead for energy effects; fangs in unarmed table
- AC: Dhampir-1–3, Edge-1

## TC-APGA-13 — Duskwalker versatile heritage
- Description: Duskwalker trait; immune to becoming undead; LLV upgrade; passively detects haunts without active Search
- Suite: playwright/character-creation
- Expected: cannot_become_undead = true; haunt_detection = passive (no Search action required)
- AC: Duskwalker-1–2

## TC-APGA-14 — Tiefling versatile heritage
- Description: Tiefling trait, LLV upgrade
- Suite: playwright/character-creation
- Expected: tiefling trait added; LLV upgrade applied if base ancestry had LLV
- AC: Tiefling-1

## TC-APGA-15 — APG supplemental ancestry feats for CRB ancestries
- Description: All APG feats for CRB ancestries loadable in those ancestries' feat selectors; new unarmed attacks added to unarmed table with correct stats
- Suite: playwright/character-creation
- Expected: APG feats appear in ancestry feat pool for each applicable CRB ancestry
- AC: Additional-1–2

## TC-APGA-16 — APG backgrounds: format and data
- Description: APG backgrounds: 2 ability boosts (1 fixed + 1 free), skill training, skill feat grant; Rare backgrounds gated behind GM approval
- Suite: playwright/character-creation
- Expected: each background follows standard data format; Rare flag gates selection
- AC: Backgrounds-1–2

## TC-APGA-17 — Background: Haunted
- Description: Aid from entity; Failure → Frightened 2; Crit Failure → Frightened 4; initial Frightened not reducible by prevention effects
- Suite: playwright/character-creation
- Expected: Aid outcomes correct; frightened_prevention = disabled for initial grant
- AC: Backgrounds-3

## TC-APGA-18 — Background: Fey-Touched
- Description: Fey's Fortune 1/day free-action fortune effect — roll twice, use better
- Suite: playwright/character-creation
- Expected: daily ability tracked; fortune effect: two d20 rolls, higher kept; frequency enforced
- AC: Backgrounds-4

## TC-APGA-19 — Background: Returned
- Description: Grants Diehard feat automatically (no selection)
- Suite: playwright/character-creation
- Expected: character.feats includes Diehard without player selection step
- AC: Backgrounds-5

## TC-APGA-20 — Integration: all 5 new ancestries in selector
- Description: Catfolk, Kobold, Orc, Ratfolk, Tengu appear in ancestry selection with correct traits/HP/speed/ability modifiers
- Suite: playwright/character-creation
- Expected: all 5 entries present; stats verified against table
- AC: Integration-1

## TC-APGA-21 — Integration: versatile heritages in heritage selector
- Description: VHs appear when any ancestry is chosen; tooltip explains slot replacement rule
- Suite: playwright/character-creation
- Expected: 5 VHs visible in heritage selector; tooltip text present
- AC: Integration-2

## TC-APGA-22 — Integration: Draconic Exemplar table displayed for Kobold
- Description: Kobold character creation shows Draconic Exemplar selection table; stored value referenced by other abilities
- Suite: playwright/character-creation
- Expected: exemplar_selection_ui shown; exemplar.dragon_type persists to feat and heritage lookups
- AC: Integration-6

## TC-APGA-23 — Edge: Grave Orc and Dhampir negative healing share same rules
- Description: Both use undead energy rules: positive energy → damage, negative energy → healing
- Suite: playwright/encounter
- Expected: positive_energy.effect = damage; negative_energy.effect = heal; applies to both ancestry types
- AC: Edge-1

## TC-APGA-24 — Edge: Kobold Spellscale one-cantrip-only
- Description: Spellscale grants exactly 1 cantrip slot; no spell progression created
- Suite: playwright/character-creation
- Expected: spell_slots_granted = {cantrip: 1}; no 1st-level slots; no progression table
- AC: Edge-2
