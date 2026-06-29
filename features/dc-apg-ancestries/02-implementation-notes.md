# Implementation Notes — dc-apg-ancestries

## Commit
- `3c5ee2838` — feat(dungeoncrawler): implement APG ancestries, versatile heritages, and backgrounds

## What was implemented

### ANCESTRIES enrichment
Five APG ancestry entries in CharacterManager.php were expanded from single-line records to multi-line entries with `special` arrays:

| Ancestry | Added special |
|---|---|
| Catfolk | `land_on_your_feet: TRUE` (halves fall damage, prevents Prone) |
| Kobold | `draconic_exemplar: TRUE` (L1 selection against KOBOLD_DRACONIC_EXEMPLAR_TABLE) |
| Orc | No flaw confirmed (Orc has Strength + Free boost, no flaw) |
| Tengu | `sharp_beak` unarmed attack: 1d6 piercing, brawling, finesse/unarmed — all tengus, NOT heritage-gated |

### KOBOLD_DRACONIC_EXEMPLAR_TABLE (new constant)
10 dragon types with `damage_type`, `breath_shape` (line/cone), and `save` (reflex/fortitude). Used by Dragonscaled resistance and any ability referencing the exemplar.

### HERITAGES enrichment — APG ancestries

**Catfolk** (heritages: 4 → replaced `winter` with `nine-lives`):
- `clawed`: `unarmed_attack` → claw 1d6 slashing, agile/finesse/unarmed
- `hunting`: `scent` 30 ft imprecise
- `jungle`: `ignore_difficult_terrain` ['vegetation', 'rubble']
- `nine-lives`: `death_mitigation` — crit-kill → treat as normal hit, 1/lifetime

**Kobold** (heritages: 4 → replaced `dracomancer`/`spelunker` with `spellscale`/`strongjaw`/`venomtail` = 5 heritages):
- `cavern`: climb natural stone (success=half speed, crit=full), `squeeze_success_upgrade`
- `dragonscaled`: `resistance` exemplar damage type, level/2 min 1, doubled vs dragon breath
- `spellscale`: `cantrip_slots: 1`, arcane, Cha-based, trained spellcasting
- `strongjaw`: `unarmed_attack` → jaws 1d6 piercing, brawling/finesse/unarmed
- `venomtail`: `tail_toxin` 1/day 1-action, persistent poison = level

**Orc** (heritages: 4 → added `grave` = 5 heritages):
- `badlands`: ignore non-magical difficult terrain, +2 heat Fortitude
- `battle-ready`: martial_weapons_trained, +1 initiative (Perception)
- `deep-orc`: `vision_override: darkvision`
- `grave`: `negative_healing`, `positive_damage_heals: FALSE`, `negative_damage_heals: TRUE`, `undead_energy_rules: TRUE`
- `rainfall`: ignore rain/mud difficult terrain, fire resistance level/2

**Ratfolk** (heritages: 4 → enriched existing):
- `desert`: all-fours speed 30 (2 free hands), starvation×10, extreme heat/cold modified
- `sewer`: immune filth fever; disease/poison stage reduction (success: -2, crit: -3; halved for virulent)
- `shadow`: trained Intimidation, Coerce animals no-language-penalty, attitude-penalty -1 step

**Tengu** (heritages: 4 → replaced `dogtooth`/`mountainkeeper` with `stormtossed`/`taloned`):
- `jinxed`: curse/misfortune save success→crit; doomed gain → flat DC 17 to reduce by 1
- `skyborn`: `fall_damage: 0`, `fall_prevents_prone: TRUE`
- `stormtossed`: electricity resistance level/2 min 1; ignore rain/fog concealment
- `taloned`: `unarmed_attack` → talons 1d4 slashing, agile/finesse/unarmed/versatile piercing

### VERSATILE_HERITAGES (new constant)
5 versatile heritages, each with:
- `traits`: includes ancestry trait + Uncommon (GM approval required)
- `vision: 'low-light vision'` with `vision_upgrade_if_already_low_light: 'darkvision'`
- `ancestry_feats` array for their unique feat(s)

| Heritage | Key mechanics | Feats |
|---|---|---|
| Aasimar | — | Lawbringer (emotion save success→crit) |
| Changeling | — | Slag May (cold iron claw 1d6 slashing) |
| Dhampir | negative_healing, undead_energy_rules | Dhampir Fangs (fangs 1d6 piercing) |
| Duskwalker | immune_to_becoming_undead, passive_haunt_detection | — |
| Tiefling | — | — |

### BACKGROUNDS — APG additions
3 APG backgrounds added to the BACKGROUNDS constant:
- `haunted`: Aid failure→Frightened 2, crit fail→Frightened 4; initial Frightened prevention-immune
- `fey_touched`: Fey's Fortune 1/day free-action fortune on any skill check
- `returned`: `auto_grant_feat: 'Diehard'` — automatically granted, not a player selection

### TRAIT_CATALOG additions
Added: Aasimar, Changeling, Dhampir, Duskwalker, Tiefling (alphabetically merged)

## Design decisions

### Grave Orc and Dhampir share identical negative_healing structure
Both use the same four keys: `negative_healing: TRUE`, `positive_damage_heals: FALSE`, `negative_damage_heals: TRUE`, `undead_energy_rules: TRUE`. Future calculator code can check the same key regardless of which heritage/ancestry granted the effect.

### Kobold heritage count: 5 (not 4)
The AC explicitly enumerates 5 Kobold heritages. The Step 2 Selection Tree comment was updated from 4→5.

### Versatile heritages as separate constant
`VERSATILE_HERITAGES` is a top-level constant (not nested inside HERITAGES) to keep the slot-replacement logic clearly separated from ancestry-bound heritage lists.

### `auto_grant_feat` for Returned background
The Returned background grants Diehard automatically without consuming the feat selection slot. This is signaled by `special.auto_grant_feat = 'Diehard'` rather than the normal `feat` key.

## KB reference
- No prior lessons found in `knowledgebase/` for versatile heritage storage pattern; this pattern is new and should be documented.

## Verification
- `php -l CharacterManager.php` → no syntax errors
- `cd /var/www/html/dungeoncrawler && ./vendor/bin/drush cr` → Cache rebuild complete
