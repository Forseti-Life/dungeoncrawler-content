# Dungeon Crawler Content Module Documentation

## Quest System

### Core Documentation

- **[QUEST_FULFILLMENT_PROCESS_FLOW.md](QUEST_FULFILLMENT_PROCESS_FLOW.md)** - DM-agent-aware quest fulfillment architecture
  - High-level process flow from touchpoint detection to objective completion
  - Deterministic vs ambiguous update handling
  - Incomplete/completion signaling model
  - Idempotency and duplicate-event safeguards
  - MVP rollout plan and acceptance criteria

- **[QUEST_FULFILLMENT_MVP_CONTRACTS.md](QUEST_FULFILLMENT_MVP_CONTRACTS.md)** - Implementation-ready quest fulfillment contracts
  - Canonical touchpoint event payload
  - Evaluator registry interfaces and decision contract
  - Confirmation queue data model and resolution flow
  - Minimal API/service boundaries for MVP
  - Observability and acceptance checks

## Inventory Management System

### Core Documentation

- **[INVENTORY_MANAGEMENT_SYSTEM.md](INVENTORY_MANAGEMENT_SYSTEM.md)** - Complete system architecture and API reference (3,300+ lines)
  - System overview and architecture
  - API endpoints and usage
  - Database schema and relationships
  - PF2e bulk calculation rules
  - Transaction safety patterns
  
- **[INVENTORY_IMPLEMENTATION_GUIDE.md](INVENTORY_IMPLEMENTATION_GUIDE.md)** - Developer implementation guide
  - Step-by-step implementation walkthrough
  - Code examples and patterns
  - Service integration guide
  - Testing strategies

- **[INVENTORY_REFACTORING_COMPLETE.md](INVENTORY_REFACTORING_COMPLETE.md)** - Integration refactoring details
  - Database integration (instance tables as source of truth)
  - Synchronization mechanism (JSON cache layer)
  - Batch operations implementation
  - Room inventory support
  - Data flow architecture

- **[INVENTORY_CAPACITY_RULES.md](INVENTORY_CAPACITY_RULES.md)** - Entity capacity rules and integration ⭐ NEW
  - Which entities can carry inventory (characters, containers, rooms, encounters)
  - Capacity calculations for each entity type
  - Container item system (item.schema.json integration)
  - PF2e encumbrance rules
  - Integration with character/item/object management
  - API usage examples
  - Testing considerations

### Quick Reference

#### Entity Capacity Rules

| Entity Type | Can Carry? | Capacity Formula | Location Type |
|-------------|------------|------------------|---------------|
| **Character** | ✅ Yes | `5 + STR Modifier` | `inventory` |
| **Container** | ✅ Yes | From `container_stats.capacity` | `container` |
| **Room** | ✅ Yes | Unlimited (`PHP_FLOAT_MAX`) | `room` |
| **Encounter** | ✅ Yes | `5 + STR Modifier` (as NPC) | `inventory` |
| **Obstacle** | ❌ No | N/A | N/A |

#### Common Container Capacities

- Belt Pouch: 0.4 bulk
- Satchel: 2 bulk
- Backpack: 4 bulk
- Chest (Small): 8 bulk
- Barrel: 10 bulk
- Bag of Holding (Type I): 25 bulk
- Bag of Holding (Type II): 50 bulk

#### Key Services

- **InventoryManagementService** - Core inventory operations
- **ContainerManagementService** - Container lifecycle management
- **CharacterStateService** - Character state and JSON synchronization

#### Database Tables

- **dc_campaign_item_instances** - Source of truth for all inventory
- **dc_campaign_characters** - Character state with inventory cache
- **dc_campaign_log** - Audit trail for inventory operations
- **dc_dungeon_rooms** - Room definitions for room inventory

## Item Schemas

### Core Schemas

- **[../config/schemas/item.schema.json](../config/schemas/item.schema.json)** - PF2e item definitions
  - Item types: weapon, armor, shield, consumable, adventuring_gear, etc.
  - **container_stats**: New section defining container properties
  - weapon_stats, armor_stats, shield_stats, consumable_stats
  - magic_properties, ai_generation metadata
  
- **[../config/schemas/character.schema.json](../config/schemas/character.schema.json)** - Character definitions
  - Ability scores (strength used for capacity)
  - Inventory structure
  - Equipment and gear

- **[../config/schemas/entity_instance.schema.json](../config/schemas/entity_instance.schema.json)** - Runtime instance state
  - Mutable state overlay
  - Inventory tracking
  - Combat state

## Development Patterns

### Making an Item a Container

Add `container_stats` to the item definition:

```json
{
  "item_id": "backpack-leather",
  "name": "Leather Backpack",
  "item_type": "adventuring_gear",
  "bulk": "L",
  "container_stats": {
    "capacity": 4.0,
    "capacity_reduction": 1.0,
    "can_lock": false,
    "access_time": "interact",
    "container_type": "backpack"
  }
}
```

### Checking Capacity in Code

```php
// Get character inventory capacity (based on STR)
$capacity = $inventoryService->getInventoryCapacity($character_id, 'character');

// Get current bulk
$current_bulk = $inventoryService->calculateCurrentBulk($character_id, 'character');

// Check encumbrance
$status = $inventoryService->getEncumbranceStatus($current_bulk, $capacity);
// Returns: 'unencumbered', 'encumbered', or 'overburdened'
```

### Room Inventory Pattern

```php
// Drop items in room during combat (reduce encumbrance)
$inventoryService->dropItemInRoom(
  $character_id,
  $heavy_armor_instance_id,
  $current_room_id,
  1,
  $campaign_id
);

// Pick up loot after combat
$inventoryService->pickUpItemFromRoom(
  $character_id,
  $treasure_instance_id,
  $room_id,
  1,
  $campaign_id
);
```

### Batch Transfer Pattern

```php
// Transfer all loot from defeated enemy to room
$result = $inventoryService->batchTransferItems(
  $enemy_id, 'character',
  $room_id, 'room',
  [
    ['item_instance_id' => 'gold-001', 'quantity': 50],
    ['item_instance_id' => 'sword-001', 'quantity': 1],
    ['item_instance_id' => 'potion-001', 'quantity': 3],
  ],
  $campaign_id
);
```

## Architecture Principles

### Single Source of Truth

- **Database**: `dc_campaign_item_instances` is authoritative
- **Cache**: Character state JSON synchronized automatically
- **Sync Method**: `syncCharacterStateInventory()` updates JSON from database

### Data Flow

```
Write Operation:
  API Request → InventoryManagementService
    → Update dc_campaign_item_instances (source of truth)
    → syncCharacterStateInventory()
    → Update character state JSON (cache)
    → Log to dc_campaign_log (audit)
    
Read Operation:
  API Request → InventoryManagementService
    → Query dc_campaign_item_instances
    → Return organized inventory
```

### Transaction Safety

All inventory operations wrapped in database transactions:
```php
try {
  $this->database->startTransaction();
  // ... perform operations
  $this->database->commit();
} catch (\Exception $e) {
  $this->database->rollBack();
  throw $e;
}
```

## Testing

### Key Test Scenarios

1. **Capacity Enforcement**: Verify items rejected when capacity exceeded
2. **Encumbrance States**: Test transitions between unencumbered/encumbered/overburdened
3. **Container Nesting**: Items in containers count toward character capacity
4. **Room Inventory**: Unlimited capacity, proper transfer mechanics
5. **Batch Operations**: Multiple items transferred efficiently with error reporting
6. **Synchronization**: Character state JSON matches instance table after operations

### Manual Testing

```bash
# From dungeoncrawler module root
./vendor/bin/drush scr /path/to/test_inventory.php
```

## API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/inventory/{type}/{id}` | GET | Get inventory |
| `/api/inventory/add` | POST | Add item to inventory |
| `/api/inventory/remove` | POST | Remove item from inventory |
| `/api/inventory/transfer` | POST | Transfer items between entities |
| `/api/inventory/batch-transfer` | POST | Batch transfer multiple items |
| `/api/inventory/change-location` | POST | Change item location (carried/worn/stowed) |
| `/api/inventory/capacity` | GET | Get capacity info |
| `/api/inventory/drop` | POST | Drop item in room |
| `/api/inventory/pickup` | POST | Pick up item from room |

## Related Documentation

### Module Documentation
- [../../README.md](../../README.md) - Module overview

### Global Documentation
- [../../../../docs/dungeoncrawler/](../../../../docs/dungeoncrawler/) - Dungeon Crawler system documentation
- [../../../../docs/ARCHITECTURE.md](../../../../docs/ARCHITECTURE.md) - Overall architecture

## Contributing

When modifying inventory system:

1. **Update instance table first** - dc_campaign_item_instances is source of truth
2. **Ensure synchronization** - Character state JSON must stay in sync
3. **Add audit logging** - Log operations to dc_campaign_log
4. **Validate capacity** - Check capacity before operations or handle exceptions
5. **Update documentation** - Keep docs current with code changes
6. **Write tests** - Cover new functionality with automated tests

## Version History

- **v1.0.0** (2025-02-18) - Initial comprehensive inventory system
  - Core inventory operations (add/remove/transfer/changeLocation)
  - Container management (create/lock/unlock/destroy)
  - PF2e bulk calculations
  - Room inventory support
  - Batch operations
  - Database integration with sync mechanism
  - Capacity rules for all entity types
  - Container item system (item.schema.json)

## License

Part of the Forseti.life Dungeon Crawler module.
