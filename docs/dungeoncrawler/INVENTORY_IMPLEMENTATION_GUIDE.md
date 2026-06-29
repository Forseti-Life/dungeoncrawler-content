# Inventory Management & Transfer System - Implementation Guide

**Created**: 2026-02-18  
**Status**: Production Ready  
**Version**: 1.0.0

## What's Been Built

A comprehensive inventory management and transfer system enabling:

✅ **Item Transfers**
- Transfer items between characters
- Transfer items between characters and containers
- Transfer items between containers
- Partial item transfers (e.g., transfer 5 of 10 coins)

✅ **Inventory Management**
- Add items to character/container inventory
- Remove items with quantity support
- Change item locations (equipped, worn, carried, stashed)
- Track item state (runes, conditions, enchantments)

✅ **Bulk & Encumbrance** (PF2e Compliant)
- Calculate item bulk based on PF2e rules
- Track total inventory bulk
- Calculate character capacity from STR modifier
- Determine encumbrance status (unencumbered/encumbered/overburdened)
- Prevent transfers that would exceed capacity

✅ **Container System**
- Create containers (backpacks, chests, stashes, etc.)
- Lock/unlock containers
- Track container capacity
- Move items between containers
- Destroy containers and scatter contents

✅ **Authorization & Permissions**
- Verify user owns source character/container
- Prevent cross-campaign transfers
- Validate destinations exist
- Enforce bulk capacity limits

✅ **Audit Logging**
- Log all inventory operations
- Track transfers between owners
- Record item state changes
- Timestamp all actions

---

## Core Files

### Services

| File | Purpose |
|------|---------|
| `src/Service/InventoryManagementService.php` | Core inventory operations (add, remove, transfer, location changes) |
| `src/Service/ContainerManagementService.php` | Container lifecycle management (create, lock, destroy) |

### Controllers

| File | Purpose |
|------|---------|
| `src/Controller/InventoryManagementController.php` | REST API endpoints for inventory operations |

### Configuration

| File | Changes |
|------|---------|
| `dungeoncrawler_content.services.yml` | Added `inventory_management_service` and `container_management_service` |
| `dungeoncrawler_content.routing.yml` | Added 6 new API endpoint routes |

### Documentation

| File | Purpose |
|------|---------|
| `docs/dungeoncrawler/INVENTORY_MANAGEMENT_SYSTEM.md` | Comprehensive system documentation |

---

## Quick Start

### Basic Usage (Service)

```php
// Inject the service
$inventory_service = \Drupal::service('dungeoncrawler_content.inventory_management_service');

// Get character inventory
$inventory = $inventory_service->getInventory('char_123', 'character');

// Add item
$inventory_service->addItemToInventory(
  'char_123',
  'character',
  ['id' => 'longsword', 'name' => 'Longsword', 'bulk' => '1'],
  'carried',
  1
);

// Transfer between characters
$inventory_service->transferItems(
  'char_123', 'character',
  'char_456', 'character',
  'item_5f7a3c2b1d4e9',
  1
);

// Equip/unequip items
$inventory_service->changeItemLocation(
  'char_123', 'character',
  'item_5f7a3c2b1d4e9',
  'equipped'
);

// Check bulk
$bulk = $inventory_service->calculateCurrentBulk('char_123', 'character');
$capacity = $inventory_service->getInventoryCapacity('char_123', 'character');
```

### Basic Usage (API)

```javascript
// Get inventory
const inv = await fetch('/api/inventory/character/char_123').then(r => r.json());

// Add item
await fetch('/api/inventory/character/char_123/item', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    item: {id: 'longsword', name: 'Longsword', bulk: '1'},
    quantity: 1,
    location: 'carried'
  })
});

// Transfer items
await fetch('/api/inventory/transfer', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    sourceOwnerId: 'char_123',
    sourceOwnerType: 'character',
    destOwnerId: 'char_456',
    destOwnerType: 'character',
    itemInstanceId: 'item_5f7a3c2b1d4e9',
    quantity: 1
  })
});

// Check capacity
const cap = await fetch('/api/inventory/character/char_123/capacity').then(r => r.json());
```

---

## API Endpoints

### 1. Get Inventory
```
GET /api/inventory/{owner_type}/{owner_id}
```
Get all items in a character's or container's inventory.

### 2. Add Item
```
POST /api/inventory/{owner_type}/{owner_id}/item
```
Add item(s) to inventory with quantity support.

### 3. Remove Item
```
DELETE /api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}
```
Remove item with optional quantity.

### 4. Transfer Items
```
POST /api/inventory/transfer
```
Transfer items between any two inventories (character-to-character, character-to-container, container-to-container).

### 5. Change Item Location
```
POST /api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}/location
```
Equip, unequip, stash, or relocate items within an inventory.

### 6. Get Capacity
```
GET /api/inventory/{owner_type}/{owner_id}/capacity
```
Get bulk and capacity information.

---

## Key Features

### Bulk Calculation (PF2e)

Automatically calculates bulk for items:
- Negligible items: 0 bulk
- Light items (L): 0.1 bulk
- 1-bulk items: 1 bulk per item
- Coins: 1 bulk per 1,000 coins

Character capacity = 5 + (STR Modifier):
- STR 8 (–1): Capacity 4
- STR 10 (0): Capacity 5
- STR 14 (+2): Capacity 7

### Encumbrance Status

| Status | Condition | Effect |
|--------|-----------|--------|
| Unencumbered | ≤ 75% capacity | No penalties |
| Encumbered | > 75% and ≤ capacity | –10 ft speed, –1 status penalties |
| Overburdened | > capacity | Cannot move; must drop items |

### Transaction Safety

All operations use database transactions:
- Changes are atomic (all-or-nothing)
- Failed transfers don't leave partial state
- Automatic rollback on errors
- No orphaned items

### Audit Trail

Every operation logged with:
- Operation type (add, remove, transfer)
- Timestamps
- User ID
- Item details
- Source and destination
- Quantity transferred

---

## Container System

### Creating Containers

```php
$container_service = \Drupal::service('dungeoncrawler_content.container_management_service');

$container_service->createContainer(
  'chest_room1_001',          // Container ID
  'chest',                     // Type (backpack, chest, stash, etc.)
  'Wooden Chest',              // Display name
  [
    'capacity' => 20,          // Custom capacity
    'lock_status' => 'locked',
    'locked_dc' => 18,
    'location_ref' => 'room_001'
  ]
);
```

### Container Operations

```php
// Get container info
$container = $container_service->getContainer('chest_room1_001');

// Get contents
$contents = $container_service->getContainerContents('chest_room1_001');

// Lock/unlock
$container_service->lockContainer('chest_room1_001', 18);
$container_service->unlockContainer('chest_room1_001');

// Check if locked
$is_locked = $container_service->isLocked('chest_room1_001');

// Get capacity
$capacity_info = $container_service->getContainerCapacity('chest_room1_001');

// Destroy and scatter contents
$container_service->destroyContainer(
  'chest_room1_001',
  'room',              // Contents go to room
  'room_001'           // Room ID
);
```

---

## Validation & Authorization

### Permission Checks

1. **Source Ownership**: User must own source character
2. **Destination Validation**: Container/character must exist
3. **Bulk Enforcement**: Destination capacity checked
4. **Campaign Scoping**: Can't transfer across campaigns

### Error Handling

All errors return JSON with explanatory messages:

```json
{
  "success": false,
  "error": "Transfer would exceed destination capacity (current: 3.5, capacity: 5, item bulk: 2)"
}
```

---

## Bulk Formulas

### Item Bulk Calculation
```
total_bulk = item_bulk_value × quantity
```

### Character Capacity
```
capacity = 5 + floor((STR - 10) / 2)
```

### Encumbrance Percentage
```
percent_filled = (current_bulk / capacity) × 100
encumbrance = {
  unencumbered if percent_filled ≤ 75,
  encumbered if 75 < percent_filled ≤ 100,
  overburdened if percent_filled > 100
}
```

---

## Database Schema

### `dc_campaign_item_instances` Table

Tracks all items (including containers):

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
  INDEX campaign_item (campaign_id, item_id),
  INDEX location (campaign_id, location_type, location_ref)
);
```

### `dc_campaign_log` Table

Audit trail for inventory operations:

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

## Testing

### Unit Tests

```bash
cd /home/keithaumiller/forseti.life/sites/dungeoncrawler
./vendor/bin/phpunit web/modules/custom/dungeoncrawler_content/tests/Unit/Service/InventoryManagementServiceTest.php
```

### Test Cases Covered

✓ Add items to inventory  
✓ Remove items from inventory  
✓ Transfer items between characters  
✓ Transfer items between characters and containers  
✓ Bulk calculation  
✓ Encumbrance status computation  
✓ Capacity enforcement  
✓ Permission validation  
✓ Item state preservation  
✓ Transaction safety  
✓ Error handling  

---

## Future Enhancements

### Phase 2 Features

- [ ] Nested containers (backpack in a chest)
- [ ] Container capacity visualization UI
- [ ] Inventory weight limits vs. bulk
- [ ] Encumbrance penalty application
- [ ] Item degradation/damage tracking
- [ ] Consumable management
- [ ] Spell component pouches
- [ ] Equipment loadouts (switch all gear at once)
- [ ] Drop items on ground
- [ ] Trade system between players
- [ ] Shop inventory management
- [ ] Crafting material inventory

### Advanced Features

- [ ] Size-based restrictions (tiny/small items only)
- [ ] Curse/attunement systems
- [ ] Multi-layered permissions (DM access)
- [ ] Batch transfer summary
- [ ] Inventory search/filter
- [ ] Item categorization
- [ ] Vendor pricing integration
- [ ] Bulk import/export

---

## Troubleshooting

### Item Transfer Fails

**Problem**: "Transfer would exceed destination capacity"
- **Solution**: Reduce transfer quantity or improve destination capacity (raise STR if character)

### Permission Denied

**Problem**: "You do not have permission to modify this character's inventory"
- **Solution**: You're trying to modify another player's character. Only the owner can modify character inventory.

### Item Not Found

**Problem**: "Item instance not found"
- **Solution**: Item was already removed or wrong item_instance_id. Refresh inventory list.

### Database Errors

**Problem**: "Duplicate entry" or transaction errors
- **Solution**: Likely race condition. Retry the operation. Contact admin if persists.

---

## Performance Notes

### Optimization Done

✓ Database indexes on (campaign_id, item_id) and location tracking  
✓ Transaction batching for bulk operations  
✓ Efficient JSON serialization for item state  
✓ Character capacity cached in CharacterStateService  

### Scaling Considerations

For 1000+ items per inventory:
- Consider archiving old log entries
- Add pagination to inventory listing
- Monitor index hit rates

---

## Related Documentation

- **Full System Design**: [INVENTORY_MANAGEMENT_SYSTEM.md](./INVENTORY_MANAGEMENT_SYSTEM.md)
- **Character State**: [issue-4-enhanced-character-sheet-design.md](./INVENTORY_MANAGEMENT_SYSTEM.md)
- **API Documentation**: [API_DOCUMENTATION.md](./API_DOCUMENTATION.md)
- **PF2e Rules**: [Equipment Chapter](https://2e.aonprd.com/Equipment.aspx)

---

## Implementation Contact

For questions or issues with the inventory system:
1. Check [INVENTORY_MANAGEMENT_SYSTEM.md](./INVENTORY_MANAGEMENT_SYSTEM.md) for detailed docs
2. Review error messages and troubleshooting section
3. Check database logs in `dc_campaign_log` table
4. Contact project maintainer

---

## Summary

The Inventory Management System provides a solid, production-ready foundation for:
- ✅ Item transfers between characters and containers
- ✅ PF2e-compliant bulk calculations
- ✅ Permission-based access control
- ✅ Comprehensive audit logging
- ✅ Transaction-safe operations

The system is extensible for future features like nested containers, shop inventory, item degradation, and advanced permission models.
