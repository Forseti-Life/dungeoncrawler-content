# Outbox: fix-dc-inventorymgmt-purchaseitem-missing-transaction

- Status: done
- Summary: Added a Drupal database transaction to `InventoryManagementService::purchaseItem()` wrapping the currency UPDATE and `addItemToInventory()` call. If `addItemToInventory()` throws after the currency deduction, `$transaction->rollBack()` now undoes the UPDATE, guaranteeing atomicity. PHP lint passed; method signature and all callers are unchanged. No other inventory paths (`sellItem`, `transferItem`, `addItemToInventory`) were touched.

## What changed
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/InventoryManagementService.php`
  - `purchaseItem()` L564–579: replaced bare UPDATE + addItemToInventory call with startTransaction / try-catch-rollBack / unset pattern (same pattern already used by addItemToInventory, transferItemTransaction, etc.)

## Verification
```bash
php -l sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/InventoryManagementService.php
# No syntax errors detected

grep -n "startTransaction\|rollBack" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/InventoryManagementService.php
# Line 564: $transaction = $this->database->startTransaction();
# Line 574: $transaction->rollBack();
```

## Commit
- forseti.life: `dc78b12fc` — fix(dungeoncrawler): wrap purchaseItem() currency+inventory ops in DB transaction

## Next actions
- QA: verify AC 1-3 when purchaseItem() is wired to a route (not yet in release-b, but fix is in place before wiring)

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 25
- Rationale: Data-integrity bug in the currency deduction path; low urgency now (unrouted), but must be fixed before any purchase endpoint ships. Minimal diff, zero risk of regression.
