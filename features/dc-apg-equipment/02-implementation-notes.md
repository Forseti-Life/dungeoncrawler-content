# Implementation Notes — dc-apg-equipment

**Commit:** `fa1cea0be`
**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/EquipmentCatalogService.php`

## What was implemented

### Structure changes
- Extended `VALID_TYPES` from `['weapon','armor','shield','gear']` to also include `'alchemical'`, `'consumable'`, `'magic'`, `'snare'`
- All APG items tagged `'source' => 'apg'` for future filtering
- No new routes or controllers — all data is served through existing `EquipmentCatalogController` which already handles arbitrary types via `getByType()`

### New item types and stat blocks

| New type | Stat block key | Items added |
|---|---|---|
| `alchemical` | `alchemical_stats` | 11 items |
| `consumable` | `consumable_stats` | 5 items |
| `magic` | `magic_stats` | 11 items |
| `snare` | `snare_stats` | 2 items |

### APG Weapons (3)
- **sword-cane**: martial/sword, 1d6 piercing, `concealed_identity: TRUE` + inspection check description
- **bola**: martial/sling, 1d6 bludgeoning, thrown-20, `on_hit_effect: trip_attempt` pointing to standard Trip rules
- **daikyu**: martial/bow, 1d8 piercing, range 80, `mounted_restriction.rule: left_side_only`

### APG Gear (3)
- **detectives-kit**: `item_bonus` block with +1 to investigation checks (Recall Knowledge, Seek, examination)
- **dueling-cape**: `deploy_action: Interact`, `deployed_bonuses` array (+1 AC, +1 Feint)
- **net**: `modes` block (rope_attached + thrown), `net_effects` (flat-footed, -10 Speed, Escape DC 16, ally Interact to remove)

### Alchemical Items (11)
- **blight-bomb/dread-ampoule/crystal-shards**: bombs integrating with existing splash system
- **focus-cathartic/sinew-shock-serum**: counteract consumables with `counteract_modifiers` array per item level tier (+6/+8/+19/+28)
- **cerulean-scourge**: 3-stage poison with `stages` array (stages 1–3)
- **timeless-salts**: `revival_window_extension: 1 week`
- **universal-solvent**: `auto_counteract_sovereign_glue: TRUE`
- All others have descriptive stat blocks

### Consumable Magic (5)
- **candle-of-revealing**: `invisible_to_concealed: TRUE` (not full observed)
- **dust-of-corpse-animation**: `max_minions_total: 4`, `cap_rule` describing 5th-minion failure
- **potion-of-retaliation**: `damage_type_at_craft: TRUE`, `aura_on_hit: TRUE`
- **terrifying-ammunition**: `frightened_floor: 1`, `frightened_floor_break: concentrate action`
- **oil-of-unlife**: `negative_healing: TRUE`, `heals_undead: TRUE`

### Permanent Magic (11)
- **glamorous-buckler**: has both `magic_stats` and `shield_stats` blocks; `daily_limit: TRUE`, `dazzle_on_feint_success: TRUE`
- **victory-plate**: has both `magic_stats` and `armor_stats` blocks; `kill_tracking: TRUE`
- **four-ways-dogslicer**: has both `magic_stats` and `weapon_stats` blocks
- **slates-of-distant-letters**: `crafted_as_pair: TRUE`, `break_rule`, `words_per_use: 25`, `frequency: 1/hour per slate`
- **winged-rune**: `dismissable: TRUE`, `dismiss_note` describing falling rule on dismiss
- **urn-of-ashes**: `urn_doomed: TRUE`, `per_rest_limit: 1`
- **rod-of-cancellation**: `permanent_cancel: TRUE`, `cooldown: 2d6 hours`, `gm_flag`
- **infiltrators-accessory**: `magical_detection: FALSE` (social context only)

### APG Snares (2)
- **engulfing-snare**: `condition: immobilized`, `escape_dc: 31`, `cage_hardness: 5`, `cage_hp: 30`
- **flare-snare**: `damage: 0`, `signal: TRUE`

## Design decisions

### Dual stat blocks (magic items that are also weapons/armor/shields)
Three items have two stat blocks: `glamorous-buckler` (magic + shield), `victory-plate` (magic + armor), `four-ways-dogslicer` (magic + weapon). This mirrors how CRB items are structured — the base item stats are in their respective `_stats` block; magical properties are in `magic_stats`. The UI can merge them.

### Counteract modifier arrays
Focus Cathartic and Sinew-Shock Serum store `counteract_modifiers` as arrays keyed by `item_level`, not hardcoded. This satisfies AC requirement "Counteract modifiers on Focus Cathartic/Sinew-Shock Serum stored per item tier (not hardcoded)."

### Social concealment vs. magical detection
`infiltrators-accessory` explicitly flags `magical_detection: FALSE` to document the AC requirement that it does not suppress True Seeing / Detect Magic.

### Kill tracking persistence
`victory-plate` has `kill_tracking: TRUE` with a note that kill log persists across sessions. The actual persistence mechanism is in the character data layer (character JSON), not in the equipment catalog. The catalog documents the intent.

### Rod of Cancellation
`permanent_cancel: TRUE` and a `gm_flag` on edge cases. The data layer documents the mechanic; UI should surface the GM adjudication note when the rod is used.

## KB reference
No direct prior lessons found in `knowledgebase/` for equipment catalog expansion. Lesson to record: when adding new item types to `EquipmentCatalogService`, always extend `VALID_TYPES` in the same commit — the controller validates against it.
