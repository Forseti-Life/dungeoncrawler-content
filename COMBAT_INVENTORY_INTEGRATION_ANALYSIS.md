# Combat System & Inventory Integration Analysis

## Current State

### Three Separate Systems

1. **Item Template System** (dungeoncrawler_content_registry + item_instances)
   - 1000+ items extracted from PF2e reference books
   - Stored in database with schema_data JSON
   - Used during character creation (step 7)
   - Data includes: item_id, name, price_gp, bulk, hands, damage (string), traits (array)
   - **Missing**: weapon category (simple/martial), weapon group, structured damage breakdown

2. **CharacterManager::WEAPONS Constant** (NEW - just added)
   - 21 hardcoded weapons for combat calculations
   - Includes: damage dice, damage_type, category, group, hands, traits, range
   - **Purpose**: Quick lookup for combat math (attack bonuses, damage calculations)
   - **Limitation**: Only covers 21 common weapons, not all items in template system

3. **InventoryManagementService**
   - Manages item *instances* (who owns what, where it's located)
   - Tracks bulk, encumbrance, equipped/worn status
   - **Does NOT** contain weapon stat definitions
   - **Purpose**: Inventory logistics, not combat mechanics

## Integration Issues

### Problem 1: Weapon ID Mismatch
**Example**: Character has "blowgun" and "dagger" in equipment (from item templates)
- ✅ "dagger" exists in CharacterManager::WEAPONS → combat calculations work
- ❌ "blowgun" does NOT exist in CharacterManager::WEAPONS → weapon is skipped in combat

**Result**: Combat attacks may not display all equipped weapons

### Problem 2: Redundant Data Definitions
**CharacterCreationStepController.php (lines 447-452)**:
```php
'weapons' => [
  ['id' => 'longsword', 'name' => 'Longsword', 'cost' => 1, 'damage' => '1d8 S', ...],
  ['id' => 'shortsword', 'name' => 'Shortsword', 'cost' => 0.9, 'damage' => '1d6 P', ...],
  ['id' => 'dagger', 'name' => 'Dagger', 'cost' => 0.2, 'damage' => '1d4 P', ...],
]
```

**CharacterManager::WEAPONS (lines 679-721)**:
```php
const WEAPONS = [
  'dagger' => ['name' => 'Dagger', 'damage' => '1d4', 'damage_type' => 'piercing', ...],
  'longsword' => ['name' => 'Longsword', 'damage' => '1d8', 'damage_type' => 'slashing', ...],
]
```

**Both define weapons, but serve different purposes:**
- CharacterCreationStepController: For *purchasing* (needs cost, bulk)
- CharacterManager::WEAPONS: For *combat* (needs category, proficiency, structured damage)

### Problem 3: Template System Lacks Combat Data
**Item template schema_data** (from database query):
```json
{
  "item_id": "dagger",
  "damage": "1d4 piercing",  // ← String, not structured
  "traits": ["agile", "finesse"],
  "bulk": "L",
  "hands": 1
  // ❌ Missing: category (simple/martial), group (knife), range
}
```

**CharacterManager::WEAPONS** (structured for combat):
```php
'dagger' => [
  'damage' => '1d4',  // ← Dice only
  'damage_type' => 'piercing',  // ← Separate field
  'category' => 'simple',  // ← Required for proficiency calculations
  'group' => 'knife',  // ← For weapon specialization features
  'traits' => ['Agile', 'Finesse', 'Thrown 10 ft', 'Versatile S'],
]
```

## Current Integration Flow

### Character Equipment → Combat Calculations

```
Character JSON equipment:
  ↓
  [{id: "dagger", type: "weapon", damage: "1d4 piercing"}, ...]
  ↓
CharacterViewController.php (lines 313-319):
  - Extracts weapon_ids from equipment
  - If no weapons, uses class defaults
  ↓
Combat calculation loop (lines 348-401):
  - Looks up each weapon_id in CharacterManager::WEAPONS
  - If NOT found → weapon is SKIPPED
  - If found → calculates attack bonus + damage
  ↓
Character sheet displays melee_attacks + ranged_attacks arrays
```

**Critical Gap**: Weapons in equipment but not in WEAPONS constant are **invisible** to combat system

## Recommended Solutions

### Short-Term (Current Session Priority ✅)
**Status**: WEAPONS constant now has 21 common weapons
- Covers most basic character builds
- Sufficient for Wizard, Fighter, Ranger, Barbarian, Rogue defaults
- **Known limitation**: Exotic weapons from equipment will be skipped

### Medium-Term (Next Phase)
**Add fallback weapon parsing** in CharacterViewController.php:
```php
// If weapon not in WEAPONS constant, try to parse from equipment JSON
if (!isset(CharacterManager::WEAPONS[$weapon_id])) {
  $weapon = $this->parseWeaponFromEquipment($item);
  if ($weapon) {
    // Use parsed data instead of skipping
  }
}
```

Benefits:
- No weapons get skipped
- Can extract damage/traits from equipment JSON
- Assume defaults (simple category, generic group)

### Long-Term (Full Integration)
**Option 1: Extend Item Template Schema**
- Add `weapon_category` field to item.schema.json
- Add `weapon_group` field
- Migrate existing items to new schema
- Remove WEAPONS constant, query templates dynamically

**Option 2: Hybrid Approach**
- Keep WEAPONS as "combat reference table"
- Add supplementary lookup: query item templates for missing weapons
- Cache parsed combat stats

## Impact Assessment

### What Works Now ✅
- Characters with standard weapons (longsword, dagger, staff, bow) have functional combat
- All 4 playable classes (Wizard, Fighter, Ranger, Rogue) get appropriate default weapons
- Combat calculations are mathematically accurate for weapons in WEAPONS constant

### What Needs Attention ⚠️
- Characters with non-standard weapons (blowgun, war flail, scythe) won't show those in combat
- Characters created before WEAPONS constant won't have combat attacks populated
- Equipment purchased during character creation may not match WEAPONS IDs

### Breaking Changes ❌
**None**: Current changes are additive
- Existing inventory system unchanged
- Item template system unchanged  
- Combat calculations are new functionality, not replacing anything

## Verification Commands

### Check character's weapons
```bash
./vendor/bin/drush sqlq "SELECT JSON_EXTRACT(character_data, '$.equipment[*].id') FROM dc_campaign_characters WHERE name = 'Burasco'"
```

### Check which template weapons exist
```bash
./vendor/bin/drush sqlq "SELECT content_id, name FROM dungeoncrawler_content_registry WHERE content_type = 'item' AND JSON_EXTRACT(schema_data, '$.item_type') = 'weapon' LIMIT 20"
```

### Verify WEAPONS constant coverage
```php
$weapon_ids = array_keys(CharacterManager::WEAPONS);
// ['club', 'dagger', 'mace', 'spear', 'staff', 'crossbow', 'sling', ...]
// Total: 21 weapons
```

## Conclusion

**Is there redundancy?**
- ✅ YES: Weapon definitions exist in multiple places
- ⚠️ BUT: Each serves a different purpose (purchasing vs combat vs inventory tracking)

**Is it integrated?**
- ⚠️ PARTIALLY: Combat system reads from equipment JSON but only processes weapons in WEAPONS constant
- ❌ NOT FULLY: Weapons in item templates may not have combat calculation support

**Next Steps:**
1. ✅ **Current session**: WEAPONS constant added with 21 essential weapons
2. **Next session**: Add fallback parsing for weapons not in constant
3. **Future**: Consider extending item template schema or dynamic loading

**Immediate Action Required:**
- Document WEAPONS IDs that match item template IDs: dagger, longsword, shortsword, staff, crossbow, sling, mace, spear, rapier, etc.
- Test character sheet with default weapons (works)
- Test character sheet with exotic weapons (may not show in combat section)
