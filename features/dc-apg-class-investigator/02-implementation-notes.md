# Implementation Notes — dc-apg-class-investigator

## Commit
- `da945aec3` — feat(dungeoncrawler): implement Investigator class mechanics (136 insertions, 6 deletions)

## What was implemented

### CLASSES['investigator'] replacement in CharacterManager.php
The existing stub entry was replaced with the full APG Investigator mechanics.

#### Class record
| Field | Value |
|---|---|
| hp | 8 |
| key_ability | Intelligence |
| Perception | Expert |
| Fortitude | Trained |
| Reflex | Expert |
| Will | Expert |
| Armor | light, unarmored |
| Weapons | simple + rapier |
| trained_skills | 4 + Int + Society (always) + methodology bonus |
| class_skills | ['Society'] |

#### Devise a Stratagem
- `action_cost: 1`, `traits: ['Fortune']`, `frequency: 1/round`
- `qualifying_weapons`: agile melee, finesse melee, ranged, sap, agile unarmed, finesse unarmed
- `attack_modifier: 'Intelligence'` — replaces Str/Dex on qualifying Strike
- `stored_roll.discard_at_end_of_turn: TRUE` — cleared even if unused
- `stored_roll.discard_if_no_qualifying_strike: TRUE` — explicit edge case
- `active_lead_cost_reduction`: action_cost → 0 when target is active lead

#### Pursue a Lead
- `action_cost: '1 minute (exploration)'`
- `+1 circumstance bonus` to investigative checks against the designated lead
- `max_leads: 2`; `oldest_lead_removed_at_cap: TRUE`
- `target_types: ['creature', 'object', 'location']`

#### Clue In
- Reaction, `1/10 minutes`, trigger: successful investigative check
- Shares Pursue a Lead circumstance bonus with one ally within `30 feet`

#### Strategic Strike
- `damage_type: 'precision'`
- `precision_damage_no_stack: TRUE` — does not stack with sneak attack (highest applies per standard rules)
- Progression: 1d6 @ L1 → 2d6 @ L5 → 3d6 @ L9 → 4d6 @ L13 → 5d6 @ L17

#### Methodology (4 options, required at L1)

**Alchemical Sciences**
- `auto_grants`: Crafting proficiency + Alchemical Crafting feat
- `formula_book: TRUE`
- `versatile_vials.count_basis: 'intelligence_modifier'`; refreshed at daily preparations
- Quick Tincture: 1-action, consume one versatile vial, produce alchemical item from formulas

**Empiricism**
- `auto_grants`: one Intelligence-based skill (player choice) + That's Odd feat
- Expeditious Inspection: free action, 1/10 min, choice of Recall Knowledge / Seek / Sense Motive
- `devise_a_stratagem_lead_waiver: TRUE` — removes the active-lead requirement for **action cost reduction only**
- Note captured: waiver is for action cost only; other lead-dependent effects still require an active lead

**Forensic Medicine**
- `auto_grants`: Medicine proficiency + Battle Medicine + Forensic Acumen feats
- `battle_medicine_bonus.bonus_type: 'investigator_level'` — adds class level to Battle Medicine healing
- `battle_medicine_immunity_duration: '1 hour'` — reduced from 1 day

**Interrogation**
- `auto_grants`: Diplomacy proficiency + No Cause for Alarm feat
- `pursue_lead_social_mode: TRUE` — lead designation works in conversation/social encounters
- Pointed Question: 1-action, Intimidation or Deception, exposes inconsistency
- `requires_prior_statement: TRUE` with `prior_statement_note` for GM adjudication flag

## Design decisions

### Methodology skill as `methodology_bonus_skill: TRUE` (not hardcoded)
Each methodology grants a different skill, so the flag is stored as a boolean on the class record and resolved at methodology selection time rather than hardcoding all possible methodologies into the top-level class record.

### `precision_damage_no_stack` on Strategic Strike
Per PF2e standard rules, only the highest precision damage source applies. This is flagged explicitly so the calculator does not add Strategic Strike and Sneak Attack together.

### Edge cases from AC encoded as explicit keys
All edge cases from the AC (Devise a Stratagem stored roll discard, Empiricism waiver scope, Pointed Question prior statement requirement) are captured as data keys rather than comments — this ensures they can be tested programmatically.

## KB reference
- No prior lessons in `knowledgebase/` for class mechanic `devise_a_stratagem` pattern; this is the first Fortune-trait "stored roll" mechanic. Pattern documented here for future reference.

## Verification
- `php -l CharacterManager.php` → no syntax errors
- `cd /var/www/html/dungeoncrawler && ./vendor/bin/drush cr` → Cache rebuild complete
