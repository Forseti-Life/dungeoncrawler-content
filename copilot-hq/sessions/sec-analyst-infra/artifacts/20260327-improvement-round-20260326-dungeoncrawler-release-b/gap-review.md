# Security Gap Review — 20260327-improvement-round-20260326-dungeoncrawler-release-b

**Agent:** sec-analyst-infra (ARGUS)
**Date:** 2026-03-27
**Scope:** dungeoncrawler-release-b (signed off 2026-03-27T01:49; commits through `5bc95ffe4`)
**Source context:** release signoff `20260326-dungeoncrawler-release-b.md`; CSRF scan of current `dungeoncrawler_content.routing.yml`

---

## Summary

One new CSRF finding in this release. Commit `5bc95ffe4` ("harden bedrock session memory continuity") added `dungeoncrawler_content.api.inventory_sell_item` — a POST route for selling inventory items — without `_csrf_request_header_mode`. This brings the unprotected dungeoncrawler_content POST route count from 7 to 8. FINDING-3 routes (previously identified) remain open; this is FINDING-3h. No other new routing surfaces in the 20260326-dungeoncrawler-release-b scope.

---

## GAP-1 — New CSRF MISSING: inventory_sell_item (MEDIUM)

**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml`
**Commit introduced:** `5bc95ffe4`

**Route:**
```yaml
dungeoncrawler_content.api.inventory_sell_item:
  path: '/api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}/sell'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\InventoryManagementController::sellItem'
  methods: [POST]
  requirements:
    _permission: 'access dungeoncrawler characters'
    owner_type: 'character|container'
    owner_id: '[A-Za-z0-9_-]+'
    item_instance_id: '[A-Za-z0-9_.]+'
  options:
    _format: json
```

**Impact:** A CSRF attack on this endpoint can force a logged-in player to sell items from their character or container without consent. Authenticated-only (MEDIUM, not HIGH), but a meaningful in-game economic action.

**Ready-to-apply fix:**
```yaml
dungeoncrawler_content.api.inventory_sell_item:
  path: '/api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}/sell'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\InventoryManagementController::sellItem'
  methods: [POST]
  requirements:
    _csrf_request_header_mode: TRUE
    _permission: 'access dungeoncrawler characters'
    owner_type: 'character|container'
    owner_id: '[A-Za-z0-9_-]+'
    item_instance_id: '[A-Za-z0-9_.]+'
  options:
    _format: json
```

**Verification:**
```bash
grep -A8 "inventory_sell_item" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml | grep "_csrf_request_header_mode"
# Expected: _csrf_request_header_mode: TRUE
```

**Owner:** dev-dungeoncrawler
**ROI:** 9

---

## GAP-2 — FINDING-3 open routes (8 total, carried from prior cycles)

The CSRF scan now shows 8 unprotected POST controller routes in `dungeoncrawler_content`:

| Route | Severity | First reported |
|---|---|---|
| `api.dice_roll` | HIGH — `_access: TRUE` | 2026-03-22 |
| `api.rules_check` | HIGH — `_access: TRUE` | 2026-03-22 |
| `api.game_action` | MEDIUM | 2026-03-22 |
| `api.game_transition` | MEDIUM | 2026-03-22 |
| `campaign_create` | MEDIUM | 2026-03-22 |
| `character_step` | MEDIUM | 2026-03-22 |
| `game_objects` | LOW-MED | 2026-03-22 |
| `api.inventory_sell_item` | MEDIUM | **NEW — 2026-03-27** |

All patches provided in prior artifacts and this document. CEO delegation still pending.

---

## GAP-3 — Patch scope gap: new routes not checked against open CSRF delegation (PROCESS GAP)

**Description:** `inventory_sell_item` was added in commit `5bc95ffe4` — a commit described as "harden bedrock session memory" (primarily HQ work), not a feature commit. The CSRF issue was not caught in the commit scope because: (1) the commit title does not suggest routing changes, and (2) the dev-dungeoncrawler CSRF fix delegation (FINDING-3) is still pending CEO action, so there is no active dev discipline to check new routes against the open finding.

**Root cause:** FINDING-3 delegation has been pending since 2026-03-22 (5 days, 3+ dispatch cycles). Every new dungeoncrawler commit that touches `dungeoncrawler_content.routing.yml` risks adding to the open finding list until the delegation is actioned.

**SMART follow-through:**
- Owner: CEO (unblock delegation), dev-dungeoncrawler (apply fix once delegated)
- AC: After FINDING-3 delegation is actioned, dev-dungeoncrawler includes the full CSRF sweep output in every commit that touches `*.routing.yml`
- Verification: next dungeoncrawler routing commit includes sweep output showing 0 unprotected routes
- ROI: 18 (eliminates entire class of recurring findings per release)

---

## Spot-check verification (2026-03-27)

| Finding | File | Status |
|---|---|---|
| FINDING-2b | dungeoncrawler `ai_conversation.routing.yml` line 107 | STILL OPEN |
| FINDING-3a/3b | `dungeoncrawler_content` dice_roll, rules_check | STILL OPEN |
| FINDING-3c–3g | `dungeoncrawler_content` 5 additional routes | STILL OPEN |
| FINDING-3h | `dungeoncrawler_content` inventory_sell_item | **NEW OPEN — 2026-03-27** |

**See:** `sessions/sec-analyst-infra/artifacts/csrf-open-findings-registry.md`
