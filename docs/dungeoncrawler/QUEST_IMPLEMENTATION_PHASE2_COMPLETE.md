# Quest System Implementation - Phase 2 Complete

## Completed Work

### Phase 1: Database & Services ✅ 
- 6 quest tables created (templates, chains, quests, progress, log, rewards_claimed)
- 4 service classes implemented:
  - QuestGeneratorService (490 lines) - Procedural quest generation
  - QuestTrackerService (540 lines) - Progress tracking
  - QuestRewardService (290 lines) - Reward distribution
  - QuestValidatorService (250 lines) - Prerequisite validation  
- All services registered in Drupal container
- Cache cleared, services active

### Phase 2: Quest Templates & Commands ✅
- Created templates directory: `web/modules/custom/dungeoncrawler_content/templates/quests/`
- Implemented 5 quest templates:
  1. **rescue_merchant.json** - 2-phase rescue quest with escort mechanics
  2. **clear_goblin_den.json** - Bounty quest with boss encounter
  3. **gather_herbs.json** - Collection side quest with return delivery
  4. **investigate_ruins.json** - 3-phase exploration quest with story impact
  5. **deliver_package.json** - Travel & delivery quest
- Created QuestTemplateCommands Drush command class (282 lines)
- Implemented 3 Drush commands:
  - `drush dcq-load` - Load templates from JSON into database
  - `drush dcq-list` - List all templates (table/JSON output)
  - `drush dcq-delete <template_id>` - Delete a template
- Registered commands in `drush.services.yml`
- Successfully loaded all 5 templates into database

## Database Verification

```bash
$ drush dcq-list
```

| Template ID | Name | Type | Level Range |
|-------------|------|------|-------------|
| clear_goblin_den | Clear the Goblin Den | bounty | L1-3 |
| deliver_package | Deliver Package to {destination} | delivery | L1-8 |
| investigate_ruins | Investigate the Ancient Ruins | exploration | L3-6 |
| rescue_merchant | Rescue {npc} from {location} | rescue | L2-5 |
| gather_herbs | Gather {herb_name} for the Alchemist | side_quest | L1-4 |

**Total**: 5 templates loaded

## Quest Template Structure

Each template includes:
- **template_id**: Unique identifier for procedural generation
- **name**: Display name (supports {variable} placeholders)
- **description**: Quest narrative
- **quest_type**: bounty, rescue, delivery, exploration, side_quest
- **level_min/level_max**: Party level requirements
- **objectives_schema**: Multi-phase objectives with types (kill, collect, explore, escort, interact)
- **rewards_schema**: XP (base + per_level), gold, items (loot tables), reputation
- **prerequisites**: Level, completed quests, reputation, items
- **tags**: Categorization (combat, exploration, dungeon, time_limited)
- **story_impact**: Campaign state changes, quest unlocks
- **estimated_duration_minutes**: Time estimate

## Variable System

Templates support runtime variable substitution:
- `{npc}`, `{location}`, `{target}`, `{destination}` - Dynamically filled during generation
- `{herb_name}`, `{npc_role}`, `{npc_faction}` - Context-specific replacements

Example: "Rescue {npc} from {location}" becomes "Rescue Merchant Garen from Goblin Cave"

## Drush Command Usage

### Load Templates
```bash
# Load all templates from default directory
drush dcq-load

# Clear existing templates and reload
drush dcq-load --clear

# Force reload even if templates exist
drush dcq-load --force

# Load from custom directory
drush dcq-load /path/to/templates/
```

### List Templates
```bash
# Display as table (default)
drush dcq-list

# Output as JSON
drush dcq-list --format=json
```

### Delete Template
```bash
drush dcq-delete rescue_merchant
```

## Technical Implementation Details

### Service Registration
**File**: `web/modules/custom/dungeoncrawler_content/drush.services.yml`
```yaml
services:
  dungeoncrawler_content.commands.quest_template:
    class: Drupal\dungeoncrawler_content\Commands\QuestTemplateCommands
    arguments:
      - '@database'
      - '@logger.factory'
    tags:
      - { name: drush.command }
```

### Command Class Structure
```php
class QuestTemplateCommands extends DrushCommands {
  protected Connection $database;
  protected LoggerChannelInterface $dcLogger;
  
  /**
   * @command dungeoncrawler_content:quest:load-templates
   * @aliases dcq-load
   */
  public function loadTemplates(string $directory = '', array $options = [])
  
  /**
   * @command dungeoncrawler_content:quest:list-templates
   * @aliases dcq-list
   */
  public function listTemplates(array $options = ['format' => 'table'])
  
  /**
   * @command dungeoncrawler_content:quest:delete-template
   * @aliases dcq-delete
   */
  public function deleteTemplate(string $template_id)
}
```

## Integration Points (Phase 4-7 TODO)

Services have integration placeholders for:
- **CharacterStateService**: XP/level management
- **InventoryManagementService**: Item rewards
- **CombatEngine**: Kill objective tracking
- **RoomGenerator**: Exploration/location discovery
- **ReputationSystem**: Faction reputation rewards (future)

## Next Steps: Phase 3 - API Controllers

Create REST endpoints for quest system:

### Endpoints to Implement
1. `POST /api/campaign/{campaign_id}/quests/generate` - Generate from template
2. `GET /api/campaign/{campaign_id}/quests/available` - List available quests
3. `POST /api/campaign/{campaign_id}/quests/{quest_id}/start` - Start quest
4. `PUT /api/campaign/{campaign_id}/quests/{quest_id}/progress` - Update objective progress
5. `POST /api/campaign/{campaign_id}/quests/{quest_id}/complete` - Complete quest
6. `POST /api/campaign/{campaign_id}/quests/{quest_id}/rewards/claim` - Claim rewards
7. `GET /api/character/{character_id}/quest-journal` - Character's quest log
8. `GET /api/campaign/{campaign_id}/quest-chains` - Available quest chains

### Controllers to Create
- `QuestGeneratorController` - Generation & availability endpoints
- `QuestTrackerController` - Start, progress, complete endpoints
- `QuestRewardController` - Reward claiming endpoint
- `QuestJournalController` - Character journal, quest chains

### Routing Configuration
Add to `dungeoncrawler_content.routing.yml` with JSON response format, access control, and RESTful patterns.

## Files Created/Modified

### Created
- `web/modules/custom/dungeoncrawler_content/templates/quests/` (directory)
- `web/modules/custom/dungeoncrawler_content/templates/quests/rescue_merchant.json`
- `web/modules/custom/dungeoncrawler_content/templates/quests/clear_goblin_den.json`
- `web/modules/custom/dungeoncrawler_content/templates/quests/gather_herbs.json`
- `web/modules/custom/dungeoncrawler_content/templates/quests/investigate_ruins.json`
- `web/modules/custom/dungeoncrawler_content/templates/quests/deliver_package.json`
- `web/modules/custom/dungeoncrawler_content/src/Commands/QuestTemplateCommands.php`
- `web/modules/custom/dungeoncrawler_content/drush.services.yml`

### Modified
- `web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.services.yml` - Removed Drush command registration (moved to drush.services.yml)

## Current System State

✅ **Fully Operational**:
- Database schema deployed (6 tables)
- Service layer functional (4 services)
- 5 quest templates loaded
- Drush command suite working

🔧 **Integration Needed** (Phase 4-7):
- Connect QuestTrackerService to CombatEngine for kill tracking
- Wire QuestRewardService to CharacterStateService for XP
- Connect QuestRewardService to InventoryManagementService for items
- Event listeners for automatic progress updates
- Reputation system integration

🚧 **Next Phase Ready**: API controller implementation can begin

## Testing Commands

### Verify Installation
```bash
# Check templates in database
drush sql:query "SELECT template_id, name, quest_type FROM dungeoncrawler_content_quest_templates"

# Count templates
drush sql:query "SELECT COUNT(*) FROM dungeoncrawler_content_quest_templates"

# Verify services registered
drush php:eval "print_r(\Drupal::service('dungeoncrawler_content.quest_generator'));"
```

### Generate First Quest (After Phase 3)
```bash
# Will be available after API controllers:
curl -X POST http://dungeoncrawler.local/api/campaign/1/quests/generate \
  -H "Content-Type: application/json" \
  -d '{
    "template_id": "rescue_merchant",
    "variables": {
      "npc": "Merchant Garen",
      "location": "Goblin Cave",
      "target": "goblin"
    }
  }'
```

## Documentation

- **Architecture**: `/docs/dungeoncrawler/QUEST_TRACKER_GENERATOR_ARCHITECTURE.md` (900+ lines)
- **Quick Reference**: `/docs/dungeoncrawler/QUEST_SYSTEM_QUICK_REFERENCE.md` (300+ lines)
- **This Summary**: `/docs/dungeoncrawler/QUEST_IMPLEMENTATION_PHASE2_COMPLETE.md`

## Timeline Summary

**Phases Completed**: 2 of 7
**Lines of Code**: ~2,850 (services + commands + templates)
**Database Tables**: 6 created
**Drush Commands**: 3 implemented
**Quest Templates**: 5 loaded

**Estimated Remaining** (from original plan):
- Phase 3: API Controllers (2-3 weeks)
- Phase 4-7: Integration & Testing (6-8 weeks)
- Total remaining: 10-14 weeks

**Current Status**: On track, ready for Phase 3 implementation.
