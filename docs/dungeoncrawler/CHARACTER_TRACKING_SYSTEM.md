# Character Tracking & Continuity System

## Overview

The Character Tracking system provides **persistent NPC continuity** across campaign sessions by tracking encounter survivors, generating AI-powered backstories, and enabling context-aware character reappearance in future encounters.

## Purpose

- **World Persistence**: NPCs remember previous encounters with the party
- **Narrative Depth**: Survivors develop backstories, motivations, and relationships
- **Dynamic Encounters**: Characters can reappear appropriately (vengeful enemies, grateful allies, fearful escapees)
- **Campaign Continuity**: Builds a living world where actions have consequences

## Architecture

### Database Schema

**Table**: `dc_campaign_npcs`

```sql
CREATE TABLE dc_campaign_npcs (
  character_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id INT UNSIGNED NOT NULL,
  
  -- Entity Data
  entity_id VARCHAR(255) NOT NULL,           -- Original creature type
  entity_instance JSON NOT NULL,              -- Complete entity_instance object
  
  -- First Encounter
  first_encounter_room_id VARCHAR(255),
  first_encounter_date TIMESTAMP,
  first_encounter_level INT UNSIGNED,
  
  -- Character State
  status ENUM('alive', 'dead', 'escaped', 'friendly', 'hostile', 'neutral', 'captured', 'unknown'),
  disposition ENUM('friendly', 'neutral', 'hostile', 'fearful', 'grateful', 'curious', 'suspicious'),
  
  -- AI-Generated Content
  backstory TEXT,                             -- Character history
  personality_traits TEXT,                    -- Behavioral quirks
  motivations TEXT,                           -- What drives them
  
  -- Reappearance Tracking
  reappearance_count INT UNSIGNED DEFAULT 0,
  last_seen_room_id VARCHAR(255),
  last_seen_date TIMESTAMP,
  can_reappear BOOLEAN DEFAULT TRUE,
  
  -- Mechanical State
  current_hp INT,
  current_level INT,
  experience_gained INT DEFAULT 0,
  
  -- Narrative Context
  notable_events JSON,                        -- Array of significant interactions
  relationships JSON,                         -- Relationships with party members
  tags JSON,                                  -- Filtering tags (quest_giver, merchant, etc.)
  preferred_locations JSON,                   -- Where character might appear
  
  INDEX (campaign_id, can_reappear, status)
)
```

### Service Layer

#### CharacterTracking Service

**Location**: `src/Service/CharacterTrackingService.php`

**Key Methods**:

```php
// Process survivors after encounter resolution
processSurvivors(array $context): array

// Find characters available for reappearance
findReusableCharacters(array $context): array

// Record character reappearance
recordReappearance(int $character_id, array $context): bool

// Mark character as permanently dead
markCharacterDead(int $character_id, array $context): bool
```

**Backstory Generation**:
- Primary: AI-powered using `ai_conversation.ai_api_service`
- Fallback: Template-based for common creature types
- Includes: backstory narrative, personality traits, primary motivation

### Integration Points

#### 1. Post-Encounter Processing

After combat resolution, call `processSurvivors()`:

```php
$survivors = [
  [
    'entity_instance' => $entity,  // Full entity_instance object
    'outcome' => 'escaped',        // alive|dead|escaped|friendly|captured
    'final_hp' => 8,
  ],
];

$character_ids = $characterTracking->processSurvivors([
  'campaign_id' => $campaign_id,
  'room_id' => $room_id,
  'party_level' => 5,
  'survivors' => $survivors,
]);
```

**Status Mapping**:
- `dead` → status: dead, can_reappear: FALSE
- `escaped` → status: escaped, disposition: fearful
- `friendly` → status: friendly, disposition: grateful
- `captured` → status: captured, disposition: hostile

#### 2. Encounter Generation (Character Reuse)

The `EntityPlacerService` can inject returning characters:

```php
// In EntityPlacerService::placeEntities()
if ($this->characterTracking && ($context['allow_character_reuse'] ?? TRUE)) {
  $reusable_characters = $this->getReusableCharacters($context);
  
  // 20% chance per creature to reuse existing character
  if (!empty($reusable_characters) && mt_rand(1, 100) <= 20) {
    $entity_instance = $this->reuseCharacter($reusable_characters, $entity, $context);
  }
}
```

**Context-Aware Filtering**:
- **Dungeons**: Hostile/fearful/suspicious characters (vengeful enemies)
- **Taverns**: Friendly/grateful/curious characters (allies, quest givers)
- **Wilderness**: Neutral/curious characters (chance encounters)

#### 3. Recording Reappearance

When a tracked character reappears:

```php
$characterTracking->recordReappearance($character_id, [
  'room_id' => $current_room_id,
  'outcome' => 'reappeared|peaceful_meeting|hostile_ambush',
]);
```

Updates:
- Increments `reappearance_count`
- Updates `last_seen_date` and `last_seen_room_id`
- Adds event to `notable_events` JSON array

## Usage Examples

### Example 1: Combat Survivor

```php
// After combat, goblin escapes at low HP
$survivors = [[
  'entity_instance' => $goblin_entity,
  'outcome' => 'escaped',
  'final_hp' => 3,
]];

$characterTracking->processSurvivors([...], $survivors);

// Character created with:
// - status: 'escaped'
// - disposition: 'fearful'
// - backstory: "A scrappy goblin warrior from the Rustfoot tribe..."
// - can_reappear: TRUE
```

### Example 2: Friendly NPC in Tavern

```php
// Party returns to town
$tavern_npcs = $characterTracking->findReusableCharacters([
  'campaign_id' => 1,
  'location_type' => 'tavern',
  'disposition_filter' => ['friendly', 'grateful', 'curious'],
  'max_count' => 3,
]);

// Returns characters who:
// - Are alive or friendly status
// - Have friendly disposition
// - Can reappear
// - Sorted by reappearance_count (prefer fresh characters)
```

### Example 3:Vengeful Enemy Returns

```php
// Escaped goblin seeks revenge
$enemies = $characterTracking->findReusableCharacters([
  'campaign_id' => 1,
  'location_type' => 'dungeon',
  'disposition_filter' => ['hostile', 'fearful'],
  'max_count' => 1,
]);

foreach ($enemies as $enemy) {
  echo "Character remembers you from level {$enemy['first_encounter_level']}!\n";
  echo "Backstory: {$enemy['backstory']}\n";
  
  // After defeating them permanently:
  $characterTracking->markCharacterDead($enemy['character_id'], [...]);
}
```

## AI Integration

### Prompt Structure

```php
$prompt = "Generate a brief backstory for an NPC in a Pathfinder 2E campaign:

Creature Type: {$entity_id}
Level: {$level}
Encounter Outcome: {$outcome}
Party Level: {$party_level}

Provide:
1. A brief backstory (2-3 sentences)
2. 2-3 personality traits
3. Primary motivation

Format as JSON: {...}";
```

### Template Fallback

If AI service unavailable, uses creature-specific templates:

```php
$templates = [
  'goblin_warrior' => [
    'backstory' => 'A scrappy goblin warrior from the Rustfoot tribe...',
    'personality_traits' => 'Cautious, opportunistic, surprisingly clever',
    'motivations' => 'Survival and finding a safer life',
  ],
  // ... more templates
];
```

## Configuration

### Enable Character Reuse

```php
$context['allow_character_reuse'] = TRUE;  // Enable (default)
$context['allow_character_reuse'] = FALSE; // Disable for boss-only rooms
```

### Reappearance Probability

Currently: **20% chance per creature** to reuse existing character

Configurable in `EntityPlacerService::placeEntities()`:

```php
if (mt_rand(1, 100) <= 20) { // Adjust percentage here
  $entity_instance = $this->reuseCharacter(...);
}
```

### Location-Based Filtering

```php
$context['location_type'] = 'tavern';   // friendly/grateful/curious
$context['location_type'] = 'dungeon';  // hostile/fearful/suspicious
$context['location_type'] = 'any';      // all dispositions
```

## Querying

### Find All Campaign NPCs

```sql
SELECT * FROM dc_campaign_npcs 
WHERE campaign_id = ? 
ORDER BY created_at DESC;
```

### Find Potential Quest Givers

```sql
SELECT * FROM dc_campaign_npcs
WHERE campaign_id = ?
  AND disposition IN ('friendly', 'grateful')
  AND can_reappear = TRUE
  AND JSON_CONTAINS(tags, '"potential_quest_giver"')
ORDER BY reappearance_count ASC
LIMIT 5;
```

### Find Vengeful Enemies

```sql
SELECT * FROM dc_campaign_npcs
WHERE campaign_id = ?
  AND status = 'escaped'
  AND disposition IN ('hostile', 'suspicious')
  AND reappearance_count < 3
ORDER BY last_seen_date ASC
LIMIT 1;
```

## Testing

**Test Script**: `/sites/dungeoncrawler/test-character-tracking.php`

Demonstrates:
1. Survivor processing after combat
2. Backstory generation (AI with template fallback)
3. Character state tracking (status, disposition, HP)
4. Context-aware reappearance (tavern friendly, dungeon hostile)
5. Permanent death marking

**Run Test**:
```bash
cd sites/dungeoncrawler
php test-character-tracking.php
```

## Future Enhancements

1. **Relationship Tracking**: Track individual party member relationships
2. **Dynamic Disposition**: Change disposition based on repeated interactions
3. **Quest Integration**: NPCs can become quest givers/objectives
4. **Merchant System**: Friendly survivors open shops
5. **Rivalry System**: Recurring villain character arcs
6. **Character Growth**: NPCs gain levels/abilities between encounters
7. **Voice/Portrait Generation**: AI-generated character art/voices
8. **Conversation History**: Track dialogue with party

## Service Registration

**File**: `dungeoncrawler_content.services.yml`

```yaml
dungeoncrawler_content.character_tracking:
  class: Drupal\dungeoncrawler_content\Service\CharacterTrackingService
  arguments:
    - '@database'
    - '@logger.factory'

dungeoncrawler_content.entity_placer:
  class: Drupal\dungeoncrawler_content\Service\EntityPlacerService
  arguments:
    - '@database'
    - '@logger.factory'
    - '@dungeoncrawler_content.schema_loader'
    - '@dungeoncrawler_content.hex_utility'
    - '@dungeoncrawler_content.terrain_generator'
    - '@dungeoncrawler_content.character_tracking'  # Injected for reuse
```

## Performance Considerations

- **Indexes**: Composite indexes on `(campaign_id, can_reappear, status)` for fast filtering
- **JSON Fields**: Used for flexible data storage (events, tags, relationships)
- **Reappearance Limits**: `reappearance_count` prevents excessive returns
- **Batch Processing**: Process survivors in single transaction
- **Caching**: Consider caching frequently-reused characters

## error Handling

```php
try {
  $characters = $characterTracking->findReusableCharacters($context);
} catch (\Exception $e) {
  // Fallback: Generate fresh encounters
  $this->logger->warning('Character tracking unavailable: @error', [
    '@error' => $e->getMessage(),
  ]);
}
```

## See Also

- [Room & Dungeon Generator Architecture](ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md)
- [Entity Placement System](ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md#entity-placement)
- [AI Integration](../../docs/technical/ai_integration.md) (if exists)
