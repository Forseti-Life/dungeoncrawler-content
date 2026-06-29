# Inventory Management and Transfer System

**Version**: 1.0.0  
**Last Updated**: 2026-02-18  
**Status**: Design & Implementation Complete

## Overview

The Inventory Management System provides comprehensive support for managing items, equipment, and transfers between characters and containers in the Dungeon Crawler game. This system is designed to:

- **Transfer items** between characters and containers
- **Track item state** (equipped, worn, stashed, dropped)
- **Calculate bulk** and encumbrance according to PF2e rules
- **Manage capacity** limits based on character strength
- **Preserve item data** (runes, conditions, quantities)
- **Audit operations** with comprehensive logging
- **Validate permissions** to prevent unauthorized transfers

---

## Architecture

### Core Components

#### 1. **InventoryManagementService** (`src/Service/InventoryManagementService.php`)

The core service providing all inventory operations:

```php
class InventoryManagementService {
  // Get inventory for character or container
  public function getInventory(string $owner_id, string $owner_type, ?int $campaign_id): array

  // Add item to inventory
  public function addItemToInventory(
    string $owner_id,
    string $owner_type,
    array $item,
    string $location,
    int $quantity,
    ?int $campaign_id
  ): array

  // Remove item from inventory
  public function removeItemFromInventory(
    string $owner_id,
    string $owner_type,
    string $item_instance_id,
    int $quantity,
    ?int $campaign_id
  ): array

  // Transfer items between inventories
  public function transferItems(
    string $source_owner_id,
    string $source_owner_type,
    string $dest_owner_id,
    string $dest_owner_type,
    string $item_instance_id,
    int $quantity,
    ?int $campaign_id
  ): array

  // Change item location (equip/unequip/stash)
  public function changeItemLocation(
    string $owner_id,
    string $owner_type,
    string $item_instance_id,
    string $new_location,
    ?int $campaign_id
  ): array

  // Calculate current bulk
  public function calculateCurrentBulk(
    string $owner_id,
    string $owner_type,
    ?int $campaign_id
  ): float

  // Get encumbrance status
  public function getEncumbranceStatus(float $current_bulk, float $capacity): string

  // Get inventory capacity
  public function getInventoryCapacity(string $owner_id, string $owner_type): float
}
```

#### 2. **InventoryManagementController** (`src/Controller/InventoryManagementController.php`)

REST API endpoints for inventory operations.

### Database Schema

The system uses existing and expanded database tables:

#### `dc_campaign_item_instances`

Tracks item instances with location information:

```sql
CREATE TABLE dc_campaign_item_instances (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id INT UNSIGNED NOT NULL DEFAULT 0,
  item_instance_id VARCHAR(100) NOT NULL UNIQUE,
  item_id VARCHAR(100) NOT NULL,
  location_type VARCHAR(32) NOT NULL DEFAULT 'inventory',
  location_ref VARCHAR(100) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  state_data LONGTEXT NOT NULL,
  created INT NOT NULL DEFAULT 0,
  updated INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY campaign_item_instance (campaign_id, item_instance_id),
  INDEX campaign_item (campaign_id, item_id),
  INDEX location (campaign_id, location_type, location_ref)
);
```

**Fields**:
- `campaign_id`: Campaign this item belongs to
- `item_instance_id`: Unique UUID for this item instance
- `item_id`: Reference to item template
- `location_type`: Where item is stored (`inventory`, `container`, `room`, `creature`, `stash`)
- `location_ref`: Reference ID (character ID, container ID, room ID, etc.)
- `quantity`: Number of items
- `state_data`: JSON payload including bulk, runes, conditions, etc.

#### `dc_campaign_log`

Audit trail for all inventory operations:

```sql
CREATE TABLE dc_campaign_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id INT UNSIGNED NOT NULL DEFAULT 0,
  log_type VARCHAR(32) NOT NULL DEFAULT 'info',
  message TEXT NOT NULL,
  context LONGTEXT,
  created INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  INDEX campaign_log_type (campaign_id, log_type)
);
```

---

## API Endpoints

### 1. Get Inventory

```
GET /api/inventory/{owner_type}/{owner_id}
```

**Parameters**:
- `owner_type`: `character` or `container`
- `owner_id`: Character or container ID
- Query param: `campaign_id` (optional)

**Response**:
```json
{
  "success": true,
  "inventory": {
    "worn": {
      "weapons": [...],
      "armor": [...],
      "accessories": [...]
    },
    "carried": [...],
    "currency": {
      "cp": 0,
      "sp": 0,
      "gp": 100,
      "pp": 5
    },
    "totalBulk": 3.5,
    "encumbrance": "unencumbered"
  },
  "bulk": {
    "current": 3.5,
    "capacity": 10,
    "percentFilled": 35,
    "encumbrance": "unencumbered"
  }
}
```

### 2. Add Item to Inventory

```
POST /api/inventory/{owner_type}/{owner_id}/item
```

**Body**:
```json
{
  "item": {
    "id": "longsword",
    "name": "Longsword",
    "type": "weapon",
    "bulk": "1",
    "price": 10
  },
  "quantity": 1,
  "location": "carried",
  "campaignId": 42
}
```

**Response**:
```json
{
  "success": true,
  "inventory": {...},
  "item_instance_id": "item_5f7a3c2b1d4e9",
  "message": "Added 1 of 'Longsword' to character"
}
```

### 3. Remove Item from Inventory

```
DELETE /api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}
```

**Query Parameters**:
- `quantity`: Number to remove (default: 1)
- `campaign_id`: Campaign ID (optional)

**Response**:
```json
{
  "success": true,
  "inventory": {...},
  "message": "Removed 1 items"
}
```

### 4. Transfer Items Between Inventories

```
POST /api/inventory/transfer
```

**Body**:
```json
{
  "sourceOwnerId": "char_123",
  "sourceOwnerType": "character",
  "destOwnerId": "char_456",
  "destOwnerType": "character",
  "itemInstanceId": "item_5f7a3c2b1d4e9",
  "quantity": 1,
  "campaignId": 42
}
```

**Response**:
```json
{
  "success": true,
  "source_inventory": {...},
  "dest_inventory": {...},
  "message": "Transferred 1 items from character to character"
}
```

**Validation**:
- Both source and destination must exist
- Source must have item with sufficient quantity
- Destination must have capacity (bulk check)
- User must own source inventory

### 5. Change Item Location (Equip/Unequip)

```
POST /api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}/location
```

**Body**:
```json
{
  "location": "equipped",
  "campaignId": 42
}
```

**Valid Locations**:
- `carried` - In inventory, not equipped
- `equipped` - Actively equipped/being used
- `worn` - Worn on body (armor, clothing)
- `stashed` - In a container/stash
- `dropped` - On the ground

**Response**:
```json
{
  "success": true,
  "inventory": {...},
  "message": "Item location changed to equipped"
}
```

### 6. Get Capacity Information

```
GET /api/inventory/{owner_type}/{owner_id}/capacity
```

**Query Parameters**:
- `campaign_id`: Campaign ID (optional)

**Response**:
```json
{
  "success": true,
  "bulk": {
    "current": 3.5,
    "capacity": 10,
    "percentFilled": 35,
    "encumbrance": "unencumbered"
  }
}
```

---

## Bulk and Encumbrance

### PF2e Bulk System

Bulk values per PF2e Core Rulebook:

| Bulk | Examples |
|------|----------|
| Negligible | Paper, feather, coin |
| Light (L) | Dagger, potion, torch |
| 1 | Longsword, breastplate |
| 2 | Greataxe, full plate |
| 3+ | Siege equipment, large objects |

### Capacity Calculation

**Character Capacity Formula** (PF2e):
```
Capacity = 5 + (STR Modifier)
```

Examples:
- STR 8 (–1 mod): Capacity = 4
- STR 10 (0 mod): Capacity = 5
- STR 14 (+2 mod): Capacity = 7
- STR 18 (+4 mod): Capacity = 9

### Encumbrance States

| State | Condition | Effect |
|-------|-----------|--------|
| Unencumbered | Bulk ≤ 75% capacity | No penalties |
| Encumbered | 75% < bulk ≤ capacity | –10 ft. speed, –1 status to AC, Reflex, Acrobatics |
| Overburdened | Bulk > capacity | Cannot move; must drop items |

### Bulk Calculations

```php
// Items with quantity
function calculateItemBulk(array $item_state, int $quantity): float {
  $bulk_value = $item_state['bulk'] ?? 'light';
  $bulk_map = [
    'negligible' => 0,
    'light' => 0.1,
    'L' => 0.1,
    '1' => 1,
    'medium' => 1,
  ];
  $bulk = $bulk_map[$bulk_value] ?? 0;
  return ($bulk * $quantity);
}

// Currency: 1,000 coins = 1 bulk
$coin_bulk = ($total_coins / 1000);
```

---

## Permissions and Authorization

### Permission Checks

1. **Source Owner**: User must own the source character
   - Verified via `dc_campaign_characters.uid`
   - Applies to transfers and removals

2. **Destination Validation**: Destination must exist
   - Characters must exist in `dc_campaign_characters`
   - Containers must be validated

3. **Campaign Access**: Must have campaign access for campaign instances

4. **Capacity Validation**: Destination must have capacity
   - Bulk calculation prevents overfilled inventories
   - Automatic rejection if exceeds capacity

---

## Item State Preservation

### Item State Data (JSON)

```json
{
  "id": "longsword",
  "name": "Longsword",
  "type": "weapon",
  "bulk": "1",
  "runes": [
    {
      "rune_id": "striking",
      "level": 1,
      "active": true
    }
  ],
  "conditions": [
    {
      "name": "broken",
      "severity": 1
    }
  ],
  "customizations": {
    "carvings": "Runes of Power",
    "owner_name": "Grok"
  },
  "enchantments": [...],
  "property_runes": [...],
  "worn_at": "right_hand"
}
```

### Transfer Behavior

When items are transferred:
1. State data is **completely preserved**
2. Equipped/worn status is **reset** to `carried`
3. Quantity is tracked per-instance
4. New item instance ID is generated
5. Original instance is deleted or quantity decremented

---

## Operation Logging

All inventory operations are logged in `dc_campaign_log` with:

```json
{
  "operation": "transfer_items",
  "owner_id": "char_123",
  "owner_type": "character",
  "uid": 42,
  "timestamp": "2026-02-18T14:30:00Z",
  "from": "character:char_123",
  "to": "character:char_456",
  "item_instance_id": "item_5f7a3c2b1d4e9",
  "new_item_instance_id": "item_6f8a4d3c2e5f0",
  "quantity": 1
}
```

### Log Types
- `add_item`: Item added to inventory
- `remove_item`: Item removed from inventory
- `transfer_items`: Item transferred between owners
- `change_location`: Item location changed (equip/unequip)

---

## Usage Examples

### JavaScript/TypeScript

```typescript
// Get character inventory
const response = await fetch('/api/inventory/character/char_123');
const data = await response.json();
console.log(data.inventory);

// Add item
const addResponse = await fetch('/api/inventory/character/char_123/item', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    item: {
      id: 'longsword',
      name: 'Longsword',
      type: 'weapon',
      bulk: '1'
    },
    quantity: 1,
    location: 'carried',
    campaignId: 42
  })
});
const addData = await addResponse.json();

// Transfer items between characters
const transferResponse = await fetch('/api/inventory/transfer', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    sourceOwnerId: 'char_123',
    sourceOwnerType: 'character',
    destOwnerId: 'char_456',
    destOwnerType: 'character',
    itemInstanceId: 'item_5f7a3c2b1d4e9',
    quantity: 1,
    campaignId: 42
  })
});
const transferData = await transferResponse.json();

// Equip item
const equipResponse = await fetch(
  '/api/inventory/character/char_123/item/item_5f7a3c2b1d4e9/location',
  {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      location: 'equipped',
      campaignId: 42
    })
  }
);
```

### PHP (Service)

```php
$inventory_service = \Drupal::service('dungeoncrawler_content.inventory_management_service');

// Get inventory
$inventory = $inventory_service->getInventory('char_123', 'character', 42);

// Add item
$result = $inventory_service->addItemToInventory(
  'char_123',
  'character',
  [
    'id' => 'longsword',
    'name' => 'Longsword',
    'type' => 'weapon',
    'bulk' => '1'
  ],
  'carried',
  1,
  42
);

// Transfer items
$transfer = $inventory_service->transferItems(
  'char_123',
  'character',
  'char_456',
  'character',
  'item_5f7a3c2b1d4e9',
  1,
  42
);

// Calculate bulk
$bulk = $inventory_service->calculateCurrentBulk('char_123', 'character');
$capacity = $inventory_service->getInventoryCapacity('char_123', 'character');
$encumbrance = $inventory_service->getEncumbranceStatus($bulk, $capacity);
```

---

## Error Handling

### Common Errors

| Error | Code | Cause |
|-------|------|-------|
| "Character not found" | 400 | Invalid character ID |
| "Item instance not found" | 400 | Invalid item instance ID |
| "Cannot remove X items; only Y available" | 400 | Insufficient quantity |
| "Transfer would exceed destination capacity" | 400 | Destination bulk limit exceeded |
| "You do not have permission to modify this character's inventory" | 403 | User doesn't own source |
| "Invalid owner type" | 400 | Invalid owner_type value |

### Transaction Safety

All operations use database transactions:
- If any step fails, entire operation is rolled back
- Changes are atomic (all-or-nothing)
- No partial transfers or inconsistent state

---

## Future Enhancements

### Planned Features

1. **Container Management**
   - Backpack, chest, stash containers
   - Container capacity limits
   - Nested containers (bag in a bag)

2. **Advanced Item Management**
   - Item degradation/repair
   - Consumable tracking
   - Stack management

3. **Economy System**
   - Trading between characters
   - Shop inventory
   - Price calculations

4. **Advanced Restrictions**
   - Size restrictions (tiny/small items only)
   - Class/ancestry restrictions
   - Curse/attunement systems

5. **UI Features**
   - Drag-and-drop item transfer
   - Bulk calculator visualization
   - Equipment loadouts

---

## Testing

### Unit Tests

```bash
./vendor/bin/phpunit modules/custom/dungeoncrawler_content/tests/Unit/Service/InventoryManagementServiceTest.php
```

### Integration Tests

```bash
./vendor/bin/phpunit modules/custom/dungeoncrawler_content/tests/Functional/InventoryManagementTest.php
```

### Test Cases

- ✓ Add items to character inventory
- ✓ Remove items from inventory
- ✓ Transfer items between characters
- ✓ Bulk calculation
- ✓ Encumbrance status
- ✓ Permission validation
- ✓ Capacity enforcement
- ✓ Item state preservation
- ✓ Transaction rollback on error
- ✓ Audit logging

---

## Performance Considerations

### Indexes

Optimized queries use these indexes:
- `campaign_item` on (campaign_id, item_id)
- `location` on (campaign_id, location_type, location_ref)
- `campaign_item_instance` unique (campaign_id, item_instance_id)

### Query Optimization

- Uses database transactions for consistency
- Bulk operations batch-insert where possible
- Caching of character state when available

### Scaling

For large inventories (1000+ items):
- Use pagination for inventory listing
- Consider archiving old log entries
- Index optimization for location queries

---

## Related Documentation

- [Character State System](./issue-4-enhanced-character-sheet-design.md)
- [Database Schema](./INVENTORY_MANAGEMENT_SYSTEM.md#database-schema)
- [PF2e Equipment Rules](https://2e.aonprd.com/Equipment.aspx)
- [API Documentation](./API_DOCUMENTATION.md)
