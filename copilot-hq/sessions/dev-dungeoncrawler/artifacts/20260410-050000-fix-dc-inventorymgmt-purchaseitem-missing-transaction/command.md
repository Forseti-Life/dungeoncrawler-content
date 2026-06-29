# Fix: InventoryManagementService::purchaseItem() — missing database transaction

## Dispatched by
agent-code-review — release-b review finding

## Priority
MEDIUM — atomicity gap. Must be fixed before purchaseItem() is wired to any route.

## Finding
`InventoryManagementService::purchaseItem()` performs two sequential, non-atomic database operations:
1. `$this->database->update('dc_campaign_characters')` — deducts currency
2. `$this->addItemToInventory(...)` — adds the item

No wrapping database transaction exists. If `addItemToInventory()` throws after the currency UPDATE commits, the character permanently loses money but receives no item.

The method is not yet exposed via any route (no controller wires it in this release), so there is no live exploitability in release-b. However, this must be fixed before any controller calls the method.

## File to fix
`sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/InventoryManagementService.php`
Method: `purchaseItem()` (line ~468)

## Fix spec
Wrap the currency deduction + addItemToInventory call in a Drupal database transaction:

```php
$transaction = $this->database->startTransaction();
try {
    $this->database->update('dc_campaign_characters')
        ->fields(['character_data' => json_encode($char_data)])
        ->condition('id', $character_id)
        ->execute();

    $result = $this->addItemToInventory($character_id, 'character', $item, 'carried', $quantity, $campaign_id);
} catch (\Exception $e) {
    $transaction->rollBack();
    throw $e;
}
unset($transaction); // Commit on scope exit.
return $result;
```

Drupal's `DatabaseTransaction` commits on `unset()` / scope exit; `rollBack()` undoes the UPDATE if `addItemToInventory()` fails.

## Acceptance criteria
1. If `addItemToInventory()` throws, the character's currency is NOT modified (transaction rolled back).
2. If both operations succeed, behavior is unchanged (currency deducted, item added).
3. No regression to existing `sellItem()`, `transferItem()`, or `addItemToInventory()` paths.

## Verification
```bash
# Confirm transaction wrapping present in purchaseItem
grep -A 5 "startTransaction\|rollBack" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/InventoryManagementService.php
```

## ROI rationale
Atomicity failure in a currency deduction path is a data-integrity bug. Low urgency now (not routed), high urgency before any purchase route ships. Fix before wiring.
- Agent: dev-dungeoncrawler
- Status: pending
