# Container System Integration - Complete

**Date**: 2025-02-18  
**Status**: ✅ Complete  
**Purpose**: Integration of container inventory rules with item/object/character management processes

## Overview

Successfully integrated the inventory capacity system with the broader item/object/character management architecture. The system now has clear rules for which entities can carry inventory, how much they can carry, and how container items work.

## What Was Implemented

### 1. Item Schema Enhancement

**File**: `config/schemas/item.schema.json`

**Added**: `container_stats` property (parallel to `weapon_stats`, `armor_stats`, etc.)

```json
"container_stats": {
  "type": ["object", "null"],
  "properties": {
    "capacity": {
      "type": "number",
      "description": "Maximum bulk capacity this container can hold."
    },
    "capacity_reduction": {
      "type": "number",
      "description": "Fraction of contained bulk that counts toward carrier's burden."
    },
    "can_lock": {
      "type": "boolean",
      "description": "Whether this container can be locked."
    },
    "lock_dc": {
      "type": "integer",
      "description": "DC to pick the lock if container is locked."
    },
    "is_locked": {
      "type": "boolean",
      "description": "Runtime state: whether the container is currently locked."
    },
    "access_time": {
      "type": "string",
      "enum": ["free", "interact", "2_actions", "3_actions"],
      "description": "Number of actions required to retrieve an item."
    },
    "water_resistant": {
      "type": "boolean",
      "description": "Whether this container protects contents from water damage."
    },
    "extradimensional": {
      "type": "boolean",
      "description": "Whether this is an extradimensional container."
    },
    "container_type": {
      "type": "string",
      "enum": ["backpack", "satchel", "pouch", "chest", "barrel", "crate", "bag_of_holding", "sack", "case", "quiver"]
    }
  },
  "required": ["capacity"]
}
```

**Impact**: Any item with `container_stats` is automatically recognized as a container by the inventory system.

### 2. Service Layer Updates

**File**: `src/Service/InventoryManagementService.php`

#### Updated `getInventoryCapacity()` Method
Now checks for `container_stats.capacity` in item state:

```php
// Check for container_stats.capacity (standard location per item.schema.json)
if (!empty($state['container_stats']['capacity'])) {
  return (float) $state['container_stats']['capacity'];
}

// Fallback to legacy capacity field for backwards compatibility
if (!empty($state['capacity'])) {
  return (float) $state['capacity'];
}
```

**Backwards Compatibility**: Still supports legacy `state['capacity']` field for existing items.

#### Added Helper Methods

```php
protected function isContainer(array $item_state): bool
```
- Checks if item has `container_stats` defined
- Returns TRUE if item can hold other items

```php
protected function getContainerProperties(array $item_state): array
```
- Returns all container properties from `container_stats`
- Provides default values for optional properties
- Returns empty array if not a container

**Usage Example**:
```php
$item_data = json_decode($item_row['state_data'], TRUE);

if ($this->isContainer($item_data)) {
  $props = $this->getContainerProperties($item_data);
  $capacity = $props['capacity'];
  $reduction = $props['capacity_reduction'];
  $is_locked = $props['is_locked'];
  $container_type = $props['container_type'];
}
```

### 3. Comprehensive Documentation

**File**: `docs/INVENTORY_CAPACITY_RULES.md` (2,100+ lines)

**Sections**:
1. **Entity Types That Can Carry Inventory**
   - Characters (with STR-based capacity)
   - Containers (with item-defined capacity)
   - Rooms (unlimited capacity)
   - Encounters (as NPCs with STR-based capacity)

2. **Entity Types That CANNOT Carry Inventory**
   - Obstacles (traversal/combat blockers)
   - Traps (hazards, not physical objects)

3. **Container System Details**
   - How to identify containers
   - Capacity calculations
   - Common container types and capacities
   - Lock mechanics
   - Access time mechanics
   - Special properties (water_resistant, extradimensional)

4. **Integration Patterns**
   - Item creation with container stats
   - Validating container capacity
   - Checking container properties
   - Character creation with inventory
   - Ability score changes affecting capacity

5. **Database Schema Integration**
   - dc_campaign_item_instances structure
   - Location types (inventory, container, room)
   - State data JSON format

6. **API Endpoints**
   - Get inventory
   - Add/remove/transfer items
   - Batch operations
   - Drop/pickup in rooms

7. **Code Architecture**
   - Service layer organization
   - Data flow patterns
   - Transaction safety

8. **Testing Considerations**
   - Test scenarios for all entity types
   - Capacity validation tests
   - Encumbrance state tests

9. **Best Practices**
   - For item designers
   - For developers
   - For game designers

**File**: `docs/README.md`

Created comprehensive documentation index linking:
- All inventory system documentation
- Quick reference tables
- Entity capacity rules
- Common container capacities
- Key services and database tables
- Development patterns and code examples
- Architecture principles
- API endpoints

### 4. Example Container Items

**File**: `config/examples/container-items.json`

**Created 11 Example Containers**:

| Container | Capacity | Bulk | Price | Special Features |
|-----------|----------|------|-------|------------------|
| Belt Pouch | 0.4 | L | 4 sp | Free action access |
| Backpack | 4.0 | L | 1 gp | Standard adventuring |
| Satchel | 2.0 | L | 5 sp | Quick access shoulder bag |
| Small Locked Chest | 8.0 | 2 | 5 gp | Can lock, DC 20, water resistant |
| Wooden Barrel | 10.0 | 4 | 2 gp | Large storage, water resistant |
| Bag of Holding (I) | 25.0 | 1 | 75 gp | **Extradimensional, 0% capacity reduction** |
| Bag of Holding (II) | 50.0 | 1 | 300 gp | **Greater extradimensional** |
| Arrow Quiver | 1.0 | L | 5 sp | Free access for ranged combat |
| Burlap Sack | 3.0 | L | 5 cp | Cheap but not water resistant |
| Scroll Case | 0.5 | L | 5 sp | Water resistant for documents |
| Ornate Treasure Chest | 12.0 | 3 | 20 gp | Locked DC 25, elaborate |

**Key Features Demonstrated**:
- **capacity_reduction = 0.0** for bags of holding (contents weightless)
- **capacity_reduction = 1.0** for standard containers (full weight)
- **can_lock / lock_dc** for secured containers
- **access_time** variations (free for quick-draw, interact for standard, 2_actions for digging)
- **water_resistant** for protective containers
- **extradimensional** for magical storage items

## Entity Capacity Rules Summary

### Characters and NPCs

**Formula**: `5 + STR Modifier`

**Examples**:
- STR 10 → Capacity 5 bulk
- STR 14 → Capacity 7 bulk
- STR 18 → Capacity 9 bulk

**Encumbrance**:
- ≤ 75% capacity: Unencumbered (no penalties)
- 76-100% capacity: Encumbered (-10 ft speed)
- > 100% capacity: Overburdened (cannot move)

**Source**: `dc_campaign_characters.state_data` with `abilities.strength`

### Containers

**Formula**: Defined in `container_stats.capacity`

**Identification**: Item has `container_stats` property

**Effective Weight Calculation**:
```
Effective Bulk = container_bulk + (contents_bulk × capacity_reduction)
```

**Examples**:
- Backpack (reduction 1.0): 4 bulk inside = L + 4 = 4.1 bulk total
- Bag of Holding (reduction 0.0): 20 bulk inside = 1 + 0 = 1 bulk total

**Source**: `dc_campaign_item_instances.state_data` with `container_stats`

### Rooms

**Formula**: `PHP_FLOAT_MAX` (unlimited)

**Use Cases**:
- Drop items during combat to reduce encumbrance
- Loot from encounters placed in room
- Quest items in environment
- Party supply caches
- Trap component storage

**Source**: `dc_dungeon_rooms` (room definition) + `dc_campaign_item_instances` (items with `location_type='room'`)

### Encounters (Enemies/NPCs)

**Formula**: Same as characters: `5 + STR Modifier`

**Behavior**:
- Creatures have ability scores
- Can carry weapons, armor, treasure
- Upon defeat, inventory becomes loot in room

**Transfer Pattern**:
```php
// Transfer all items from defeated creature to room
$inventoryService->batchTransferItems(
  $creature_id, 'character',
  $room_id, 'room',
  $creature_inventory_items,
  $campaign_id
);
```

## Integration Points

### Item Management

**When Creating Items**:
1. Define `container_stats` if item should hold other items
2. Set appropriate `capacity` for container type
3. Set `capacity_reduction` (0.0 for magical, 1.0 for standard)
4. Define lock properties if applicable
5. Set `access_time` based on retrieval speed

**Item Type Integration**:
- Most containers use `item_type: "adventuring_gear"`
- Magical containers use `item_type: "held_item"` with `magic_properties`
- Containers can have traits like `["locked"]`, `["magical"]`, `["extradimensional"]`

### Character Management

**Character Creation**:
1. Initialize character with ability scores (including STR)
2. Create inventory structure in character state JSON
3. Calculate initial capacity: `5 + STR modifier`
4. Set encumbrance: `"unencumbered"`

**Ability Score Changes**:
- STR increases → capacity increases automatically on next inventory query
- STR decreases → may trigger encumbrance state change
- System recalculates capacity on-the-fly from current abilities

**Character State Sync**:
- `dc_campaign_item_instances` is source of truth
- Character state JSON synchronized via `syncCharacterStateInventory()`
- Sync happens automatically after all inventory operations

### Object Management

**Obstacles vs. Containers**:
- **Obstacles**: Cannot carry inventory (walls, pits, barriers)
- **Containers**: Use container items instead (chests, barrels, crates)

**Environmental Objects**:
- If object needs to hold items → create as container item instance
- If object is traversal/combat mechanic → create as obstacle (no inventory)

**Example**:
```
❌ Don't: Create obstacle with inventory
✅ Do: Create chest (container item) placed in room
```

## Code Usage Examples

### Creating a Container Item

```php
// Define backpack as item
$backpack_data = [
  'id' => 'backpack-leather-001',
  'name' => 'Leather Backpack',
  'item_type' => 'adventuring_gear',
  'bulk' => 'L',
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

// Add to character inventory
$backpack_instance_id = $inventoryService->addItemToInventory(
  $character_id,
  'character',
  $backpack_data,
  'stowed',  // Backpack itself is stored
  1,
  $campaign_id
);

// Now add items to the backpack
$inventoryService->transferItems(
  $character_id, 'character',
  $backpack_instance_id, 'container',  // Transfer to container
  $rope_instance_id,
  1,
  $campaign_id
);
```

### Checking if Item is Container

```php
// Get item from database
$item_row = $database->select('dc_campaign_item_instances', 'i')
  ->fields('i', ['state_data'])
  ->condition('item_instance_id', $item_instance_id)
  ->execute()
  ->fetchAssoc();

$item_data = json_decode($item_row['state_data'], TRUE);

// Check if it's a container
if ($inventoryService->isContainer($item_data)) {
  $props = $inventoryService->getContainerProperties($item_data);
  
  echo "Container: {$props['container_type']}\n";
  echo "Capacity: {$props['capacity']} bulk\n";
  echo "Locked: " . ($props['is_locked'] ? 'Yes' : 'No') . "\n";
}
```

### Capacity-Based Game Logic

```php
// Check if character can pick up item
$current_bulk = $inventoryService->calculateCurrentBulk($character_id, 'character');
$capacity = $inventoryService->getInventoryCapacity($character_id, 'character');
$item_bulk = $inventoryService->calculateItemBulk($item_data, $quantity);

if ($current_bulk + $item_bulk > $capacity) {
  throw new \Exception(
    "Too heavy! You need {$item_bulk} bulk but only have " . 
    ($capacity - $current_bulk) . " bulk available."
  );
}

// Check encumbrance after pickup
$new_bulk = $current_bulk + $item_bulk;
$encumbrance = $inventoryService->getEncumbranceStatus($new_bulk, $capacity);

if ($encumbrance === 'encumbered') {
  $message = "You feel the weight of your gear. Speed reduced by 10 ft.";
}
elseif ($encumbrance === 'overburdened') {
  $message = "You can barely move! Drop something or you can't proceed.";
}
```

## Testing Integration

### Unit Tests

```php
// Test: Item with container_stats is recognized
$item_data = ['container_stats' => ['capacity' => 4.0]];
$this->assertTrue($inventoryService->isContainer($item_data));

// Test: Capacity read from container_stats
$capacity = $inventoryService->getInventoryCapacity($container_instance_id, 'container');
$this->assertEquals(4.0, $capacity);

// Test: Character capacity based on STR
$character_capacity = $inventoryService->getInventoryCapacity($character_id, 'character');
$this->assertEquals(7.0, $character_capacity); // STR 14 = 5 + 2
```

### Integration Tests

```php
// Test: Add container to character, then add items to container
$backpack_id = $inventoryService->addItemToInventory(...);
$result = $inventoryService->transferItems(..., $backpack_id, 'container', ...);
$this->assertTrue($result['success']);

// Test: Capacity enforcement on containers
$this->expectException(\InvalidArgumentException::class);
$inventoryService->addItemToInventory($backpack_id, 'container', $heavy_item, 'carried', 10);
// Should fail: exceeds backpack capacity
```

## Migration Considerations

### Existing Items

**Option 1: Update Existing Items**
Run migration to add `container_stats` to items that should be containers:

```php
// Find items that have legacy 'capacity' field
$items = $database->select('dc_campaign_item_instances', 'i')
  ->fields('i', ['item_instance_id', 'state_data'])
  ->execute()
  ->fetchAll();

foreach ($items as $item) {
  $state = json_decode($item->state_data, TRUE);
  
  // If has legacy capacity field
  if (!empty($state['capacity']) && empty($state['container_stats'])) {
    // Migrate to container_stats
    $state['container_stats'] = [
      'capacity' => (float) $state['capacity'],
      'capacity_reduction' => 1.0,
      'can_lock' => false,
      'access_time' => 'interact',
      'water_resistant' => false,
      'extradimensional' => false,
      'container_type' => 'backpack',
    ];
    
    // Remove legacy field
    unset($state['capacity']);
    
    // Update database
    $database->update('dc_campaign_item_instances')
      ->fields(['state_data' => json_encode($state)])
      ->condition('item_instance_id', $item->item_instance_id)
      ->execute();
  }
}
```

**Option 2: Backwards Compatibility**
The system already supports both:
- New: `state_data['container_stats']['capacity']`
- Legacy: `state_data['capacity']`

No migration required if you're okay with mixed formats.

### Template/Registry Updates

Update item templates in content registry to include `container_stats`:

```sql
UPDATE dungeoncrawler_content_registry
SET schema_data = JSON_SET(
  schema_data,
  '$.container_stats',
  JSON_OBJECT(
    'capacity', 4.0,
    'capacity_reduction', 1.0,
    'container_type', 'backpack'
  )
)
WHERE content_type = 'item'
  AND content_id IN ('backpack-001', 'satchel-001', 'chest-001');
```

## Benefits

### For Item Designers
- ✅ Clear schema for defining containers
- ✅ Rich properties (locks, access time, water resistance)
- ✅ Examples to follow (11 container types)
- ✅ Validation via JSON schema

### For Developers
- ✅ Simple API: `isContainer()`, `getContainerProperties()`
- ✅ Backwards compatibility maintained
- ✅ Consistent with existing item system patterns
- ✅ Well-documented integration points

### For Game Designers
- ✅ Flexible container types for different gameplay
- ✅ Capacity reduction mechanic (magical vs. mundane)
- ✅ Lock mechanics for puzzles/progression
- ✅ Access time for action economy balance

### For Players
- ✅ Clear capacity limits and feedback
- ✅ Strategic choices (what to carry vs. stow)
- ✅ Container upgrades meaningful (bag of holding!)
- ✅ Consistent rules across all entity types

## Next Steps

### Recommended
1. **Import Example Containers**: Load `container-items.json` into content registry
2. **Test Integration**: Create character, add backpack, transfer items
3. **Update Existing Templates**: Add `container_stats` to item templates
4. **UI Updates**: Show container capacity in inventory UI
5. **Lock Mechanics**: Implement unlock action with DC checks

### Future Enhancements
- **Nested Container Visualization**: Show container contents in tree view
- **Auto-stow**: "Put all similar items in backpack" bulk action
- **Container Recommendations**: "This exceeds capacity, try a bag of holding"
- **Smart Packing**: Optimize which container to use based on weight reduction

## Documentation Reference

- **[INVENTORY_CAPACITY_RULES.md](INVENTORY_CAPACITY_RULES.md)** - Comprehensive capacity rules (2,100+ lines)
- **[README.md](README.md)** - Documentation index
- **[item.schema.json](../config/schemas/item.schema.json)** - Item schema with container_stats
- **[container-items.json](../config/examples/container-items.json)** - 11 example containers
- **[InventoryManagementService.php](../src/Service/InventoryManagementService.php)** - Service implementation

---

**Status**: ✅ Complete  
**Version**: 1.0.0  
**Last Updated**: 2025-02-18
