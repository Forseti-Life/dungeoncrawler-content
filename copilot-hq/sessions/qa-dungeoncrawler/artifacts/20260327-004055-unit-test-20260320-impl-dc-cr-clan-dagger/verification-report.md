# Verification Report — dc-cr-clan-dagger

- Feature: Clan Dagger (Dwarven Starting Equipment)
- Work item: 20260327-004055-unit-test-20260320-impl-dc-cr-clan-dagger
- Release: 20260326-dungeoncrawler-release-b
- Dev commits: `5bc95ffe4` (main feature), `efc7eef2a` (bug fixes)
- QA date: 2026-03-28
- QA seat: qa-dungeoncrawler

## Knowledgebase check
- No prior lessons found for ancestry-granted equipment or sell-taboo patterns — this is the prototype pattern.

## Overall verdict: BLOCK

One hard failure blocks release: **TC-012 duplicate grant prevention is not enforced**. All other core AC criteria pass.

---

## Suite: dc-cr-clan-dagger-e2e (drush ev unit tests)

Verified via `drush ev` against local dev environment (`http://localhost:8080`). No Playwright suite provisioned yet; drush-ev is authoritative for this release.

### Site audit (role-url-audit)
- Run: `20260328-022412` (local, `http://localhost:8080`)
- Results: 379 paths, **0 failures, 0 permission violations, 0 missing assets**
- ACL route `api-inventory-routes` already covers sell endpoint (`/api/inventory/*/sell`) — all roles set to `ignore` (parameterized POST-only routes, correct)

### Test results summary

| TC | Description | Result | Notes |
|---|---|---|---|
| TC-001 | Clan dagger exists in catalog with correct stats | PASS | JSON: 1d4 piercing, bulk L, traits agile/dwarf/versatile S, level 0, ancestry_granted true, sell_taboo true |
| TC-002 | Dwarf character receives Clan Dagger at creation | PASS | char 218: 1 clan-dagger in carried inventory, ancestry_granted true, sell_taboo true |
| TC-003 | Item flags (ancestry_granted, sell_taboo) set | PASS | Both flags confirmed on inventory item record |
| TC-004 | Sell Clan Dagger triggers taboo block | PASS | `success=false, sell_taboo=true`, message present, item remains in inventory |
| TC-005 | Combat: weapon functions in encounter | DEFERRED | Requires dc-cr-encounter-rules (out of this release scope per test plan note) |
| TC-006 | Agile trait: MAP −4/−8 | DEFERRED | Same dependency as TC-005 |
| TC-007 | Versatile S: slashing damage option | DEFERRED | Same dependency as TC-005 |
| TC-008 | Non-Dwarf (Elf) does NOT receive Clan Dagger | PASS | char 219: 0 items in inventory |
| TC-009 | Two Dwarfs get distinct item instances | PASS | chars 220/221: separate item_instance_ids |
| TC-010 | Drop/lose does NOT trigger sell taboo | PASS | `removeItemFromInventory` returns success, no sell_taboo key in response |
| TC-011 | Non-sell interactions (equip/stow) no taboo | PASS | `changeItemLocation` returns success, no sell_taboo key |
| TC-012 | Duplicate grant prevention (admin bypass) | **FAIL** | Second `addItemToInventory` call succeeds; char ends up with 2 clan daggers |
| TC-013 | Graceful fail if catalog unavailable at creation | PASS | try/catch in `grantAncestryStartingEquipment` logs error, does not abort character creation |
| TC-014 | Server-side grant — client cannot bypass | PASS | `createCharacter` options array has no item grant param; grant always fires server-side |
| TC-015 | Server-side sell taboo — client cannot bypass | PASS | Direct `sellItem(gm_override=false)` returns taboo block |
| TC-016 | GM override allows sale | PASS | `sellItem(gm_override=true)` returns success, item removed |

---

## BLOCK findings

### BLOCK-001: TC-012 — Duplicate grant prevention not enforced
- **Severity**: Medium (admin-only attack surface; not exploitable by normal players)
- **Reproduction**:
  ```
  $ims->addItemToInventory($char_id, "character", $clan_dagger_array, "carried", 1, 0);
  // Called twice — inventory ends up with 2 clan daggers
  ```
- **Root cause**: `InventoryManagementService::addItemToInventory()` has no deduplication logic for ancestry_granted items. The AC says "should not grant two" but no check exists.
- **Scope**: The `grantAncestryStartingEquipment()` method in CharacterManager calls `addItemToInventory` exactly once; the duplicate can only be triggered via a second direct call (e.g., admin API or bug). `createCharacter` itself calls `grantAncestryStartingEquipment` only once.
- **Risk**: Low for normal users (creation path is idempotent). High if admin API is misused or if a retry on creation error fires twice.
- **Required fix**: Add deduplication check in `grantAncestryStartingEquipment` or `addItemToInventory` for items with `ancestry_granted: true` — skip if character already has one.

---

## Deferred tests (out of scope this release)
- TC-005/006/007: Combat functionality (Agile MAP, Versatile S, weapon stats in attack roll). Depend on dc-cr-encounter-rules implementation. **Accept risk for this release** — the item is correctly defined in catalog and granted, combat system integration is a separate feature.

---

## Evidence
- Site audit: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260328-022412/`
- Findings JSON: 0 failures, 0 violations confirmed
- DB query confirmed: `sell_taboo: true` stored in `state_data` for all newly-created Dwarf clan daggers
- `php -l` syntax check: PASS (CharacterManager, InventoryManagementService, InventoryManagementController)

---

## Verdict: BLOCK

**TC-012 (duplicate grant prevention)** is a reproducible defect per AC. Fix required before APPROVE.

All other core AC items (auto-grant, Dwarf-only, sell taboo server enforcement, GM override, drop vs. sell distinction, server-side grant, distinct instances per character) are **PASS**.

Combat tests (TC-005/006/007) deferred with PM-accepted risk — encounter system not in scope for this release.

---

## PM action required
- BLOCK on TC-012: Dev must add deduplication guard in `grantAncestryStartingEquipment` or `addItemToInventory` for `ancestry_granted` items.
- After fix: QA will run targeted TC-012 retest and issue APPROVE.
- PM may accept risk on TC-012 (admin-only attack, low severity) and proceed to release — escalate to CEO for risk acceptance if chosen.
