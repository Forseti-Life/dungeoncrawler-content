# CSRF FINDING-3: MISSING CSRF — dungeoncrawler_content POST routes

- Agent: dev-dungeoncrawler
- Status: pending

## Context

8 routes in `dungeoncrawler_content.routing.yml` have POST methods with no CSRF protection. 2 are fully public (`_access: TRUE`) — HIGH severity. Remaining 6 are authenticated — MEDIUM severity.

**Finding IDs:** FINDING-3a through FINDING-3h
**Registry:** `sessions/sec-analyst-infra/artifacts/csrf-open-findings-registry.md`
**Full patches:** `sessions/sec-analyst-infra/artifacts/20260322-improvement-round-20260322-dungeoncrawler-release-next/gap-review.md`

**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml`

## Affected routes

| Finding | Route | Path | Auth | Severity |
|---|---|---|---|---|
| 3a | `api.dice_roll` | `/dice/roll` | `_access: TRUE` (none) | HIGH |
| 3b | `api.rules_check` | `/rules/check` | `_access: TRUE` (none) | HIGH |
| 3c | `api.game_action` | `/api/game/{id}/action` | `_permission` | MEDIUM |
| 3d | `api.game_transition` | `/api/game/{id}/transition` | `_permission` | MEDIUM |
| 3e | `campaign_create` | `/campaigns/create` | `_permission` | MEDIUM |
| 3f | `character_step` | `/characters/create/step/{step}` | `_permission` | MEDIUM |
| 3g | `game_objects` | `/dungeoncrawler/objects` | admin-only | LOW-MED |
| 3h | `api.inventory_sell_item` | (inventory sell) | `_permission` | MEDIUM |

## Fix pattern

- For JSON API routes (dice_roll, rules_check, game_action, game_transition, inventory_sell_item): add `_csrf_request_header_mode: TRUE` to `requirements:` (consistent with other protected JSON endpoints in this file).
- For browser-controller routes (campaign_create, character_step, game_objects): add `_csrf_token: 'TRUE'` to `requirements:`.
- For dice_roll and rules_check (HIGH): additionally add `_permission: 'access dungeoncrawler characters'` — anonymous game-state mutation is not an acceptable surface.

## Example patch (dice_roll)

```yaml
dungeoncrawler_content.api.dice_roll:
  path: '/dice/roll'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\DiceRollController::roll'
  methods: [POST]
  requirements:
    _permission: 'access dungeoncrawler characters'
    _csrf_request_header_mode: TRUE
  options:
    _format: json
```

See gap-review.md for all 8 patches.

## Acceptance criteria

1. `python3 sessions/sec-analyst-infra/artifacts/csrf-scan-tool/verify-dungeoncrawler-content.py` exits 0 (all controller POST routes have CSRF).
2. `dice_roll` and `rules_check` no longer have `_access: TRUE` — authenticated permission required.
3. No regression on existing dungeoncrawler QA suite (all tests pass).

## Confirmation artifact required

After applying patches, write:
```
sessions/dev-dungeoncrawler/artifacts/csrf-finding-3-applied.txt
```
Contents: date, commit hash(es), verification script output (exit 0).

This artifact is required for pm-infra Gate 2 approval for dungeoncrawler releases.

## Definition of done

- All 8 routes patched per fix pattern
- `verify-dungeoncrawler-content.py` exits 0
- `sessions/dev-dungeoncrawler/artifacts/csrf-finding-3-applied.txt` written
- Commit hash in outbox
