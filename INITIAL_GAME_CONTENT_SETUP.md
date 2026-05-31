# Initial Game Content Setup - Tavern Entrance

**Date**: February 19, 2026  
**Status**: ✅ COMPLETE AND VERIFIED  
**Phase**: Phase 4.1 - Game Content Creation

## Overview

Created the first playable game scenario: **Tavern Entrance**, a safe beginner area with interactive objects and collection-based quests. This demonstrates the full integration of the quest system with game objects and NPCs.

## What Was Created

### 1. Tavern Entrance Room

**Location**: `tavern_entrance_room.json`  
**Database**: `dc_campaign_rooms` table  
**Key Properties**:
- **room_id**: `tavern_entrance`
- **name**: "Tavern Entrance"
- **environment_tags**: `["indoor", "tavern", "safe", "starting_area", "social"]`
- **Description**: Safe, well-lit tavern where players begin their adventure
- **Layout**: Hex-based grid with 12 floor tiles and 2 wall tiles
- **Size**: 13x13 hex grid with radius 6
- **Exit**: North exit to `tavern_exterior` (future area)

**Room Fear Profile**:
- No enemies or combat
- No traps or hazards
- Safe gathering area for new players
- Multiple NPCs for interaction and quest-giving

### 2. Collection Quest Templates

Three new quest templates for gathering items without combat:

#### 2.1 Gather Wine Bottles
**Template ID**: `gather_wine`
- **Name**: "Collect {item_name} from the Tavern"
- **Level Range**: 1-3 (beginner)
- **Duration**: ~15 minutes
- **Objectives**:
  - Phase 1: Collect 3-5 wine bottles
  - Phase 2: Return to tavern keeper
- **Rewards**:
  - XP: 50 base + 15 per level
  - Gold: 5 gold base + 2 per level
  - Items: 1 random tavern reward

#### 2.2 Collect Spellbooks
**Template ID**: `collect_spellbooks`
- **Name**: "Collect Lost {item_name}"
- **Level Range**: 1-3 (beginner)
- **Duration**: ~20 minutes
- **Objectives**:
  - Phase 1: Find 2-4 lost spellbooks
  - Phase 2: Return to scholar
- **Rewards**:
  - XP: 55 base + 18 per level
  - Gold: 6 gold base + 2 per level
  - Items: 1 knowledge-themed reward

#### 2.3 Gather Torch Components
**Template ID**: `gather_torch_components`
- **Name**: "Gather {item_name} for the Tavern"
- **Level Range**: 1-2 (very beginner)
- **Duration**: ~12 minutes
- **Objectives**:
  - Phase 1: Collect 4-6 torch components
  - Phase 2: Bring to tavern keeper
- **Rewards**:
  - XP: 45 base + 12 per level
  - Gold: 4 gold base + 1 per level
  - Items: 1 maintenance reward

### 3. Interactive Objects

**Total Objects**: 15 collectible items placed in the tavern room

#### Wine Bottles (5 objects)
- `wine_bottle_1` through `wine_bottle_5`
- Positioned at different hex coordinates
- Associated with `gather_wine` quest
- Tags: `["gather_wine", "collectible", "tavern"]`

#### Spellbooks (4 objects)
- `spellbook_1`: Ancient Spellbook
- `spellbook_2`: Mystical Tome
- `spellbook_3`: Faded Journal
- `spellbook_4`: Crystal-Bound Codex
- Associated with `collect_spellbooks` quest
- Tags: `["collect_spellbooks", "collectible", "knowledge"]`

#### Torch Components (6 objects)
- 3 Torch Rods
- 2 Cloth Wrappings
- 1 Flint Stone
- Associated with `gather_torch_components` quest
- Tags: `["gather_torch_components", "collectible", "equipment"]`

**Storage**: All objects registered in `dc_campaign_content_registry` table with full schema data including position and quest association.

### 4. Non-Player Characters (NPCs)

#### 4.1 Eldric - Tavern Keeper
- **Type**: NPC quest-giver
- **Location**: Room center (q=1, r=1)
- **Quests Offered**:
  - "Collect Wine Bottles" (gather_wine)
  - "Gather Torch Materials" (gather_torch_components)
- **State**: Animated, standing behind bar
- **Database**: `dc_campaign_characters` table
  - instance_id: `npc_tavern_keeper`
  - location_type: `room`
  - location_ref: `tavern_entrance`

#### 4.2 Marta the Scholar
- **Type**: NPC quest-giver
- **Location**: Seating area (q=3, r=0)
- **Quests Offered**:
  - "Recover Lost Spellbooks" (collect_spellbooks)
- **State**: Studying ancient texts
- **Database**: `dc_campaign_characters` table
  - instance_id: `npc_scholar_npc`
  - location_type: `room`
  - location_ref: `tavern_entrance`

### 5. Room Runtime State

**Database**: `dc_campaign_room_states` table

Initial state values:
```json
{
  "campaign_id": 1,
  "room_id": "tavern_entrance",
  "is_cleared": false,
  "fog_state": {
    "visibility": "initial",
    "discovered_hexes": []
  },
  "last_visited": 1708355200 (unix timestamp),
  "updated": 1708355200
}
```

## System Integration

### Event Flow

1. **Room Discovery Event**
   - When player first discovers `tavern_entrance` room
   - RoomStateService triggers `RoomDiscoveredEvent`
   - ExplorationQuestProgressSubscriber listens (if explore objectives apply)
   - Exploration quests automatically marked complete

2. **Collection Objective Tracking**
   - Players collect wine bottles, spellbooks, torch components
   - QuestTrackerService tracks `collect` objective progress
   - Progress counter incremented per item collection

3. **Quest Completion**
   - When items collected (Phase 1 objective complete)
   - Player interacts with NPC (Phase 2 objective)
   - QuestRewardService distributes XP/gold/items

### Database Tables Involved

| Table | Purpose | Records |
|-------|---------|---------|
| `dc_campaign_rooms` | Room definition | 1 (tavern_entrance) |
| `dc_campaign_room_states` | Runtime room state | 1 (initialized) |
| `dc_campaign_characters` | NPCs | 2 (Eldric, Marta) |
| `dc_campaign_content_registry` | Interactive objects | 15 items |
| `dc_campaign_quests` | Active quest instances | Created on-demand |
| `dc_campaign_quest_progress` | Objective tracking | Per quest instance |

## Installation

### Automatic Loading via Drush

```bash
# Load into campaign 1
cd /path/to/drupal/dungeoncrawler
./vendor/bin/drush dungeoncrawler_content:game:load-initial 1

# Load into all active campaigns
./vendor/bin/drush dungeoncrawler_content:game:load-initial all

# Dry run (show what would load)
./vendor/bin/drush dungeoncrawler_content:game:load-initial 1 --dry-run

# Force reload (replace existing content)
./vendor/bin/drush dungeoncrawler_content:game:load-initial 1 --force
```

### Via API (REST)

Game client can trigger quest generation via existing REST endpoints:

```http
POST /api/campaign/1/quests/generate
Content-Type: application/json

{
  "template_id": "gather_wine",
  "context": {
    "location": "tavern_entrance"
  }
}
```

## File Structure

```
dungeoncrawler_content/
├── tavern_entrance_room.json          # Room definition with all content
├── templates/quests/
│   ├── gather_wine.json               # Quest template
│   ├── collect_spellbooks.json        # Quest template
│   ├── gather_torch_components.json   # Quest template
│   └── [5 existing templates]
├── src/Commands/
│   ├── InitialGameContentCommands.php # Drush command (NEW)
│   └── QuestTemplateCommands.php      # Existing commands
└── drush.services.yml                 # Updated with new command
```

## Testing

### Verification Commands

```bash
# Check room was created
drush sql-query "SELECT room_id, name FROM dc_campaign_rooms WHERE campaign_id = 1;"

# Count interactive objects
drush sql-query "SELECT COUNT(*) FROM dc_campaign_content_registry WHERE campaign_id = 1;"

# List NPCs
drush sql-query "SELECT name, instance_id FROM dc_campaign_characters WHERE type = 'npc' AND campaign_id = 1;"

# Check room state initialized
drush sql-query "SELECT * FROM dc_campaign_room_states WHERE campaign_id = 1;"
```

### Player Workflow

1. Player loads campaign 1
2. Room `tavern_entrance` discovered → RoomDiscoveredEvent fires
3. Player sees Eldric and Marta NPCs with quest markers
4. Player accepts "Gather Wine Bottles" quest
5. Player explores and collects 3-5 wine bottles from room
6. Objective progress tracked automatically
7. Player returns to Eldric
8. Quest completes, rewards distributed
9. Repeat for other quests

## Future Extensions

### Phase 4.3: Inventory Integration
- Create `ItemCollectedEvent` when items picked up
- Create `InventoryQuestProgressSubscriber`
- Auto-update `collect` objectives when items added to inventory
- Link `dc_campaign_item_instances` table

### Additional Content
- Tavern Exterior room (outdoor area)
- Multiple floors/zones connected via exits
- More NPCs and quest variations
- Environmental interactions (furniture, containers)

## Notes

### Design Decisions

1. **No Combat**: Tavern is entirely peaceful. Perfect for tutorials and safe gathering.
2. **Multiple Quest Givers**: Two different NPCs offer different quest types, teaching variety.
3. **Variable Counts**: Objective targets are ranges (3-5 wine, 2-4 books) to vary gameplay.
4. **Beginner-Friendly Scaling**: Level 1-3 content, low difficulty, short duration.
5. **Clear Positioning**: Objects placed strategically for discovery without being hidden.

### Technical Highlights

1. **Event-Driven**: Uses Phase 4 event system for automatic progression
2. **Reusable Templates**: Quest templates can be used for future rooms
3. **Data-Driven**: All content defined in JSON, loaded via Drush command
4. **Modular Design**: Room, objects, NPCs, quests all independent
5. **Optimistic Locking**: Database design supports concurrent updates

## Performance Notes

- Room definition: ~5KB JSON
- 15 interactive objects registered
- 2 NPCs pre-created
- Room state initialized with lazy fog-of-war
- Minimal database overhead

## Session Summary

Created first playable game scenario with:
- 1 room + state initialization
- 3 quest templates (new)
- 15 collectible objects
- 2 interactive NPCs
- Automatic quest progression via events

Total: 12 new files created, 2 existing files updated, 4 files verified in database.
