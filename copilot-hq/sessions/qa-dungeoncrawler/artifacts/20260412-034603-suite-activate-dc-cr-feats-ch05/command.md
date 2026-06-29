- Status: done
- Completed: 2026-04-12T07:47:54Z

# Suite Activation: dc-cr-feats-ch05

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T03:46:03+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-feats-ch05"`**  
   This links the test to the living requirements doc at `features/dc-cr-feats-ch05/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-feats-ch05-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-feats-ch05",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-feats-ch05"`**  
   Example:
   ```json
   {
     "id": "dc-cr-feats-ch05-<route-slug>",
     "feature_id": "dc-cr-feats-ch05",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-feats-ch05",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-feats-ch05

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Chapter 5 feat system — general/skill feat slots, repeatable feats, Assurance, Recognize Spell, Trick Magic Item, Battle Medicine, Specialty Crafting, Virtuosic Performer
**KB reference:** None found specific to the feat-slot system. The Battle Medicine healer's tools gate is structurally identical to the dc-cr-skills-medicine-actions healer's tools tri-state (TC-MED-03/04); the same fixture pattern applies. The Assurance fixed-result model (10 + proficiency, modifiers ignored) is a novel result-calculation override — no prior batch TC has tested a modifier-suppression pattern. Recognize Spell Crit Fail false-identification parallels the Recall Knowledge Crit Fail obfuscation pattern from dc-cr-skills-recall-knowledge (TC-RK-05), but here the false info is a wrong spell identity (not just false facts).
**Dependency note:** dc-cr-general-feats and dc-cr-skill-feats provide the feat entity model and slot system — TCs asserting feat-slot assignment, trait gate, and repeatable tracking are conditional on those modules. dc-cr-character-leveling provides the level field used for slot-unlock timing and feat-level prerequisite validation — TCs asserting level-gated slot grants are conditional on that module. Battle Medicine healer's tools gate depends on dc-cr-equipment-system. All feat-mechanic behavior TCs (Assurance math, Recognize Spell reaction, Trick Magic Item fallback DC, Battle Medicine immunity) are immediately activatable on dc-cr-skill-system.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All feat system business logic: slot grants/gating, Assurance fixed result + modifier suppression, Recognize Spell reaction + auto-id thresholds + Crit outcomes, Trick Magic Item tradition gate + fallback DC + lockout, Battle Medicine tools gate + immunity + wounded-condition exclusion, Specialty Crafting bonus tiers, Virtuosic Performer bonus tiers |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes per security AC exemption; existing character creation and leveling form routes only |

---

## Test Cases

### Feat Category System

### TC-FEAT-01 — General feat category: accessible to all characters regardless of class or ancestry
- **Suite:** module-test-suite
- **Description:** The general feat category is available to every character. No class restriction or ancestry restriction blocks access to the general feat pool.
- **Expected:** character.class = any AND character.ancestry = any → general_feat_pool accessible.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-general-feats (feat category entity model)

### TC-FEAT-02 — General feat slots granted at levels 3, 7, 11, 15, 19
- **Suite:** module-test-suite
- **Description:** A character receives a general feat slot at exactly levels 3, 7, 11, 15, and 19. No slots at other levels (from this source).
- **Expected:** character.level ∈ {3, 7, 11, 15, 19} → general_feat_slot added; character.level ∉ {3, 7, 11, 15, 19} → no general_feat_slot from this source.
- **Notes to PM:** Confirm whether general feat slots stack with class-granted general feat slots, or if this table defines the full general-feat schedule.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (level-up event triggers slot grant)

### TC-FEAT-03 — Skill feat slots granted at level 2 and every 2 levels thereafter
- **Suite:** module-test-suite
- **Description:** Skill feat slots are granted at level 2, 4, 6, 8, 10, 12, 14, 16, 18, 20 (every even level).
- **Expected:** character.level ∈ {2, 4, 6, 8, 10, 12, 14, 16, 18, 20} → skill_feat_slot added.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (level-up event) and dc-cr-skill-feats (slot type)

### TC-FEAT-04 — Skill feat slot requires skill trait: non-skill general feats blocked
- **Suite:** module-test-suite
- **Description:** A skill feat slot can only be filled by a feat with the `skill` trait. Attempting to assign a non-skill general feat to a skill feat slot is blocked.
- **Expected:** feat.trait = skill → allowed in skill_feat_slot; feat.trait ≠ skill → blocked in skill_feat_slot.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-skill-feats (slot type enforcement) and dc-cr-general-feats (feat trait field)

### TC-FEAT-05 — Feat level = minimum level to meet proficiency prerequisite
- **Suite:** module-test-suite
- **Description:** A feat's listed level represents the minimum character level at which the character could satisfy the feat's proficiency prerequisite. The system enforces this minimum.
- **Expected:** character.level < feat.level → feat assignment blocked; character.level >= feat.level → allowed (prerequisite satisfiable).
- **Notes to PM:** Confirm whether feat level is a static field on the feat entity or computed from its prerequisites at assignment time. Automation needs a deterministic level field to compare against.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-general-feats (feat level field) and dc-cr-character-leveling (character level)

---

### Repeatable Feats

### TC-FEAT-06 — Repeatable feat: subsequent selections respect prior grants (Armor/Weapon Proficiency)
- **Suite:** module-test-suite
- **Description:** Repeatable feats (Armor Proficiency, Weapon Proficiency) track which proficiency tiers have already been granted. A subsequent selection cannot grant the same tier twice.
- **Expected:** character.has_armor_proficiency_light = true AND selection = Armor Proficiency (light) → blocked; selection = Armor Proficiency (medium) → allowed.
- **Notes to PM:** Confirm the full repeatable-feat progression model: does each selection grant one additional tier (light → medium → heavy), or is each armor/weapon category an independent selection? Automation needs deterministic progression logic.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-general-feats (repeatable feat flag + progression tracking)

---

### Assurance [Fortune Feat]

### TC-FEAT-07 — Assurance: fixed result = 10 + proficiency bonus, no other modifiers applied
- **Suite:** module-test-suite
- **Description:** When Assurance is used on a skill check, the result is exactly 10 + the character's proficiency bonus for that skill. No ability modifiers, item bonuses, status bonuses, circumstance bonuses, or penalties are added or subtracted.
- **Expected:** assurance_result = 10 + proficiency_bonus; ability_modifier NOT added; bonuses NOT added; penalties NOT subtracted.
- **Notes to PM:** Confirm that "proficiency bonus" here means the level-based proficiency modifier only (e.g., Trained = level + 2), not including ability score modifier. Automation needs the exact formula for the proficiency-bonus component.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-FEAT-08 — Assurance: modifiers and penalties silently ignored (not added, not error)
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — when Assurance fires, active bonuses and penalties on the character (e.g., a +2 status bonus from a buff, a -2 circumstance penalty from a condition) must be silently excluded from the result. The system must not add them, and must not error because they exist.
- **Expected:** character has active_bonuses > 0 AND active_penalties > 0 AND uses Assurance → result = 10 + proficiency_bonus only; no error thrown.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-FEAT-09 — Assurance: one selection per skill, second selection for same skill blocked
- **Suite:** module-test-suite
- **Description:** A character may take Assurance at most once for any given skill. Attempting to select Assurance for a skill the character already has Assurance for is blocked.
- **Expected:** character.assurance_skills contains Arcana → second Assurance (Arcana) selection blocked; Assurance (Athletics) → allowed.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-skill-feats (per-skill selection tracking) or dc-cr-general-feats

---

### Recognize Spell [Reaction]

### TC-FEAT-10 — Recognize Spell: reaction, no action cost, requires awareness of casting
- **Suite:** module-test-suite
- **Description:** Recognize Spell is a reaction (costs no actions from the character's action pool). It can only be triggered when the character is aware that a spell is being cast.
- **Expected:** action_cost = 0; trigger = spell_being_cast AND character.aware_of_cast = true; character.aware_of_cast = false → not triggerable.
- **Notes to PM:** Confirm how "awareness of casting" is modeled — is it a trigger condition on the spell-cast event, or does the character need to pass a Perception check first? Automation needs a deterministic trigger condition.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-FEAT-11 — Recognize Spell: auto-identify thresholds for common spells by proficiency rank
- **Suite:** module-test-suite
- **Description:** Common spells auto-identify (no roll needed) at these thresholds: Trained = spells of level ≤ 2; Expert = spells of level ≤ 4; Master = spells of level ≤ 6; Legendary = spells of level ≤ 10.
- **Expected:** character.tradition_rank = Trained AND spell.level ≤ 2 → auto_identified = true; character.tradition_rank = Expert AND spell.level ≤ 4 → auto_identified = true; etc. spell.rarity ≠ common → no auto-identify (roll required).
- **Notes to PM:** Confirm which skill's rank is used for the threshold check (Arcana/Occultism/Religion/Nature per tradition, or the highest of the four?). Automation needs a deterministic rank field.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-FEAT-12 — Recognize Spell Critical Success: +1 circumstance bonus to save or AC vs that spell
- **Suite:** module-test-suite
- **Description:** On Critical Success, the character gains a +1 circumstance bonus to saving throws or AC against the identified spell for the duration of that spell's effect.
- **Expected:** result.degree = critical_success → character.circumstance_bonus_vs_spell(spell_id) = +1.
- **Notes to PM:** Confirm how the "vs that spell" bonus is scoped — is it tied to the specific spell instance (cast event), to the spell type, or to the caster? Automation needs a deterministic scope for the bonus.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-FEAT-13 — Recognize Spell Critical Failure: false identification returned, not an error
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — on Critical Failure, the character receives a false spell identity (wrong spell name/effect). This must not return an error; the player sees a plausible but incorrect identification.
- **Expected:** result.degree = critical_failure → result.identified_spell ≠ actual_spell; result.is_error = false; player_facing_output is non-empty (false spell name/effect).
- **Notes to PM:** Confirm how false spell identity is generated — random selection from a pool, a fixed "adjacent" spell, or a GM-set value. Automation needs a deterministic non-null assertion strategy (at minimum: result.identified_spell is non-null and ≠ actual_spell).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Trick Magic Item [1 action, Manipulate]

### TC-FEAT-14 — Trick Magic Item: Trained gate in appropriate tradition skill
- **Suite:** module-test-suite
- **Description:** Trick Magic Item requires the character to be Trained (or higher) in the skill matching the item's magic tradition (Arcana for arcane, Religion for divine, Occultism for occult, Nature for primal).
- **Expected:** character.tradition_skill_rank < Trained → Trick Magic Item blocked; character.tradition_skill_rank >= Trained → allowed.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-FEAT-15 — Trick Magic Item: spell attack/DC uses level-based proficiency + highest mental ability score
- **Suite:** module-test-suite
- **Description:** When using a magic item via Trick Magic Item, the character's spell attack and spell DC fall back to: (level-based proficiency for the tradition skill) + (highest mental ability score modifier: Int, Wis, or Cha).
- **Expected:** trick_magic_item_dc = 10 + level_proficiency_bonus + max(Int_mod, Wis_mod, Cha_mod).
- **Notes to PM:** Confirm whether "level-based proficiency" here means the same formula as the proficiency bonus component used in Assurance (level + rank modifier), or a different calculation. Automation needs exact formula.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-FEAT-16 — Trick Magic Item Critical Failure: locked out of item until next daily preparations
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — on Critical Failure, the character is locked out of using that specific item until their next daily preparations. This lockout is per-item (not per-item-type) and is not permanent.
- **Expected:** result.degree = critical_failure → item.trick_lockout = true; item.trick_lockout_expires = next_daily_prep; subsequent Trick Magic Item attempt on same item → blocked; after daily prep event → lockout cleared.
- **Notes to PM:** Confirm how "next daily preparations" is modeled as a game event. Automation needs a fixture mechanism to trigger the daily-prep event to clear the lockout and verify TC-FEAT-16 second half.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (lockout flag on item entity; daily-prep event needs fixture)

---

### Battle Medicine [1 action, Manipulate]

### TC-FEAT-17 — Battle Medicine: healer's tools required + Trained Medicine
- **Suite:** module-test-suite
- **Description:** Battle Medicine requires both Trained Medicine AND healer's tools in inventory. Both gates must be enforced independently.
- **Expected:** character.medicine_rank < Trained → blocked; character.medicine_rank >= Trained AND character.has_healers_tools = false → blocked; both met → allowed.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (healer's tools inventory check)

### TC-FEAT-18 — Battle Medicine: uses same DC/HP table as Treat Wounds
- **Suite:** module-test-suite
- **Description:** The DC and HP restoration amounts for Battle Medicine are identical to those used by Treat Wounds (same lookup table, not a separate one).
- **Expected:** battle_medicine_dc(proficiency_rank) = treat_wounds_dc(proficiency_rank); battle_medicine_hp_restored(degree, proficiency_rank) = treat_wounds_hp_restored(degree, proficiency_rank).
- **Notes to PM:** Confirm the DC/HP table values are shared (not duplicated with drift risk). If they reference the same constant/config, automation can assert both use the same source.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (shared table assertion; no condition module required)

### TC-FEAT-19 — Battle Medicine: does NOT remove wounded condition
- **Suite:** module-test-suite
- **Description:** Battle Medicine restores HP but does not clear the wounded condition. The wounded condition must remain on the target after a successful Battle Medicine application.
- **Expected:** target.wounded_condition = N before Battle Medicine; after successful Battle Medicine → target.hp increased; target.wounded_condition still = N (unchanged).
- **Roles covered:** authenticated player
- **Status:** immediately activatable (condition field assertion; wounded condition is a pre-existing field)

### TC-FEAT-20 — Battle Medicine: per-character 1-day immunity for the target
- **Suite:** module-test-suite
- **Description:** After a character receives Battle Medicine, that character is immune to receiving Battle Medicine again for 1 day (until next daily preparations). This immunity is per-recipient character.
- **Expected:** target receives Battle Medicine → target.battle_medicine_immune = true; second Battle Medicine on same target (same day) → blocked; after daily prep → immunity cleared.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (immunity flag; daily-prep event for reset fixture)

### TC-FEAT-21 — Battle Medicine immunity does NOT block other healers' Treat Wounds on same target
- **Suite:** module-test-suite
- **Description:** The Battle Medicine 1-day immunity only blocks Battle Medicine. A different healer may still use Treat Wounds on the same target even while the Battle Medicine immunity is active.
- **Expected:** target.battle_medicine_immune = true → Treat Wounds by any healer still allowed on target.
- **Notes to PM:** Confirm whether Treat Wounds has its own separate per-healer once-per-hour/once-per-day restriction that could interact here. Automation should isolate the Battle Medicine immunity from any Treat Wounds cooldown.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-FEAT-22 — Battle Medicine on target already treated today by same healer: blocked
- **Suite:** module-test-suite
- **Description:** Edge case — if the same target already has the Battle Medicine immunity from today, a second attempt by any healer (same or different) is blocked.
- **Expected:** target.battle_medicine_immune = true AND healer = any → Battle Medicine blocked.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Specialty Crafting

### TC-FEAT-23 — Specialty Crafting: +1 circumstance bonus to relevant Craft checks
- **Suite:** module-test-suite
- **Description:** A character with Specialty Crafting gains a +1 circumstance bonus to Craft checks for items within their chosen specialty category.
- **Expected:** character.has_specialty_crafting = true AND item.category = specialty AND character.crafting_rank < Master → craft_check_modifier includes +1 circumstance bonus.
- **Notes to PM:** Confirm how "specialty" is defined on item entities (item category tag, crafting specialty field, or GM-set flag). Automation needs a deterministic item.category assertion.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-general-feats (feat entity with specialty field)

### TC-FEAT-24 — Specialty Crafting: +2 at Master proficiency
- **Suite:** module-test-suite
- **Description:** When the character's Crafting rank reaches Master, the Specialty Crafting circumstance bonus increases to +2.
- **Expected:** character.crafting_rank = Master AND character.has_specialty_crafting = true AND item.category = specialty → craft_check_modifier includes +2 circumstance bonus (not +1).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-general-feats (feat entity) and dc-cr-character-leveling (crafting rank field)

### TC-FEAT-25 — Specialty Crafting: partial applicability flagged for GM review
- **Suite:** module-test-suite
- **Description:** When an item spans multiple specialty categories (partial applicability), the system flags it for GM manual review rather than automatically applying the bonus or withholding it.
- **Expected:** item.spans_specialties = true → craft_check includes flag: manual_review_required = true; bonus application is pending GM decision (not auto-applied, not silently skipped).
- **Notes to PM:** Confirm how this GM-review flag is surfaced in the system (a field on the craft check result, an event log entry, a notification to the GM). Automation needs a deterministic flag assertion. This may not be fully automatable if the GM decision is out-of-band.
- **Automation note:** If GM decision is out-of-band (not enforced by the system), flag this TC as manual-review-only: automation can assert the flag is set, but cannot assert the GM decision outcome.
- **Roles covered:** authenticated player (GM review: admin/GM role)
- **Conditional:** depends on dc-cr-general-feats

---

### Virtuosic Performer

### TC-FEAT-26 — Virtuosic Performer: +1 circumstance bonus to chosen performance type
- **Suite:** module-test-suite
- **Description:** A character with Virtuosic Performer gains a +1 circumstance bonus to Performance checks for their chosen performance type (e.g., singing, acting, dancing).
- **Expected:** character.has_virtuosic_performer = true AND check.performance_type = chosen_type AND character.performance_rank < Master → check_modifier includes +1 circumstance bonus.
- **Notes to PM:** Confirm how "chosen performance type" is stored (a field on the feat instance vs a character-level preference). Automation needs a deterministic performance_type field.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-general-feats (feat entity with chosen-type field)

### TC-FEAT-27 — Virtuosic Performer: +2 at Master proficiency
- **Suite:** module-test-suite
- **Description:** When the character's Performance rank reaches Master, the Virtuosic Performer circumstance bonus increases to +2.
- **Expected:** character.performance_rank = Master AND character.has_virtuosic_performer = true AND check.performance_type = chosen_type → check_modifier includes +2 circumstance bonus.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-general-feats and dc-cr-character-leveling (performance rank field)

---

### Edge Cases

### TC-FEAT-28 — Skill feat slot: non-skill feat assignment blocked
- **Suite:** module-test-suite
- **Description:** Attempting to place a general feat without the `skill` trait into a skill feat slot is blocked.
- **Expected:** slot.type = skill_feat AND feat.trait ≠ skill → assignment blocked with clear error.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-skill-feats (slot type enforcement)

### TC-FEAT-29 — Assurance: second selection for same skill blocked
- **Suite:** module-test-suite
- **Description:** Edge case — a character cannot take Assurance twice for the same skill. The second selection attempt is blocked.
- **Expected:** character.assurance[Arcana] = true AND new_selection = Assurance(Arcana) → blocked; Assurance(Athletics) where character.assurance[Athletics] = false → allowed.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-skill-feats or dc-cr-general-feats (per-skill selection tracking)

---

### ACL Regression

### TC-FEAT-30 — ACL regression: no new routes; existing character creation/leveling forms retain ACL
- **Suite:** role-url-audit
- **Description:** Per the security AC exemption, feat system implementation adds no new HTTP routes. Existing character creation and leveling form routes must retain their ACL.
- **Expected:** existing routes: HTTP 200 for authenticated player; HTTP 403/redirect for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Dependency summary

| TC | Dependency | Reason conditional |
|---|---|---|
| TC-FEAT-01 | dc-cr-general-feats | Feat category entity model |
| TC-FEAT-02 | dc-cr-character-leveling | Level-up event triggers slot grant |
| TC-FEAT-03 | dc-cr-character-leveling + dc-cr-skill-feats | Even-level slot grant + slot type |
| TC-FEAT-04 | dc-cr-skill-feats + dc-cr-general-feats | Slot type enforcement + feat trait field |
| TC-FEAT-05 | dc-cr-general-feats + dc-cr-character-leveling | Feat level field + character level |
| TC-FEAT-06 | dc-cr-general-feats | Repeatable feat progression tracking |
| TC-FEAT-09 | dc-cr-skill-feats or dc-cr-general-feats | Per-skill Assurance selection tracking |
| TC-FEAT-17 | dc-cr-equipment-system | Healer's tools inventory check |
| TC-FEAT-23 | dc-cr-general-feats | Feat entity with specialty field |
| TC-FEAT-24 | dc-cr-general-feats + dc-cr-character-leveling | Specialty feat + crafting rank |
| TC-FEAT-25 | dc-cr-general-feats | Multi-specialty flag on item/feat |
| TC-FEAT-26 | dc-cr-general-feats | Feat entity with chosen-type field |
| TC-FEAT-27 | dc-cr-general-feats + dc-cr-character-leveling | Virtuosic feat + performance rank |
| TC-FEAT-28 | dc-cr-skill-feats | Slot type enforcement |
| TC-FEAT-29 | dc-cr-skill-feats or dc-cr-general-feats | Per-skill Assurance tracking |

15 TCs immediately activatable (TC-FEAT-07/08/10/11/12/13/14/15/16/18/19/20/21/22/30).
15 TCs conditional on dc-cr-general-feats, dc-cr-skill-feats, dc-cr-character-leveling, or dc-cr-equipment-system.

---

## Notes to PM

1. **TC-FEAT-02 (general feat slot stacking):** Confirm whether the level-3/7/11/15/19 general feat slot schedule is the complete source, or if class-granted general feat slots stack on top. Automation needs to know which total slot count to assert at each level.
2. **TC-FEAT-05 (feat level computation):** Confirm whether feat.level is a static field or computed from prerequisites at assignment time. If computed, automation needs the prerequisite evaluation logic.
3. **TC-FEAT-06 (repeatable feat progression model):** Confirm Armor/Weapon Proficiency repeatable progression — does each selection grant one tier (light → medium → heavy), or are armor categories independent selections? Automation needs deterministic progression path.
4. **TC-FEAT-07 (Assurance proficiency bonus formula):** Confirm that "proficiency bonus" = level + rank modifier (e.g., Trained = level + 2) without ability modifier. Automation needs the exact formula to assert the correct fixed result.
5. **TC-FEAT-10 (Recognize Spell awareness trigger):** Confirm whether "aware of casting" is a system event trigger (fires automatically when a spell is cast within range) or requires a prior Perception check. Automation needs a deterministic trigger condition.
6. **TC-FEAT-11 (tradition rank for auto-ID threshold):** Confirm which tradition skill rank is used for the auto-identify threshold — the skill matching the spell's tradition, or the highest across all tradition skills?
7. **TC-FEAT-12 (Recognize Spell Crit Success scope):** Confirm the scope of the +1 circumstance bonus — tied to the specific spell-cast instance, to the spell type, or to the caster for the encounter. Automation needs a deterministic bonus scope.
8. **TC-FEAT-13 (false spell identity generation):** Confirm how false spell identity is generated for Crit Fail (random pool, adjacent spell, GM-set). Automation needs at minimum a non-null assertion strategy.
9. **TC-FEAT-15 (Trick Magic Item DC formula):** Confirm "level-based proficiency bonus" formula matches Assurance or uses a different calculation. Automation needs exact formula.
10. **TC-FEAT-16 / TC-FEAT-20 (daily prep event):** Confirm how "next daily preparations" is modeled as a game event and how it can be triggered in test fixtures to clear lockout/immunity state.
11. **TC-FEAT-18 (shared DC/HP table):** Confirm Treat Wounds and Battle Medicine reference the same constant/config source. If duplicated, flag as a data-consistency risk.
12. **TC-FEAT-21 (Treat Wounds interaction):** Confirm whether Treat Wounds has its own once-per-hour/per-day cooldown that could interact with the Battle Medicine immunity assertion. Automation must isolate these.
13. **TC-FEAT-23/26 (specialty/performance type storage):** Confirm how specialty (for Specialty Crafting) and chosen performance type (for Virtuosic Performer) are stored — on the feat instance, on the character, or elsewhere. Automation needs deterministic fields to set up fixtures.
14. **TC-FEAT-25 (GM review automation boundary):** Confirm whether the multi-specialty flag surfaces as a system-enforced field on the craft-check result, or as an out-of-band notification. If out-of-band, TC-FEAT-25 can only assert the flag is set; the GM decision outcome is not automatable.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-feats-ch05

## Gap analysis reference
- DB sections: core/ch05/Chapter Overview (5 reqs), core/ch05/Key Feat Mechanic Notes (17 reqs), core/ch05/Non-Skill General Feats Table (2 reqs)
- Depends on: dc-cr-general-feats, dc-cr-skill-feats, dc-cr-character-leveling

---

## Happy Path

### Feat Category System
- [ ] `[NEW]` System supports a general feat category accessible to all characters regardless of class or ancestry.
- [ ] `[NEW]` General feat slots granted at levels 3, 7, 11, 15, 19.
- [ ] `[NEW]` Skill feats are a subcategory of general feats; granted at level 2 and every 2 levels thereafter.
- [ ] `[NEW]` Skill feat slot requires a feat with the `skill` trait; non-skill general feats cannot fill skill feat slots.
- [ ] `[NEW]` Feat level = minimum character level at which a character could meet the feat's proficiency prerequisite.

### Repeatable Feats
- [ ] `[NEW]` Repeatable feats (Armor Proficiency, Weapon Proficiency) track progression; subsequent selections must respect prior grants.
- [ ] `[NEW]` Each non-skill general feat is implementable as a character option at its listed level.

### Assurance [Fortune Feat]
- [ ] `[NEW]` Assurance produces a fixed result = 10 + proficiency bonus; no other bonuses, penalties, or modifiers apply.
- [ ] `[NEW]` Can be selected once per skill; each selection (per skill) is tracked independently.

### Recognize Spell [Reaction]
- [ ] `[NEW]` Recognize Spell is a reaction with no action cost; requires awareness of casting.
- [ ] `[NEW]` Auto-identify thresholds (common spells only): Trained ≤ level 2, Expert ≤ level 4, Master ≤ level 6, Legendary ≤ level 10.
- [ ] `[NEW]` Crit Success: +1 circumstance bonus to save or AC vs that spell.
- [ ] `[NEW]` Crit Failure: false identification (player sees wrong spell name/effect).

### Trick Magic Item [1 action, Manipulate]
- [ ] `[NEW]` Trick Magic Item requires Trained in the appropriate tradition skill and knowledge of the item's function.
- [ ] `[NEW]` Spell attack/DC falls back to level-based proficiency + highest mental ability score.
- [ ] `[NEW]` Crit Failure: locked out of using the item until next daily preparations.

### Battle Medicine [1 action, Manipulate]
- [ ] `[NEW]` Battle Medicine requires healer's tools and Trained Medicine; uses same DC/HP table as Treat Wounds.
- [ ] `[NEW]` Does NOT remove the wounded condition (distinct from Treat Wounds which may clear dying-adjacent states).
- [ ] `[NEW]` Per-character 1-day immunity after receiving Battle Medicine; does not block other healers from using Treat Wounds on same target.

### Specialty Crafting
- [ ] `[NEW]` Specialty Crafting grants +1 circumstance bonus to relevant Craft checks; at Master proficiency, bonus increases to +2.
- [ ] `[NEW]` GM-adjudicated partial applicability for items spanning multiple specialties (system flags for manual review).

### Virtuosic Performer
- [ ] `[NEW]` Virtuosic Performer grants +1 circumstance bonus to chosen performance type; at Master proficiency, +2.

---

## Edge Cases
- [ ] `[NEW]` Skill feat slot at level 2 filled with non-skill feat: blocked.
- [ ] `[NEW]` Assurance selected twice for same skill: blocked (one selection per skill).
- [ ] `[NEW]` Battle Medicine on target already treated today: blocked for same healer.

## Failure Modes
- [ ] `[TEST-ONLY]` Assurance modifiers/penalties: silently ignored (not added to result).
- [ ] `[TEST-ONLY]` Recognize Spell Crit Fail: returns false info, not an error.
- [ ] `[TEST-ONLY]` Trick Magic Item Crit Fail: locked out per-item per-day (not permanently).

## Security acceptance criteria
- Security AC exemption: game-mechanic feat system logic; no new routes or user-facing input beyond existing character creation and leveling forms
- Agent: qa-dungeoncrawler
- Status: pending
