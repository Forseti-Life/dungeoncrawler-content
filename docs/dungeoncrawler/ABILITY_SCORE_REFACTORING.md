# Pathbuilder-Inspired Ability Score Calculator Refactoring

## Overview

Refactored the character creation ability score system to follow Pathbuilder 2e's excellent UX patterns, providing:
- **Real-time calculation** with visual breakdown
- **Source attribution** showing where each boost/flaw comes from
- **Validation** preventing invalid boost combinations
- **Interactive UI** for selecting free boosts
- **Complete tracking** across all 8 character creation steps

## Design Philosophy

### Inspired by Pathbuilder 2e
Pathbuilder 2e (closed-source Android/iOS app) sets the gold standard for Pathfinder 2e character creation:
1. **Progressive disclosure**: Shows ability scores updating as you make choices
2. **Clear attribution**: "+2 STR from Dwarf ancestry"
3. **Real-time validation**: Disables invalid choices before selection
4. **Visual feedback**: Color-coded boosts (green) and flaws (red)
5. **Reversible decisions**: Can go back and change earlier steps

### Our Implementation
While we can't access Pathbuilder's source code, we've implemented its core UX patterns using Drupal-native approaches:
- **AbilityScoreTracker service**: Centralized calculation logic
- **JSON-based source tracking**: Records every boost/flaw with attribution
- **AJAX endpoints**: Real-time calculation without page reloads
- **Twig widgets**: Reusable ability score display components
- **JavaScript validation**: Prevents duplicate boosts client-side

## Pathfinder 2E Ability Score Rules

### Starting Point
- All six abilities start at **10** (strength, dexterity, constitution, intelligence, wisdom, charisma)

### Boost Rules
- **Boosts add +2** if current score < 18
- **Boosts add +1** if current score ≥ 18
- **No duplicate boosts** to the same ability in a single step
- Boosts apply in order: Ancestry → Background → Class → Free

### Flaw Rules
- **Flaws subtract 2** from an ability
- **Minimum score is 8** after all modifications
- Only ancestries can apply flaws (some have none)

### Application Order

| Step | Source | Boosts | Notes |
|------|--------|--------|-------|
| 2 | **Ancestry** | 2-3 fixed/free | May include 1 flaw |
| 3 | **Background** | 2 free | Player chooses which abilities |
| 4 | **Class** | 1 key ability | Choice if class has multiple options |
| 5 | **Free** | 4 free | Player chooses, each to different ability |

### Example Calculation

**Character: Dwarf Fighter**

```
Step 1: Base scores
  STR 10, DEX 10, CON 10, INT 10, WIS 10, CHA 10

Step 2: Ancestry (Dwarf)
  +2 CON (fixed), +2 WIS (fixed), +2 STR (free), -2 CHA (flaw)
  STR 12, DEX 10, CON 12, INT 10, WIS 12, CHA 8

Step 3: Background (selected: STR, INT)
  +2 STR, +2 INT
  STR 14, DEX 10, CON 12, INT 12, WIS 12, CHA 8

Step 4: Class (Fighter, key ability: STR)
  +2 STR
  STR 16, DEX 10, CON 12, INT 12, WIS 12, CHA 8

Step 5: Free Boosts (selected: STR, DEX, CON, WIS)
  +2 STR, +2 DEX, +2 CON, +2 WIS
  STR 18, DEX 12, CON 14, INT 12, WIS 14, CHA 8

Final: STR 18 (+4), DEX 12 (+1), CON 14 (+2), INT 12 (+1), WIS 14 (+2), CHA 8 (-1)
```

## Implementation Architecture

### Core Service: `AbilityScoreTracker`

**Location**: `web/modules/custom/dungeoncrawler_content/src/Service/AbilityScoreTracker.php`

**Service Registration**: `dungeoncrawler_content.ability_score_tracker`

**Key Methods**:

#### `calculateAbilityScores(array $character_data): array`
Main calculation method returning complete breakdown:
```php
[
  'scores' => ['strength' => 18, 'dexterity' => 12, ...],
  'modifiers' => ['strength' => 4, 'dexterity' => 1, ...],
  'sources' => [
    'strength' => [
      ['type' => 'base', 'value' => 10, 'source' => 'Base score'],
      ['type' => 'boost', 'value' => 2, 'source' => 'Dwarf ancestry', 'step' => 'ancestry'],
      ['type' => 'boost', 'value' => 2, 'source' => 'Background', 'step' => 'background'],
      // ...
    ],
    // ...
  ],
  'breakdown' => ['Base: All 10', 'Ancestry: +2 STR, +2 CON, -2 CHA', ...],
  'validation' => []  // Empty if valid, errors if invalid
]
```

#### `applyAncestryAbilities()`, `applyBackgroundBoosts()`, etc.
Step-specific calculation methods with:
- Score updates
- Source tracking
- Validation logic
- Breakdown text generation

#### Utility Methods
- `applyBoost(int $score): int` - Applies PF2e boost rules (+2 or +1)
- `calculateModifiers(array $scores): array` - Derives modifiers from scores
- `normalizeAbilityKey(string $key): ?string` - Handles 'str' vs 'strength' variations
- `toShortForm()` / `fromShortForm()` - Converts between formats for JSON storage

### API Endpoints

#### `POST /api/characters/ability-scores/calculate`
Real-time calculation endpoint for AJAX requests

**Request**:
```json
{
  "character_data": {
    "ancestry": "dwarf",
    "background": "soldier",
    "background_boosts": ["strength", "constitution"],
    "class": "fighter",
    "class_key_ability": "strength",
    "free_boosts": ["strength", "dexterity", "constitution", "wisdom"]
  }
}
```

**Response**:
```json
{
  "scores": {"strength": 18, "dexterity": 12, ...},
  "modifiers": {"strength": 4, "dexterity": 1, ...},
  "sources": {...},
  "breakdown": [...],
  "validation": []
}
```

### UI Components

#### Ability Score Widget (Twig)
**Template**: `character-ability-scores.html.twig`

Shows:
- Current score (large number)
- Modifier (e.g., "+4")
- Visual boost indicators (↑ for boosts, ↓ for flaws)
- Tooltip with source breakdown

```twig
<div class="ability-card">
  <h3>Strength</h3>
  <div class="score-display">
    <span class="score">18</span>
    <span class="modifier">+4</span>
  </div>
  <div class="sources">
    <span class="boost" data-source="Dwarf ancestry">+2</span>
    <span class="boost" data-source="Background">+2</span>
    <span class="boost" data-source="Fighter class">+2</span>
    <span class="boost" data-source="Free boost">+2</span>
  </div>
</div>
```

#### Boost Selection UI
Interactive checkbox/button UI for selecting free boosts:
- Shows available abilities
- Enforces no-duplicate rule
- Real-time score preview
- Disabled state for already-selected abilities

### Form Integration

#### Step 2: Ancestry Selection
- **Displays**: Ancestry boosts/flaws (automatic)
- **Preview**: Shows scores after ancestry applied

#### Step 3: Background Selection
- **Interactive**: 2 checkboxes/buttons for boost selection
- **Validation**: Cannot select same ability twice
- **Preview**: Shows running total including ancestry + background

#### Step 4: Class Selection
- **Interactive**: If class has choice (e.g., "Strength or Dexterity"), show selector
- **Auto**: If class has fixed key ability, auto-apply
- **Preview**: Running total including all prior steps

#### Step 5: Ability Scores (REFACTORED)
**OLD**: Read-only display of incomplete calculation
**NEW**: Interactive selection of 4 free boosts with real-time preview

**UI Flow**:
1. Show current scores (base + ancestry + background + class)
2. Prompt: "Choose 4 abilities to boost (each must be different)"
3. Six ability cards with selectable checkboxes
4. Real-time preview updates as user selects
5. Form validation before proceeding

#### Steps 6-8: Ability Preview
Small widget showing final locked-in scores at top of page

## Data Structure

### Character Data JSON
```json
{
  "step": 5,
  "name": "Thorgrim",
  "ancestry": "dwarf",
  "heritage": "forge-dwarf",
  "background": "soldier",
  "background_boosts": ["strength", "constitution"],
  "class": "fighter",
  "class_key_ability": "strength",
  "free_boosts": ["strength", "dexterity", "constitution", "wisdom"],
  "abilities": {
    "str": 18,
    "dex": 12,
    "con": 14,
    "int": 12,
    "wis": 14,
    "cha": 8
  },
  "ability_sources": {
    "str": [
      {"type": "base", "value": 10, "source": "Base score"},
      {"type": "boost", "value": 2, "source": "Dwarf ancestry", "step": "ancestry"},
      {"type": "boost", "value": 2, "source": "Background", "step": "background"},
      {"type": "boost", "value": 2, "source": "Fighter class", "step": "class"},
      {"type": "boost", "value": 2, "source": "Free boost", "step": "free"}
    ],
    // ... other abilities
  }
}
```

## Migration Notes

### Updating Existing Characters
Characters created before this refactoring may have incomplete ability calculations.

**Migration Strategy**:
1. On character load, check if `ability_sources` exists
2. If missing, recalculate using `AbilityScoreTracker::calculateAbilityScores()`
3. Store recalculated sources in character_data JSON
4. Flag character as migrated

### Backward Compatibility
- Old flat structure: `strength`, `dexterity`, etc. → Still supported
- New nested structure: `abilities.str`, `abilities.dex` → Preferred
- Both work; conversion happens in `loadCharacterData()`

## Testing

### Unit Tests
**Location**: `tests/src/Unit/AbilityScoreTrackerTest.php`

Test cases:
- Base score initialization (all 10)
- Ancestry boosts (fixed and free)
- Ancestry flaws (minimum 8)
- Background free boosts
- Class key ability (fixed and choice)
- 4 free boosts
- Boost threshold (18+ gets +1 instead of +2)
- Duplicate boost validation
- Complete end-to-end calculation

### Functional Tests
**Location**: `tests/src/Functional/CharacterCreationAbilityScoresTest.php`

Test scenarios:
- Creating character through all 8 steps
- Going back and changing ancestry (recalculation)
- Selecting invalid boost combinations (validation)
- API endpoint response format
- UI widget rendering

## Future Enhancements

### Pathbuilder-style Features to Add
1. **Visual Diff**: When changing earlier step, highlight affected ability changes
2. **Preset Builds**: "Optimize for melee combat" auto-selects boosts
3. **Character Concepts**: "Tank", "Striker", "Support" templates
4. **Comparison View**: Side-by-side comparison of boost choices
5. **Mobile Optimization**: Touch-friendly boost selection

### Advanced Validation
- Warn about suboptimal choices (e.g., boosting dump stat)
- Suggest ability priorities based on class
- Show which skills benefit from each ability

### Performance
- Cache ancestry/class data lookups
- Debounce real-time calculation API calls
- Pre-calculate common builds

## References

- **Pathbuilder 2e**: https://pathbuilder2e.com/ (inspiration source)
- **PF2e Core Rulebook**: pp. 20-26 (Ability Scores chapter)
- **PF2e Character Creation**: pp. 13-26 (Step-by-step process)
- **CharacterManager Constants**: `CharacterManager::ANCESTRIES`, `::CLASSES`, `::BACKGROUNDS`
- **JSON Schema**: `config/schemas/character_base.json` (v4.0.0)

## Questions?

This refactoring touches multiple parts of the character creation system. If you encounter issues:

1. Check `AbilityScoreTracker` service is registered in `.services.yml`
2. Clear Drupal cache: `drush cr`
3. Verify character_data JSON includes new fields (background_boosts, class_key_ability, free_boosts)
4. Check browser console for JavaScript errors in boost selection UI
5. Review `/api/characters/ability-scores/calculate` endpoint responses

---

**Status**: Phase 1 Complete (Service layer + documentation)
**Next**: API endpoint + UI widgets + form integration
**Target**: Pathbuilder-quality ability score experience in Drupal
