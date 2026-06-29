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
