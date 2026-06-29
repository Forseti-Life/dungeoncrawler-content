# Suite Activation: dc-apg-equipment

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T19:45:51+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-apg-equipment"`**  
   This links the test to the living requirements doc at `features/dc-apg-equipment/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-apg-equipment-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-apg-equipment",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-apg-equipment"`**  
   Example:
   ```json
   {
     "id": "dc-apg-equipment-<route-slug>",
     "feature_id": "dc-apg-equipment",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-apg-equipment",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-apg-equipment

## Coverage summary
- AC items: ~45 (new weapons, adventuring gear, alchemical items, consumable magic, permanent magic, APG snares)
- Test cases: 16 (TC-EQP-01–16)
- Suites: playwright (character creation, encounter, downtime)
- Security: AC exemption granted (no new routes)

---

## TC-EQP-01 — New weapons: stats, traits, proficiency grouping
- Description: Sword Cane, Bola, Daikyu appear in weapon selector with correct damage dice, traits, and proficiency grouping
- Suite: playwright/character-creation
- Expected: each weapon selectable; correct traits listed; correct proficiency group assigned
- AC: Weapons-1

## TC-EQP-02 — Sword Cane: concealed identity via social inspection
- Description: Sword Cane does not automatically identify as a weapon during social inspection; requires exceptional check
- Suite: playwright/encounter
- Expected: weapon.type != weapon_visible_by_default; investigation check required to expose identity
- AC: Weapons-2

## TC-EQP-03 — Bola: Trip attempt on successful hit
- Description: Bola's hit triggers optional Trip attempt using standard Trip rules
- Suite: playwright/encounter
- Expected: hit → Trip sub-action offered; Trip uses standard Athletics check vs. target CMD
- AC: Weapons-3

## TC-EQP-04 — Daikyu: mounted firing restriction
- Description: Daikyu can only fire left-side while mounted
- Suite: playwright/encounter
- Expected: while mounted + non-left-side firing → blocked or flagged; left-side or dismounted = allowed
- AC: Weapons-4

## TC-EQP-05 — Adventuring gear: Detective's Kit, Dueling Cape, Net
- Description: Detective's Kit: +1 item bonus to investigation checks; Dueling Cape: Interact to deploy, +1 AC/Feint; Net: two modes (rope-attached Grapple, thrown immobilize on crit)
- Suite: playwright/encounter
- Expected: bonuses apply correctly; Net immobilize only on crit; Net Escape DC = 16; ally remove = Interact action
- AC: Gear-1–3

## TC-EQP-06 — Alchemical bombs: Blight Bomb, Dread Ampoule, Crystal Shards
- Description: Blight Bomb: poison + persistent poison + splash; Dread Ampoule: Enfeebled 2 for 1 turn + fear/emotion traits; Crystal Shards: caltrops or climbing handholds (context-dependent)
- Suite: playwright/encounter
- Expected: all integrate with existing bomb/splash system; correct damage types; condition durations tracked
- AC: Alchemical-1–3

## TC-EQP-07 — Counteract consumables: Focus Cathartic and Sinew-Shock Serum
- Description: Focus Cathartic counteracts Confused or Stupefied; Sinew-Shock Serum counteracts Clumsy or Enfeebled; counteract modifier scales per item tier
- Suite: playwright/encounter
- Expected: counteract follows standard counteract check; modifier stored per item tier (+6/+8/+19/+28); only one condition per use
- AC: Alchemical-4–5

## TC-EQP-08 — Alchemical utility items: Olfactory Obfuscator, Leadenleg, Forensic Dye
- Description: Olfactory Obfuscator: concealment vs. scent-based detectors; Leadenleg: Speed reduction (Fortitude resists); Forensic Dye: tracking mark; +to Seek/Track
- Suite: playwright/encounter
- Expected: each item effect applied correctly; concealment vs. scent uses existing concealment flag; Speed reduction stored per item entry
- AC: Alchemical-6–8

## TC-EQP-09 — Preservation items: Timeless Salts, Universal Solvent, Cerulean Scourge
- Description: Timeless Salts: prevent corpse decay 1 week; Universal Solvent: auto-counteract sovereign glue; Cerulean Scourge: 3-stage poison
- Suite: playwright/downtime
- Expected: Timeless Salts extends revival window (7-day flag set); Universal Solvent uses counteract check vs. non-sovereign adhesives; poison stages stored per entry
- AC: Alchemical-9–11

## TC-EQP-10 — Consumable magic: Candle of Revealing, Dust of Corpse Animation
- Description: Candle of Revealing: removes invisible in area (concealed not observed); Dust of Corpse Animation: 1 undead minion, 1-min duration, max 4 total
- Suite: playwright/encounter
- Expected: invisible → concealed (not observed) after candle; undead count tracks ≤4 cap; duration expires at 1 minute
- AC: Consumable-1–2

## TC-EQP-11 — Consumable magic: Potion of Retaliation, Terrifying Ammunition, Oil of Unlife
- Description: Potion of Retaliation: aura on hit using specified damage type; Terrifying Ammunition: Frightened floor 1 until concentrate action; Oil of Unlife: negative healing applies to undead
- Suite: playwright/encounter
- Expected: retaliation damage type set at craft time; frightened min = 1 until concentrate; Oil heals undead, does not harm
- AC: Consumable-3–5

## TC-EQP-12 — Permanent magic: Glamorous Buckler, Victory Plate, Rope of Climbing
- Description: Glamorous Buckler: Feint bonus while raised, daily dazzle on Feint success; Victory Plate: kill tracking, resistance grant; Rope of Climbing: command-following animation
- Suite: playwright/encounter
- Expected: Glamorous Buckler bonus only while raised; dazzle daily limit enforced; Victory Plate kill log persists; rope commands work
- AC: Permanent-1–3

## TC-EQP-13 — Permanent magic: Slates of Distant Letters, Four-Ways Dogslicer
- Description: Slates: crafted as pair; breaking one destroys both; 25 words/activation; 1/hour per slate; Dogslicer: 3 rune slots swappable, 1d6 damage to activate
- Suite: playwright/encounter
- Expected: slate destruction propagates to partner; word count ≤ 25; 1-hour cooldown per slate; Dogslicer swap = Interact; activation damage rolls 1d6
- AC: Permanent-4–5

## TC-EQP-14 — Permanent magic: Winged Rune, Wand of Overflowing Life, Wand of Snowfields, Urn of Ashes, Rod of Cancellation
- Description: Winged Rune: 5-min fly, 1/hour; Wand of Overflowing Life: free bonus heal; Wand of Snowfields: difficult terrain; Urn of Ashes: reaction doomed protection; Rod: permanent cancel with 2d6-hour cooldown
- Suite: playwright/encounter
- Expected: each item respects frequency limit; Rod cooldown rolls 2d6 hours; Urn single doomed reduction per rest
- AC: Permanent-6–10

## TC-EQP-15 — APG snares: Engulfing Snare, Flare Snare
- Description: Engulfing Snare: immobilized, Hardness 5 HP 30, Escape DC 31; Flare Snare: signal only, no damage
- Suite: playwright/encounter
- Expected: APG snares appear in snare selector alongside CRB snares; Engulfing Snare stats correct; Flare Snare damage = 0
- AC: APGSnares-1–2

## TC-EQP-16 — Infiltrator's Accessory: social context concealment
- Description: Concealment handled by social context rules (not magical detection evasion)
- Suite: playwright/encounter
- Expected: Infiltrator's Accessory does not suppress magical detection; applies only social concealment modifier
- AC: Permanent-6 (Integration clause)

### Acceptance criteria (reference)

# Acceptance Criteria: APG Equipment and Magic Items

## Feature: dc-apg-equipment
## Source: PF2E Advanced Player's Guide, Chapter 5 & 6

---

## New Weapons and Adventuring Gear

- [ ] `Sword Cane`: looks like a mundane cane while sheathed; social inspection does not automatically identify it as a weapon (requires exceptional check)
- [ ] `Bola`: thrown ranged weapon, no reload; on successful hit may attempt to Trip the target
- [ ] `Daikyu`: bow with mounted firing restriction — can only fire left-side when mounted
- [ ] All new weapons use standard weapon mechanics from Core Rulebook (damage dice, traits, proficiency)
- [ ] `Detective's Kit`: +1 item bonus on investigation/examination skill checks (Recall Knowledge, Seek, and similar)
- [ ] `Dueling Cape`: Interact action required to deploy; while deployed grants +1 item bonus to AC and +1 to Feint checks
- [ ] `Net` (two modes):
  - Rope-attached mode: extends Grapple range to 10 feet
  - Thrown mode: ranged attack; on critical hit, immobilizes target
  - Net imposes flat-footed and –10 ft Speed penalty; Escape DC 16; adjacent ally can remove with Interact action

---

## Alchemical Items (APG Additions)

- [ ] `Blight Bomb`: deals poison damage + persistent poison damage + splash damage (three-component bomb)
- [ ] `Dread Ampoule`: hit imposes Enfeebled 2 until start of thrower's next turn; has fear/emotion traits
- [ ] `Crystal Shards`: splash creates crystals acting as caltrops on floor, or climbing handholds on vertical surfaces (context-dependent effect)
- [ ] `Focus Cathartic`: attempts to counteract Confused or Stupefied (one condition per use; uses counteract rules); counteract modifier scales: +6 (L2), +8 (L4), +19 (L12), +28 (L18)
- [ ] `Sinew-Shock Serum`: attempts to counteract Clumsy or Enfeebled (one condition per use; same counteract scaling)
- [ ] `Olfactory Obfuscator`: suppresses scent-based detection; grants concealment vs. creatures using precise scent
- [ ] `Leadenleg`: reduces target's Speed (per-item entry reduction); Fortitude save resists
- [ ] `Cerulean Scourge`: high-level 3-stage affliction poison with escalating damage (stage 1/2/3 effects stored per item entry)
- [ ] `Timeless Salts`: prevent corpse decay for 1 week; extends viable magical revival window
- [ ] `Universal Solvent`: auto-counteracts sovereign glue; uses counteract check vs. other adhesives
- [ ] `Forensic Dye`: creates tracking mark on target; improves Seek/Track checks against marked target

---

## Consumable Magic Items (APG Additions)

- [ ] `Candle of Revealing`: removes invisible condition from creatures in area (not full visibility — concealed, not observed)
- [ ] `Dust of Corpse Animation`: creates one temporary undead minion (1-minute duration; maximum 4 total minions including this one at any time)
- [ ] `Potion of Retaliation`: damage type must be specified when crafted; deals that type in a damaging aura when holder is hit
- [ ] `Terrifying Ammunition`: on failed save, target cannot reduce Frightened below 1 until spending a concentrate action
- [ ] `Oil of Unlife`: applies negative healing to undead target (heals undead; does not harm them); follows standard potion application rules

---

## Permanent Magic Items (APG Additions)

- [ ] `Glamorous Buckler`: grants Feint bonus while shield is raised; daily activation on successful Feint causes dazzled condition on target
- [ ] `Victory Plate`: tracks creature kills of level ≥ plate level; records heraldic information; activated to grant resistance based on a trait of slain creatures
- [ ] `Rope of Climbing`: animates on activation; follows commands: stop, fasten, detach, knot, unknot
- [ ] `Slates of Distant Letters`: must be crafted as a pair; one slate breaking shatters both; 25 words per activation; maximum 1/hour use per slate
- [ ] `Four-Ways Dogslicer`: 3 property rune slots that can be swapped via 1-action Interact; activation costs 1d6 damage of the newly-activated type
- [ ] `Infiltrator's Accessory`: concealment property handled by social context rules (not magical detection evasion)
- [ ] `Winged Rune`: grants timed Fly Speed; frequency 1/hour; duration 5 minutes; dismissable early
- [ ] `Wand of Overflowing Life`: each activation grants caster a free 1-action heal targeting themselves (bonus effect on top of standard wand spell)
- [ ] `Wand of the Snowfields`: cone of cold adds environmental difficult terrain to affected area
- [ ] `Urn of Ashes`: reaction protecting against Doomed condition; finite protection (urn has its own doomed accumulation); only one doomed reduction per night's rest
- [ ] `Rod of Cancellation`: on counteract success, target magical effect or item is permanently canceled; cooldown 2d6 hours after activation

---

## APG Snares (Equipment)

- [ ] `Engulfing Snare`: creates cage structure; trapped creature gains immobilized condition; escape requires Escape check (DC 31) or destroying cage (Hardness 5, HP 30)
- [ ] `Flare Snare`: signal device — no damage; emits bright light; useful for scouting and alarm configurations

---

## Integration Checks

- [ ] New weapons appear in weapon selection with correct damage dice, traits, and proficiency grouping
- [ ] Sword Cane's concealed identity: social checks to identify it use standard Investigation/Perception rules
- [ ] Bola Trip attempt uses standard Trip rules after successful hit
- [ ] Alchemical bomb items (Blight Bomb, Dread Ampoule, Crystal Shards) integrate with existing bomb/splash system
- [ ] Counteract modifiers on Focus Cathartic/Sinew-Shock Serum stored per item tier (not hardcoded)
- [ ] APG snares appear in the snare selector alongside Core Rulebook snares
- [ ] Victory Plate kill tracking persists across sessions

## Edge Cases

- [ ] Dust of Corpse Animation 4-minion cap: attempting to create a 5th minion fails or destroys oldest
- [ ] Terrifying Ammunition failure state: Frightened reduction specifically blocked (normal recovery via mental checks still works for other conditions)
- [ ] Winged Rune dismissal: fly speed removed immediately; falling rules apply normally if in the air
- [ ] Rod of Cancellation: permanent cancellation — not temporary or counteractable (GM adjudication flag for edge cases)
- Agent: qa-dungeoncrawler
- Status: pending
