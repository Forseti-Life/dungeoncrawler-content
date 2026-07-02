# Inventory Capacity Rules and Entity Management

**Date**: 2025-02-18  
**Module**: dungeoncrawler_content  
**Purpose**: Define which entities can carry inventory, capacity calculations, and integration with item/object/character management

## Overview

The inventory management system supports multiple entity types that can hold items. Each entity type has specific capacity rules based on PF2e mechanics and game design requirements.

## Entity Types That Can Carry Inventory

### 1. Characters (Players and NPCs)

**Can Carry**: ✅ Yes  
**Location Type**: `inventory`  
**Database**: `dc_campaign_characters`

#### Capacity Calculation
```
Capacity (Bulk) = 5 + STR Modifier
```

**Formula**:
- Base capacity: **5 bulk**
- STR Modifier: `floor((STR - 10) / 2)`

**Examples**:
- STR 10 → 5 + 0 = **5 bulk**
- STR 14 → 5 + 2 = **7 bulk**
- STR 18 → 5 + 4 = **9 bulk**
- STR 20 → 5 + 5 = **10 bulk**

#### Encumbrance States
Based on current bulk vs. capacity:

| State | Threshold | Effects |
|-------|-----------|---------|
| **Unencumbered** | ≤ 75% capacity | No penalties |
| **Encumbered** | 76-100% capacity | -10 ft Speed penalty, may apply check penalties |
| **Overburdened** | > 100% capacity | Cannot move, cannot take actions requiring movement |

**Code Reference**: [InventoryManagementService.php](../src/Service/InventoryManagementService.php) lines 817-862

#### Character Inventory Structure
Characters organize inventory by location type:
- **carried**: Items in hands or on belt
- **worn**: Equipped armor, clothing, accessories
- **stowed**: Items stored in backpack/containers
- **containers**: Nested containers with their own capacity

**Source of Truth**: `dc_campaign_item_instances` table with `location_type='inventory'` and `location_ref=character_id`

**Performance Cache**: Character state JSON in `dc_campaign_characters.state_data` is synchronized automatically

### 2. Containers (Items That Hold Other Items)

**Can Carry**: ✅ Yes  
**Location Type**: `container`  
**Database**: `dc_campaign_item_instances` (both container and contents)

#### Container Identification
An item is a container if it has `container_stats` defined in its schema:

```json
{
  "item_id": "backpack-001",
  "name": "Sturdy Backpack",
  "item_type": "adventuring_gear",
  "bulk": "L",
  "container_stats": {
    "capacity": 4,
    "capacity_reduction": 1,
    "access_time": "interact",
    "container_type": "backpack"
  }
}
```

#### Capacity Calculation

**Fixed Capacity**: Defined in `container_stats.capacity` (bulk units)

**Common Container Capacities**:
| Container Type | Capacity | Bulk When Empty | Example |
|----------------|----------|-----------------|---------|
| Belt Pouch | 0.4 bulk | L | Small coin purse |
| Satchel | 2 bulk | L | Shoulder bag |
| Backpack | 4 bulk | L | Standard adventuring pack |
| Chest (Small) | 8 bulk | 2 | Lockable storage |
| Barrel | 10 bulk | 4 | Large storage |
| Bag of Holding (Type I) | 25 bulk | 1 | Magical extradimensional |
| Bag of Holding (Type II) | 50 bulk | 1 | Greater magical storage |

#### Container Properties

##### Capacity Reduction
**Purpose**: Determines how much container weight counts toward carrier's burden

```
Effective Bulk = container_bulk + (contents_bulk × capacity_reduction)
```

**Examples**:
- **capacity_reduction = 1.0** (Standard containers): Full weight of contents counts
  - Backpack with 3 bulk inside = L (backpack) + 3 (contents) = 3.1 bulk total
- **capacity_reduction = 0.0** (Bag of Holding): Contents are weightless
  - Bag with 20 bulk inside = 1 (bag) + 0 (contents) = 1 bulk total
- **capacity_reduction = 0.5** (Efficient Pack): Half weight of contents
  - Pack with 4 bulk inside = L (pack) + 2 (half of contents) = 2.1 bulk total

##### Lock Mechanics
```json
{
  "can_lock": true,
  "lock_dc": 20,
  "is_locked": false
}
```

**Behavior**:
- If `can_lock = false`: Container cannot be locked
- If `can_lock = true` and `is_locked = true`: 
  - Items cannot be added or removed
  - Requires key or DC check to unlock
  - `lock_dc` defines difficulty of Thievery check

**Mutable State**: `is_locked` tracked in `dc_campaign_item_instances.state_data`

##### Access Time
**Purpose**: Actions required to retrieve items

| Value | Actions | Description |
|-------|---------|-------------|
| `free` | Free action | Quick-draw items (belt pouch) |
| `interact` | 1 action | Standard access (backpack) |
| `2_actions` | 2 actions | Digging through pack |
| `3_actions` | 3 actions | Buried deep (bottom of chest) |

##### Special Properties
- **water_resistant**: Protects contents from water damage
- **extradimensional**: Bag of holding, portable hole (affects antimagic interactions)

**Code Reference**: [ContainerManagementService.php](../src/Service/ContainerManagementService.php)

### 3. Rooms (Dungeon Environment)

**Can Carry**: ✅ Yes  
**Location Type**: `room`  
**Database**: `dc_dungeon_rooms` (room), `dc_campaign_item_instances` (items)

#### Capacity Calculation
```
Capacity = PHP_FLOAT_MAX (Unlimited)
```

**Rationale**: Rooms represent physical dungeon spaces that can hold large quantities of items (treasure piles, dropped equipment, furniture, etc.)

#### Room Inventory Use Cases

| Scenario | Mechanic |
|----------|----------|
| **Drop Items** | Character drops heavy items to reduce encumbrance during combat |
| **Loot Storage** | Encounter drops loot in room; party picks up after combat |
| **Quest Items** | Environmental items (keys, levers, books) placed in room |
| **Item Caches** | Party creates supply depot in safe room |
| **Trap Components** | Scattered items from triggered traps |

#### API Methods
```php
// Drop item in current room
$inventoryService->dropItemInRoom(
  $character_id, 
  $item_instance_id, 
  $room_id, 
  $quantity,
  $campaign_id
);

// Pick up item from room
$inventoryService->pickUpItemFromRoom(
  $character_id, 
  $item_instance_id, 
  $room_id, 
  $quantity,
  $campaign_id
);
```

**Example Game Flow**:
```
1. Party enters room with heavy armor on ground
2. System: GET /api/inventory/room/{room_id} → shows armor instance
3. Player: "Pick up the armor"
4. System: POST /api/inventory/pickup → transferItems(room, character, armor_instance)
5. Bulk check: Current 6 + armor 3 = 9, capacity 8 → "Too heavy! Drop something first"
```

**Code Reference**: [InventoryManagementService.php](../src/Service/InventoryManagementService.php) lines 540-592

### 4. Encounters (Enemy Creatures)

**Can Carry**: ✅ Yes (as NPCs/creatures)  
**Location Type**: `inventory`  
**Database**: `dc_campaign_encounter_instances`

#### Capacity Calculation
**Same as Characters**: `5 + STR Modifier`

**Behavior**:
- Creatures have ability scores including STR
- Can carry treasure, weapons, worn items
- Upon defeat, inventory becomes loot in room
- Managed through same character inventory system

**Example**:
```
Goblin Warrior (STR 12, +1 mod):
- Capacity: 5 + 1 = 6 bulk
- Equipment: 
  - Horsechopper (1 bulk)
  - Leather armor (1 bulk)  
  - Small pouch (L) containing:
    - 15 gold coins (L)
    - Rusty key (-)
- Total: 2.2 bulk / 6.0 capacity
```

**On Defeat**:
```php
// Transfer all items from defeated creature to room
foreach ($creature_inventory as $item) {
  $inventoryService->dropItemInRoom(
    $creature_id,
    $item['item_instance_id'],
    $current_room_id,
    $item['quantity'],
    $campaign_id
  );
}
```

## Entities That CANNOT Carry Inventory

### Obstacles

**Can Carry**: ❌ No  
**Schema**: `obstacle.schema.json`  
**Reason**: "Obstacles are traversal/combat blockers or modifiers; they are not containers."

**Examples**: Walls, pits, magical barriers, spinning blades

**If you need storage furniture**: Use a **container item** instead (chest, barrel, crate)

### Traps

**Can Carry**: ❌ No  
**Reason**: Traps are mechanical/magical hazards, not physical objects with inventory

**If trap has components/loot**: Place items in room after trap is triggered/disarmed

## Integration with Item Management

### Item Creation with Container Stats

When creating container items, ensure `container_stats` is properly set:

```php
// Create a backpack item instance
$backpack_data = [
  'id' => 'backpack-standard',
  'name' => 'Leather Backpack',
  'item_type' => 'adventuring_gear',
  'bulk' => 'L',
  'description' => 'A sturdy leather backpack with multiple pockets.',
  'container_stats' => [
    'capacity' => 4.0,
    'capacity_reduction' => 1.0,
    'can_lock' => false,
    'access_time' => 'interact',
    'water_resistant' => false,
    'extradimensional' => false,
    'container_type' => 'backpack',
  ],
];

$backpack_instance_id = $inventoryService->addItemToInventory(
  $character_id,
  'character',
  $backpack_data,
  'stowed',
  1,
  $campaign_id
);
```

### Validating Container Capacity

The system automatically validates capacity when:
1. Adding items to container
2. Transferring items between inventories
3. Picking up items from rooms

```php
// This will throw exception if capacity exceeded
$result = $inventoryService->transferItems(
  $character_id, 'character',
  $backpack_instance_id, 'container',
  $heavy_item_instance_id,
  1,
  $campaign_id
);
// Exception: "Transfer would exceed destination capacity (current: 3.5, capacity: 4.0, item bulk: 2.0)"
```

### Checking Container Properties

```php
// Check if item is a container
$item_data = json_decode($item_row['state_data'], TRUE);
$is_container = !empty($item_data['container_stats']);

// Get container capacity
if ($is_container) {
  $capacity = (float) $item_data['container_stats']['capacity'];
  $reduction = (float) ($item_data['container_stats']['capacity_reduction'] ?? 1.0);
  
  // Calculate effective bulk
  $container_bulk = $this->calculateItemBulk($item_data, 1);
  $contents_bulk = $this->calculateCurrentBulk($item_instance_id, 'container');
  $effective_bulk = $container_bulk + ($contents_bulk * $reduction);
}
```

## Integration with Character Management

### Character Creation

When creating a character, initialize inventory structure:

```php
$character_state = [
  'character_id' => $character_id,
  'name' => $character_name,
  'abilities' => [
    'strength' => 14,  // Used for capacity calculation
    'dexterity' => 12,
    // ... other abilities
  ],
  'inventory' => [
    'carried' => [],
    'worn' => [],
    'stowed' => [],
    'containers' => [],
  ],
  'bulk' => [
    'current' => 0.0,
    'capacity' => 7.0,  // 5 + STR mod (2)
    'encumbrance' => 'unencumbered',
  ],
];
```

**Important**: Character state JSON is synchronized automatically from `dc_campaign_item_instances`

### Ability Score Changes

When character STR changes (level up, magic item, disease, etc.):

```php
// Update character abilities
$characterStateService->updateAbilities($character_id, [
  'strength' => $new_str_value,
]);

// Capacity is recalculated automatically on next inventory operation
$inventory = $inventoryService->getInventory($character_id, 'character');
// Returns updated capacity based on new STR
```

### Encumbrance Checks

```php
$current_bulk = $inventoryService->calculateCurrentBulk($character_id, 'character');
$capacity = $inventoryService->getInventoryCapacity($character_id, 'character');
$encumbrance = $inventoryService->getEncumbranceStatus($current_bulk, $capacity);

if ($encumbrance === 'overburdened') {
  throw new \Exception("Cannot move while overburdened. Drop items to reduce bulk.");
}

if ($encumbrance === 'encumbered') {
  // Apply -10 ft speed penalty
  $speed_penalty = 10;
}
```

## Database Schema Integration

### dc_campaign_item_instances

**Primary table** for all inventory tracking:

```sql
CREATE TABLE dc_campaign_item_instances (
  item_instance_id VARCHAR(255) PRIMARY KEY,
  item_id VARCHAR(255),           -- Item type/template ID
  campaign_id INT,
  location_type VARCHAR(50),      -- 'inventory', 'container', 'room'
  location_ref VARCHAR(255),      -- Character ID, container ID, or room ID
  state_data TEXT,                -- JSON: item state including container_stats
  quantity INT DEFAULT 1,
  created INT,
  -- INDEX on (location_type, location_ref) for fast inventory queries
);
```

**Location Types**:
- `inventory` → Character or creature (`location_ref` = character_id)
- `container` → Inside a container item (`location_ref` = container_item_instance_id)
- `room` → Dropped in dungeon room (`location_ref` = room_id)

### dc_campaign_characters

**Performance cache** for character state:

```sql
CREATE TABLE dc_campaign_characters (
  character_id VARCHAR(255) PRIMARY KEY,
  campaign_id INT,
  uid INT,                        -- Player user ID
  state_data TEXT,                -- JSON: includes inventory snapshot
  -- ... other character fields
);
```

**state_data JSON** includes:
```json
{
  "inventory": {
    "carried": [...],
    "worn": [...],
    "stowed": [...],
    "containers": {...}
  },
  "bulk": {
    "current": 4.5,
    "capacity": 8.0,
    "encumbrance": "unencumbered"
  },
  "abilities": {
    "strength": 16
  }
}
```

**Synchronization**: Updated automatically via `syncCharacterStateInventory()` after every inventory modification

## API Endpoints

### Get Inventory
```
GET /api/inventory/{owner_type}/{owner_id}?campaign_id={id}
```

**Supported owner_types**: `character`, `container`, `room`

**Response**:
```json
{
  "owner_id": "char-001",
  "owner_type": "character",
  "capacity": 8.0,
  "current_bulk": 4.5,
  "encumbrance": "unencumbered",
  "inventory": {
    "carried": [...],
    "worn": [...],
    "stowed": [...],
    "containers": {...}
  }
}
```

### Add Item
```
POST /api/inventory/add
{
  "owner_id": "char-001",
  "owner_type": "character",
  "item": {...},
  "location": "carried",
  "quantity": 1,
  "campaign_id": 123
}
```

### Transfer Items
```
POST /api/inventory/transfer
{
  "source_owner_id": "char-001",
  "source_owner_type": "character",
  "dest_owner_id": "container-backpack-001",
  "dest_owner_type": "container",
  "item_instance_id": "sword-instance-001",
  "quantity": 1,
  "campaign_id": 123
}
```

### Batch Transfer
```
POST /api/inventory/batch-transfer
{
  "source_owner_id": "encounter-goblin-001",
  "source_owner_type": "character",
  "dest_owner_id": "room-treasury-001",
  "dest_owner_type": "room",
  "items": [
    {"item_instance_id": "gold-pile-001", "quantity": 50},
    {"item_instance_id": "rusty-sword-001", "quantity": 1}
  ],
  "campaign_id": 123
}
```

### Drop/Pickup in Room
```
POST /api/inventory/drop
{
  "character_id": "char-001",
  "room_id": "room-chamber-005",
  "item_instance_id": "heavy-armor-001",
  "quantity": 1,
  "campaign_id": 123
}

POST /api/inventory/pickup
{
  "character_id": "char-001",
  "room_id": "room-treasury-001",
  "item_instance_id": "quest-key-001",
  "quantity": 1,
  "campaign_id": 123
}
```

## Code Architecture

### Service Layer

**InventoryManagementService** (`src/Service/InventoryManagementService.php`):
- Core inventory operations (add/remove/transfer/changeLocation)
- Capacity calculations for all entity types
- Bulk calculations (PF2e rules)
- Encumbrance status determination
- Room inventory support (drop/pickup)
- Batch operations

**ContainerManagementService** (`src/Service/ContainerManagementService.php`):
- Container lifecycle (create/lock/unlock/destroy)
- Container capacity validation
- Lock/unlock mechanics

**CharacterStateService** (`src/Service/CharacterStateService.php`):
- Character state management
- Ability score tracking
- Inventory JSON synchronization (cache layer)

### Data Flow

```
Client Request
    ↓
Controller (InventoryManagementController)
    ↓
Service Layer (InventoryManagementService)
    ↓
Database Layer (dc_campaign_item_instances)
    ↓
Sync Layer (syncCharacterStateInventory)
    ↓
Cache Update (dc_campaign_characters.state_data)
    ↓
Audit Log (dc_campaign_log)
    ↓
Response
```

## Testing Considerations

### Test Cases

1. **Character Capacity**:
   - Create character with STR 10 (capacity 5)
   - Add items totaling 4 bulk → should succeed
   - Add item with 2 bulk → should fail (exceeds capacity)

2. **Container Capacity**:
   - Create backpack (capacity 4)
   - Add to character inventory
   - Add 3 bulk to backpack → should succeed
   - Add 2 bulk to backpack → should fail (exceeds container capacity)

3. **Room Inventory**:
   - Drop 1000 bulk of items in room → should succeed (unlimited)
   - Pick up from room with character at capacity → should fail

4. **Encumbrance**:
   - Character capacity 8, add 6 bulk → unencumbered
   - Add 1 bulk → encumbered (7/8 = 87.5%)
   - Add 2 bulk → overburdened (9/8 = 112.5%)

5. **Batch Operations**:
   - Transfer 10 items from defeated enemy to room
   - Verify all items transferred or appropriate failures reported

6. **Synchronization**:
   - Modify inventory via InventoryManagementService
   - Query CharacterStateService → should show updated inventory
   - Verify JSON matches instance table data

## Best Practices

### For Item Designers

1. **Always define container_stats** for containers:
   ```json
   "container_stats": {
     "capacity": 4.0,
     "capacity_reduction": 1.0,
     "container_type": "backpack"
   }
   ```

2. **Set appropriate bulk** for items:
   - Light items (coins, keys, scrolls): `"bulk": "L"` (0.1)
   - Negligible items (feathers, string): `"bulk": "-"` (0.0)
   - Standard items: `"bulk": "1"` or `"bulk": "2"`

3. **Use extradimensional sparingly**: Only for magical items like bags of holding

### For Developers

1. **Always use InventoryManagementService** for inventory modifications
2. **Never directly modify** `dc_campaign_characters.state_data` inventory JSON
3. **Check capacity** before operations (or rely on service exceptions)
4. **Use batch operations** when transferring multiple items
5. **Log operations** via dc_campaign_log for audit trail

### For Game Designers

1. **Balance STR requirements**: Higher STR = more carrying capacity
2. **Reward container items**: Backpacks and bags increase effective capacity
3. **Use encumbrance mechanically**: Force choices (loot vs. mobility)
4. **Room inventory for pacing**: Allow drop/pickup during dungeon crawls

## Future Enhancements

### Planned
- **Equipment slots**: Track which worn items occupy which body slots
- **Weight vs. Bulk**: Add optional weight tracking for different game systems
- **Container permissions**: Share/trade between party members with restrictions
- **Persistent room cleanup**: Auto-remove abandoned items after time period

### Under Consideration
- **Mount inventory**: Horses, pack mules with separate capacity
- **Vehicle storage**: Wagons, carts with large capacity
- **Bank/vault storage**: Long-term secure storage in towns
- **Ethereal storage**: Summoned containers, familiar pouches

## References

- **PF2e Bulk Rules**: https://2e.aonprd.com/Rules.aspx?ID=187
- **Item Schema**: `config/schemas/item.schema.json`
- **Character Schema**: `config/schemas/character.schema.json`
- **Architecture**: `docs/INVENTORY_MANAGEMENT_SYSTEM.md`
- **Implementation**: `docs/INVENTORY_REFACTORING_COMPLETE.md`
- **Code**: `src/Service/InventoryManagementService.php`

---

**Last Updated**: 2025-02-18  
**Version**: 1.0.0
