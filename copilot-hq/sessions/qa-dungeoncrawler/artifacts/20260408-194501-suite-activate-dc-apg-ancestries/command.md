# Suite Activation: dc-apg-ancestries

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T19:45:01+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-apg-ancestries"`**  
   This links the test to the living requirements doc at `features/dc-apg-ancestries/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-apg-ancestries-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-apg-ancestries",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-apg-ancestries"`**  
   Example:
   ```json
   {
     "id": "dc-apg-ancestries-<route-slug>",
     "feature_id": "dc-apg-ancestries",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-apg-ancestries",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria: APG Ancestries and Versatile Heritages

## Feature: dc-apg-ancestries
## Source: PF2E Advanced Player's Guide, Chapter 1

---

## New Ancestries (5)

### Catfolk (Amurrun)
- [ ] Ancestry record: HP 8, Medium, Speed 25, Dexterity boost, Charisma boost, Wisdom flaw, Low-light vision
- [ ] Passive `Land on Your Feet`: falling halves damage and prevents Prone on landing
- [ ] Heritages: Clawed (claw unarmed 1d6 slashing, agile/finesse/unarmed), Hunting (scent 30 ft imprecise), Jungle (ignore difficult terrain from vegetation/rubble), Nine Lives (one-time critical hit death mitigation)

### Kobold
- [ ] Ancestry record: HP 6, Small, Speed 25, Dexterity boost, Charisma boost, Constitution flaw, Darkvision
- [ ] Draconic Exemplar selection at L1 — stores dragon type, damage type, breath weapon shape, save type; used by other kobold abilities
- [ ] Draconic Exemplar lookup table implemented (10 dragon types)
- [ ] Heritage: Cavern — climb natural stone (success → half speed, crit → full speed), squeeze success→crit
- [ ] Heritage: Dragonscaled — resistance to exemplar damage type = level/2 (min 1); doubled vs. dragon breath
- [ ] Heritage: Spellscale — 1 at-will arcane cantrip, trained arcane spellcasting, Cha-based
- [ ] Heritage: Strongjaw — jaws unarmed attack (1d6 piercing, brawling, finesse, unarmed)
- [ ] Heritage: Venomtail — `Tail Toxin` 1-action 1/day: apply to weapon, next hit before end of next turn deals persistent poison = level

### Orc
- [ ] Ancestry record: HP 10, Medium, Speed 25, Strength boost, free boost, Darkvision; **no ability flaw**
- [ ] Heritage set covers: terrain adaptation, weapon proficiency bump, darkvision extension (low-light → darkvision), Grave Orc (negative healing), damage resistance, weather/environment bonuses
- [ ] Grave Orc heritage: `negative healing` — harmed by positive energy, healed by negative energy

### Ratfolk (Ysoki)
- [ ] Ancestry record: HP 6, Small, Speed 25, Dexterity boost, Intelligence boost, Strength flaw, Low-light vision
- [ ] Heritage: Sewer Rat — immune to filth fever; disease/poison save stage reduction (2 on success, 3 on crit; halved for virulent)
- [ ] Heritage: Desert Rat — on all fours Speed 30 (both hands free required); starvation/thirst threshold ×10; heat/cold extremes modified
- [ ] Heritage: Shadow Rat — trained Intimidation; Coerce animals without language penalty; animals start one attitude step worse

### Tengu
- [ ] Ancestry record: HP 6, Medium, Speed 25, Dexterity boost, free boost, Low-light vision
- [ ] All tengus start with `Sharp Beak` unarmed attack: 1d6 piercing, brawling group, finesse, unarmed
- [ ] Heritage: Jinxed — curse/misfortune saves: success→crit success; doomed gain → flat DC 17 to reduce by 1
- [ ] Heritage: Skyborn — take 0 damage from any fall; never lands Prone from falling
- [ ] Heritage: Stormtossed — electricity resistance = level/2 (min 1); ignore concealment from rain/fog when targeting
- [ ] Heritage: Taloned — talons unarmed (1d4 slashing, agile/finesse/unarmed/versatile piercing)

---

## Versatile Heritages (5)

- [ ] Versatile heritages occupy the heritage slot; character has no normal ancestry heritage abilities
- [ ] Characters gain access to versatile heritage feat list **plus** their original ancestry feat list
- [ ] Sense upgrade rule: if ancestry grants low-light vision and versatile heritage would also grant it, heritage instead grants darkvision
- [ ] All versatile heritages have the Uncommon trait (require GM approval to select)

### Aasimar
- [ ] Heritage grants aasimar trait, low-light vision upgrade rule
- [ ] Feat `Lawbringer`: succeed on emotion effect save → critical success instead

### Changeling
- [ ] Heritage grants changeling trait, low-light vision upgrade rule
- [ ] Feat `Slag May`: cold iron claw unarmed attack (1d6 slashing, brawling, grapple, unarmed, cold iron material)

### Dhampir
- [ ] Heritage grants dhampir trait, negative healing, low-light vision upgrade rule
- [ ] Negative healing: positive energy damages, negative energy heals; treated as undead for energy effect rules
- [ ] Feat `Dhampir Fangs`: fangs unarmed attack (1d6 piercing, brawling, grapple, unarmed)

### Duskwalker
- [ ] Heritage grants duskwalker trait; immune to becoming undead (body and spirit); low-light vision upgrade rule
- [ ] Passive: detects haunts without actively Searching (still must meet other detection requirements)

### Tiefling
- [ ] Heritage grants tiefling trait, low-light vision upgrade rule

---

## Additional Ancestry Options (Existing Ancestries)

- [ ] All APG supplemental ancestry feats for Core Rulebook ancestries are loadable as ancestry feat options for those ancestries
- [ ] APG introduces additional ancestral unarmed attacks for Core ancestries; each added to unarmed attack table with correct stats

---

## APG Backgrounds

- [ ] APG backgrounds follow the same data format as CRB backgrounds: 2 ability boosts (1 fixed, 1 free), skill training, skill feat grant
- [ ] Rare backgrounds are tagged Rare and gated behind GM approval
- [ ] Background `Haunted`: Aid from entity; on failure → Frightened 2; on crit fail → Frightened 4; initial Frightened not reducible by prevention effects
- [ ] Background `Fey-Touched`: `Fey's Fortune` 1/day free-action fortune effect on a skill check (roll twice, use better)
- [ ] Background `Returned`: grants Diehard feat automatically (no selection — automatic grant)

---

## Integration Checks

- [ ] All 5 new ancestries appear in ancestry selection list with correct traits, HP, speed, and ability modifiers
- [ ] Versatile heritages appear in heritage selection when any ancestry is chosen; tooltip explains the slot replacement rule
- [ ] Sense upgrade rule resolves automatically at character creation when both ancestry and heritage would grant low-light vision
- [ ] Uncommon trait on versatile heritages gates selection behind GM approval flag
- [ ] APG backgrounds appear in background selection with correct boosts and feat grants
- [ ] Draconic Exemplar table is displayed at character creation for Kobold; stored and referenced by other kobold abilities

## Edge Cases

- [ ] Grave Orc and Dhampir negative healing: `positive_damage_heals = false`, `negative_damage_heals = true`; both apply the undead energy rules
- [ ] Kobold Spellscale: only grants 1 cantrip slot; does not create a full spell list or progression
- [ ] Tengu Skyborn: fall immunity (not just reduction); fall damage = 0 regardless of height
- [ ] Two characters with versatile heritages of different types have independent heritage feat lists
