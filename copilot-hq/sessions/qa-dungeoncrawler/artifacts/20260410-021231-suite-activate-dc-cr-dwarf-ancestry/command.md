# Suite Activation: dc-cr-dwarf-ancestry

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-10T02:12:31+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-dwarf-ancestry"`**  
   This links the test to the living requirements doc at `features/dc-cr-dwarf-ancestry/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-dwarf-ancestry-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-dwarf-ancestry",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-dwarf-ancestry"`**  
   Example:
   ```json
   {
     "id": "dc-cr-dwarf-ancestry-<route-slug>",
     "feature_id": "dc-cr-dwarf-ancestry",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-dwarf-ancestry",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-dwarf-ancestry

## Coverage summary
- AC items: 17 (13 happy path, 2 edge cases, 2 failure modes)
- Test cases: 19 (TC-DWF-01–19)
- Suites: playwright (character creation flows)
- Security: AC exemption granted (no new routes)

---

## TC-DWF-01 — Core stats: HP, size, speed
- Description: Dwarf ancestry grants HP 10, Medium size, Speed 20 ft
- Suite: playwright/character-creation
- Expected: character.hp_from_ancestry = 10; size = Medium; speed = 20
- AC: Stats-1

## TC-DWF-02 — Ability boosts and flaw
- Description: Constitution and Wisdom get fixed boosts; one free boost; Charisma has a flaw
- Suite: playwright/character-creation
- Expected: con +2, wis +2, one player-chosen +2; cha –2 applied automatically
- AC: Stats-2, Stats-3

## TC-DWF-03 — Traits
- Description: Dwarf and Humanoid traits applied
- Suite: playwright/character-creation
- Expected: character.traits includes [Dwarf, Humanoid]
- AC: Stats-4

## TC-DWF-04 — Starting languages
- Description: Common and Dwarven granted automatically
- Suite: playwright/character-creation
- Expected: character.languages includes [Common, Dwarven]
- AC: Stats-5

## TC-DWF-05 — Bonus languages by Int modifier
- Description: Each point of positive Int modifier unlocks one bonus language from: Gnomish, Goblin, Jotun, Orcish, Terran, Undercommon
- Suite: playwright/character-creation
- Expected: bonus_language_slots = max(0, int_modifier); only listed languages selectable
- AC: Stats-6

## TC-DWF-06 — Clan Dagger free item
- Description: Every Dwarf receives a free Clan Dagger at character creation
- Suite: playwright/character-creation
- Expected: character.inventory contains Clan Dagger; gold not deducted
- AC: ClanDagger-1, Failure-2

## TC-DWF-07 — Clan Dagger taboo warning
- Description: Selling the Clan Dagger triggers a system warning (taboo flag)
- Suite: playwright/character-creation
- Expected: sell action on Clan Dagger shows taboo_warning; does not silently succeed
- AC: ClanDagger-2

## TC-DWF-08 — Low-Light Vision
- Description: Dwarf has Low-Light Vision (dim light treated as bright; double range from light sources)
- Suite: playwright/character-creation
- Expected: character.senses includes low_light_vision
- AC: LLV-1

## TC-DWF-09 — Feat: Dwarven Lore
- Description: Grants trained in Crafting and Religion; Dwarven Lore subcategory skill added
- Suite: playwright/character-creation
- Expected: skills.crafting ≥ trained; skills.religion ≥ trained; skills.dwarven_lore exists
- AC: Feat-1

## TC-DWF-10 — Feat: Dwarven Weapon Familiarity
- Description: Trained in battleaxe, pick, and warhammer; Dwarf weapon trait feats apply
- Suite: playwright/character-creation
- Expected: weapon_proficiencies includes battleaxe, pick, warhammer at trained; dwarf_weapon_feats unlocked
- AC: Feat-2, Edge-1

## TC-DWF-11 — Feat: Rock Runner
- Description: Acrobatics DCs on uneven stone surfaces reduced; not flat-footed on uneven/slippery stone
- Suite: playwright/character-creation
- Expected: stone_surface_acrobatics_dc_reduction flag active; flat_footed_stone_immunity = true
- AC: Feat-3, Edge-2

## TC-DWF-12 — Feat: Stonecunning
- Description: +2 Perception to notice unusual stonework; auto-rolled when passing within 10 ft
- Suite: playwright/character-creation
- Expected: perception_bonus_stonework = +2; auto_check_trigger = within_10ft_stonework
- AC: Feat-4

## TC-DWF-13 — Feat: Unburdened Iron
- Description: Removes –5 ft Speed penalty from medium/heavy armor; reduces armor Speed penalties by 5 ft (min 0)
- Suite: playwright/character-creation
- Expected: armor_speed_penalty reduced by 5 (floor 0); the standard –5 penalty for medium/heavy = 0
- AC: Feat-5

## TC-DWF-14 — Feat: Vengeful Hatred
- Description: +1 circumstance bonus to damage vs. one selected type (giants/humanoids/orcs/ogrekin); type chosen at selection
- Suite: playwright/character-creation
- Expected: vengeful_hatred.target_type set on feat selection; damage_bonus = +1 circumstance vs. that type
- AC: Feat-6

## TC-DWF-15 — Heritage: Ancient-Blooded Dwarf
- Description: +1 circumstance to saves vs. magic; once/day free action Aid a save
- Suite: playwright/character-creation
- Expected: save_bonus_magic = +1 circumstance; daily_aid_action_available = true
- AC: Heritage-1

## TC-DWF-16 — Heritage: Death Warden Dwarf
- Description: Additional saving throw vs. necromancy on CritFail if no CritFail resistance exists
- Suite: playwright/character-creation
- Expected: on necromancy_crit_fail trigger, character makes extra save before applying CritFail result
- AC: Heritage-2

## TC-DWF-17 — Heritage: Forge Dwarf
- Description: Ignore heat effects of non-extreme environments; standard armor does not impede in heat
- Suite: playwright/character-creation
- Expected: heat_resistance_non_extreme = true; armor_heat_penalty = none
- AC: Heritage-3

## TC-DWF-18 — Heritage: Rock Dwarf
- Description: +1 circumstance Fortitude vs. Shove/Trip; treated as one size larger for Bulk limits
- Suite: playwright/character-creation
- Expected: fortitude_bonus_shove_trip = +1 circumstance; bulk_limit uses size_category + 1
- AC: Heritage-4

## TC-DWF-19 — Heritage: Strong-Blooded Dwarf
- Description: +1 status Fortitude vs. poison; CritSuccess expunges poison; Success reduces stage
- Suite: playwright/character-creation
- Expected: fortitude_poison_bonus = +1 status; on crit_success vs. poison: stage removed; on success: stage reduced by 1
- AC: Heritage-5

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-dwarf-ancestry

## Gap analysis reference
- DB sections: core/ch02 (Dwarf Ancestry — not yet loaded in dc_requirements; see human ancestry DB gap note)
- Depends on: dc-cr-ancestry-system ✓, dc-cr-heritage-system ✓, dc-cr-languages ✓

---

## Happy Path

### Core Stats
- [ ] `[NEW]` Dwarf ancestry: HP 10, Medium size, Speed 20 feet.
- [ ] `[NEW]` Ability boosts: Constitution, Wisdom, and one free boost.
- [ ] `[NEW]` Ability flaw: Charisma.
- [ ] `[NEW]` Traits: Dwarf, Humanoid.
- [ ] `[NEW]` Starting languages: Common, Dwarven.
- [ ] `[NEW]` Bonus languages: Gnomish, Goblin, Jotun, Orcish, Terran, Undercommon — each available at 1 bonus language per point of positive Intelligence modifier.

### Clan Dagger
- [ ] `[NEW]` Every Dwarf receives a free Clan Dagger at character creation.
- [ ] `[NEW]` Selling the Clan Dagger is flagged as a taboo (system note/warning displayed).

### Low-Light Vision
- [ ] `[NEW]` Dwarves have Low-Light Vision (see in dim light as bright light; see twice as far from light sources).

### Ancestry Feats (1st-level Dwarf feats available at character creation)
- [ ] `[NEW]` Dwarven Lore (1st): trained in Crafting and Religion; Dwarven Lore skill (special Lore subcategory).
- [ ] `[NEW]` Dwarven Weapon Familiarity (1st): trained in battleaxe, pick, and warhammer; Dwarf weapon trait feats apply.
- [ ] `[NEW]` Rock Runner (1st): Acrobatics DCs for uneven stone surfaces reduced; not flat-footed on uneven/slippery stone surfaces.
- [ ] `[NEW]` Stonecunning (1st): +2 Perception to notice unusual stonework; automatically rolled when passing within 10 ft.
- [ ] `[NEW]` Unburdened Iron (1st): removes –5 ft Speed penalty from medium/heavy armor; reduces Speed penalties from armor by 5 ft (minimum 0).
- [ ] `[NEW]` Vengeful Hatred (1st): +1 circumstance bonus to damage vs. selected creature type (giants/humanoids/orcs/ogrekin); one type chosen at selection.

### Heritage Selection (mandatory at character creation)
- [ ] `[NEW]` Ancient-Blooded Dwarf: +1 circumstance bonus to saves vs. magic; once per day Aid a save as free action.
- [ ] `[NEW]` Death Warden Dwarf: additional saving throw vs. necromancy on Crit Fail if no Crit Fail resistance.
- [ ] `[NEW]` Forge Dwarf: heat resistance; ignore the heat effects of non-extreme environments and standard armor.
- [ ] `[NEW]` Rock Dwarf: +1 circumstance bonus to Fortitude against Shove and Trip; treated as 1 size larger for item Bulk limits.
- [ ] `[NEW]` Strong-Blooded Dwarf: +1 status bonus to Fortitude saves vs. poison; reduce poison stage on Crit Success (expunge on Success).

---

## Edge Cases
- [ ] `[NEW]` Dwarf Dwarven Weapon Familiarity + class weapon proficiency: trained in all marked as Dwarf weapons (group treats them as martial if already in martial group, or simple if not yet trained).
- [ ] `[NEW]` Rock Runner: does NOT prevent flat-footed from other sources (only stone uneven surfaces exempted).

## Failure Modes
- [ ] `[TEST-ONLY]` Dwarf Speed 20 ft: default for Medium creature; does not auto-reduce to 20 (must be set explicitly).
- [ ] `[TEST-ONLY]` Clan Dagger free item: does not cost gp at character creation; appears in inventory automatically.

## Security acceptance criteria
- Security AC exemption: game-mechanic ancestry selection; no new routes or user-facing input beyond existing character creation forms
- Agent: qa-dungeoncrawler
- Status: pending
