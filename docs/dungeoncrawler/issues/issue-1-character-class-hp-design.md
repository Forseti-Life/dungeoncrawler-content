# Issue #1: Character Creation Class HP Lookup - Design Document

## Verification Notes (2026-02-18)

- Character save flow now applies class-specific HP via `CharacterManager::getClassHP()` with fallback behavior, so the original hardcoded-only behavior is no longer fully accurate.
- The schema-driven lookup path described in this design remains partially unimplemented (`SchemaLoader::getClassData()` is still a TODO/throws).
- Treat this issue as **partially addressed**: gameplay HP variation is present, schema-loader integration is still pending.

## Overview
Design the system to retrieve class HP from schema data instead of hardcoded defaults in the character creation wizard.

## Current State
- **Location**: `CharacterCreationStepForm.php:445`
- **Problem**: `$class_hp = 8; // Default, TODO: get from class data`
- **Issue**: All classes use same HP value instead of PF2e-accurate class-specific HP

## Process Flow Design

```
Character Creation Step 2 (Class Selection)
    ↓
User selects class
    ↓
AJAX/Form Submit
    ↓
Controller retrieves class data from schema
    ↓
Extract base HP for selected class
    ↓
Calculate total HP (base + CON modifier + ancestry bonus)
    ↓
Store in character_data JSON
    ↓
Display in character preview
```

## Data Architecture

### 1. Schema Structure (character_options_step2.json)
```json
{
  "classes": [
    {
      "id": "fighter",
      "name": "Fighter",
      "hit_points": 10,
      "key_ability": ["STR", "DEX"]
    },
    {
      "id": "wizard",
      "name": "Wizard", 
      "hit_points": 6,
      "key_ability": ["INT"]
    }
    // ... more classes
  ]
}
```

### 2. Character Data Structure (in database)
```json
{
  "step": 2,
  "class": {
    "id": "fighter",
    "name": "Fighter",
    "base_hp": 10,
    "key_ability_selected": "STR"
  },
  "calculated_stats": {
    "max_hp": 18,  // 10 (class) + 6 (CON) + 2 (ancestry)
    "hp_breakdown": {
      "class_base": 10,
      "con_modifier": 6,
      "ancestry_bonus": 2,
      "other_bonuses": 0
    }
  }
}
```

## Service Layer Design

### SchemaLoader Service (existing)
**Extend with method:**
```php
/**
 * Get class data by class ID
 * 
 * @param string $class_id - Class identifier ('fighter', 'wizard', etc)
 * @return array - Class data including hit_points
 */
public function getClassData(string $class_id): array
```

### CharacterCalculator Service (new)
```php
class CharacterCalculator {
  
  /**
   * Calculate total HP for character
   * 
   * @param array $character_data - Full character data with class, abilities, ancestry
   * @return array - HP total and breakdown
   *   [
   *     'total' => 18,
   *     'breakdown' => [
   *       'class_base' => 10,
   *       'con_modifier' => 6, 
   *       'ancestry_bonus' => 2
   *     ]
   *   ]
   */
  public function calculateMaxHP(array $character_data): array
  
  /**
   * Get ability modifier from ability score
   * 
   * @param int $ability_score - Raw ability score (3-18+)
   * @return int - Modifier (-4 to +7)
   */
  public function getAbilityModifier(int $ability_score): int
  
  /**
   * Get ancestry HP bonus
   * 
   * @param string $ancestry_id - Ancestry identifier
   * @return int - HP bonus (usually 6, 8, or 10)
   */
  public function getAncestryHPBonus(string $ancestry_id): int
}
```

## Controller/Form Updates

### CharacterCreationStepForm
```php
/**
 * Process step 2 (class selection)
 */
protected function processStep2(array $form_data): array {
  // 1. Load class data from schema
  class_data = schemaLoader->getClassData(form_data['class_id'])
  
  // 2. Store class info with base HP
  character_data['class'] = {
    'id': class_data['id'],
    'name': class_data['name'],
    'base_hp': class_data['hit_points'],
    'key_ability': form_data['key_ability_choice']
  }
  
  // 3. Recalculate HP (needs ability scores from step 1)
  if (character_data['abilities']) {
    hp_data = characterCalculator->calculateMaxHP(character_data)
    character_data['calculated_stats']['max_hp'] = hp_data['total']
    character_data['calculated_stats']['hp_breakdown'] = hp_data['breakdown']
  }
  
  return character_data
}

/**
 * Recalculate all derived stats when any component changes
 */
protected function recalculateStats(array $character_data): array {
  // HP
  hp = characterCalculator->calculateMaxHP(character_data)
  character_data['calculated_stats']['max_hp'] = hp['total']
  
  // AC (armor class)
  ac = characterCalculator->calculateAC(character_data)
  character_data['calculated_stats']['ac'] = ac
  
  // Saves
  saves = characterCalculator->calculateSaves(character_data)
  character_data['calculated_stats']['saves'] = saves
  
  return character_data
}
```

## Frontend Updates

### Character Preview Widget
```javascript
/**
 * Update character preview when class selected
 */
function updateCharacterPreview(stepData) {
  if (stepData.calculated_stats) {
    // Display HP with tooltip showing breakdown
    $('#hp-display').html(
      '<span class="hp-total">' + stepData.calculated_stats.max_hp + '</span>' +
      '<span class="hp-tooltip">' + 
        'Class: ' + stepData.calculated_stats.hp_breakdown.class_base + '<br>' +
        'CON: ' + stepData.calculated_stats.hp_breakdown.con_modifier + '<br>' +
        'Ancestry: ' + stepData.calculated_stats.hp_breakdown.ancestry_bonus +
      '</span>'
    )
  }
}
```

## API Endpoints

### GET /api/character/calculate-stats
```
Request:
{
  "character_data": {
    "ancestry": { "id": "human" },
    "class": { "id": "fighter" },
    "abilities": {
      "STR": 16,
      "DEX": 14,
      "CON": 14,
      "INT": 10,
      "WIS": 12,
      "CHA": 10
    }
  }
}

Response:
{
  "max_hp": 18,
  "hp_breakdown": {
    "class_base": 10,
    "con_modifier": 4,
    "ancestry_bonus": 2,
    "other_bonuses": 2
  },
  "ac": 16,
  "saves": {
    "fortitude": 7,
    "reflex": 4,
    "will": 3
  }
}
```

## Testing Scenarios

1. **Wizard Selection**: 6 base HP + CON mod
2. **Fighter Selection**: 10 base HP + CON mod
3. **Ability Score Change**: HP recalculates when CON changes
4. **Ancestry Change**: HP includes correct ancestry bonus
5. **Multi-class**: Handle edge cases (future)

## Implementation Phases

**Phase 1**: Schema parsing
- Load class data from JSON
- Extract hit_points value

**Phase 2**: Calculator service
- Create CharacterCalculator service
- Implement HP calculation logic

**Phase 3**: Form integration
- Update CharacterCreationStepForm
- Add recalculation triggers

**Phase 4**: Frontend display
- HP preview widget
- Breakdown tooltip

**Phase 5**: Testing
- Unit tests for calculator
- Integration tests for form flow

## Questions to Resolve

1. Should HP calculation happen on every step or only after class selection?
2. Cache calculated stats or recalculate on each page load?
3. Handle temporary HP bonuses in character creation?
4. Ancestries with variable HP bonuses - how to present choices?
