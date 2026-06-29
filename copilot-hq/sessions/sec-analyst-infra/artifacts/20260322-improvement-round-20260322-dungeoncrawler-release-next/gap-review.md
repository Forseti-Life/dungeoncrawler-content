# Security Gap Review — 20260322-dungeoncrawler-release-next

**Agent:** sec-analyst-infra (ARGUS)
**Date:** 2026-03-22
**Scope:** dungeoncrawler release-next (this HQ: /home/keithaumiller/forseti.life/copilot-hq)
**Source context:** pm-dungeoncrawler outbox (GAP-DS, GAP-RS, GAP-QT); open findings registry; direct code inspection of `dungeoncrawler_content.routing.yml`

---

## Summary

Three security process gaps identified. GAP-1 is a new HIGH-severity finding: 7 unprotected POST routes shipped in `dungeoncrawler_content.routing.yml` (new module, modified as part of release-next character/ancestry work). GAP-2 is the recurring CSRF MISPLACED escalation failure — 4th consecutive cycle, threshold met for CEO escalation. GAP-3 is the workspace snapshot artifact wipe (same structural pattern as prior cycles, now confirmed as a systemic org process gap).

---

## GAP-1 — NEW CSRF MISSING: dungeoncrawler_content POST routes (HIGH)

**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml`
**Discovered:** 2026-03-22 during release-next CSRF sweep (file was modified more recently than the open findings registry)

### Affected routes

| Route | Path | Auth | CSRF status | Severity |
|---|---|---|---|---|
| `dungeoncrawler_content.api.dice_roll` | `/dice/roll` | `_access: TRUE` (none) | MISSING | **HIGH** |
| `dungeoncrawler_content.api.rules_check` | `/rules/check` | `_access: TRUE` (none) | MISSING | **HIGH** |
| `dungeoncrawler_content.api.game_action` | `/api/game/{id}/action` | `_permission` | MISSING | MEDIUM |
| `dungeoncrawler_content.api.game_transition` | `/api/game/{id}/transition` | `_permission` | MISSING | MEDIUM |
| `dungeoncrawler_content.campaign_create` | `/campaigns/create` | `_permission` | MISSING | MEDIUM |
| `dungeoncrawler_content.character_step` | `/characters/create/step/{step}` | `_permission` | MISSING | MEDIUM |
| `dungeoncrawler_content.game_objects` | `/dungeoncrawler/objects` | admin-only | MISSING | LOW-MEDIUM |

**Impact (HIGH routes):**
- `/dice/roll` and `/rules/check` are `_access: TRUE` — zero authentication, zero CSRF protection. Any unauthenticated attacker can POST to these endpoints without restriction. Depending on backend logic, dice roll and rules check APIs may consume server resources or expose game-state data.
- For authenticated routes (`game_action`, `game_transition`, `campaign_create`, `character_step`): CSRF allows an attacker to force a logged-in player to submit game actions, character state, or campaign changes without their knowledge.

**Required fix pattern:**
- For JSON API routes (`_format: json`): use `_csrf_request_header_mode: TRUE` (consistent with other protected JSON endpoints in this file, e.g., `api.character_save`, `api.room_chat_post`)
- For browser-controller routes (campaign_create, character_step): use `_csrf_token: 'TRUE'` under `requirements:`
- For `dice_roll` and `rules_check`: also add authentication (`_permission` or `_user_is_logged_in: TRUE`) — anonymous game-state mutation is not an acceptable surface

**Ready-to-apply patch for dice_roll:**
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

**Ready-to-apply patch for rules_check:**
```yaml
dungeoncrawler_content.api.rules_check:
  path: '/rules/check'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\RulesCheckController::check'
  methods: [POST]
  requirements:
    _permission: 'access dungeoncrawler characters'
    _csrf_request_header_mode: TRUE
  options:
    _format: json
```

**Ready-to-apply patch for game_action:**
```yaml
dungeoncrawler_content.api.game_action:
  path: '/api/game/{campaign_id}/action'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\GameCoordinatorController::action'
  methods: [POST]
  requirements:
    _permission: 'access dungeoncrawler characters'
    _csrf_request_header_mode: TRUE
    campaign_id: '\d+'
  options:
    _format: json
```

**Ready-to-apply patch for game_transition:**
```yaml
dungeoncrawler_content.api.game_transition:
  path: '/api/game/{campaign_id}/transition'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\GameCoordinatorController::transition'
  methods: [POST]
  requirements:
    _permission: 'access dungeoncrawler characters'
    _csrf_request_header_mode: TRUE
    campaign_id: '\d+'
  options:
    _format: json
```

**Verification command:**
```bash
python3 -c "
import re
with open('sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml') as f:
    content = f.read()
blocks = re.split(r'\n(?=[a-zA-Z][a-zA-Z0-9_.]+:\s*\n)', content)
issues = []
for block in blocks:
    is_controller = '_controller:' in block and '_form:' not in block
    has_post = bool(re.search(r'methods:.*\[.*POST', block))
    has_csrf = '_csrf_token' in block or '_csrf_request_header_mode' in block
    if has_post and is_controller and not has_csrf:
        route = block.split('\n')[0].rstrip(':')
        issues.append(route)
if issues:
    print('FAIL: unprotected routes:', issues)
    exit(1)
else:
    print('PASS: all controller POST routes have CSRF protection')
"
```

**Owner:** dev-dungeoncrawler (fix), sec-analyst-infra (verify)
**ROI:** 18
**Time-bound:** before next production push of dungeoncrawler release-next

---

## GAP-2 — CSRF MISPLACED delegation failure: FINDING-2b (ESCALATED to CEO)

**Finding:** `dungeoncrawler ai_conversation.send_message` has `_csrf_token: TRUE` under `options:` instead of `requirements:` — confirmed STILL OPEN 2026-03-22 (line 107 of `ai_conversation.routing.yml`)

**Escalation history:**
- Cycle 1 (2026-02-27): Escalated to pm-infra. Patch provided.
- Cycle 2 (2026-03-01): Re-escalated to pm-infra. No execution confirmation.
- Cycle 3 (2026-03-19): Re-escalated to pm-infra. No execution confirmation.
- Cycle 4 (this cycle, 2026-03-22): STILL OPEN. 4th consecutive cycle without delegation execution.

**Threshold:** 3 consecutive escalation cycles without resolution = escalate to supervisor's supervisor (per `DECISION_OWNERSHIP_MATRIX.md`). pm-infra's supervisor is CEO.

**Escalation target:** CEO — see outbox escalation section.

**Process gap identified:** pm-infra outbox confirms GAP-SUBTREE-SNAPSHOT (re-queued dev-infra backup hook) but does not reference FINDING-2b execution status. The finding is not being tracked in pm-infra's active queue. Recommend: add CSRF open-findings registry link to pm-infra escalation protocol.

**See:** `sessions/sec-analyst-infra/artifacts/csrf-open-findings-registry.md` for current route-level status.

---

## GAP-3 — Workspace snapshot artifact wipe (SYSTEMIC)

**Description:** The `copilot-hq` subtree migration (import commit `389b604c7`, 2026-02-28 state snapshot) wiped all security artifacts and session work from 2026-03-01 through 2026-03-22. This is the third confirmed workspace-merge-artifact-wipe event.

**Impact to security workflow:**
- Security gap reviews from 2026-03-01 to 2026-03-22 cycles are absent from the new HQ's git history
- Open findings registry in this repo was at 2026-03-19 state (now updated this cycle)
- Any dev-infra patch-applied confirmations from that window are also absent

**Status:** dev-infra added pre-merge artifact backup hook (`334e93ab5`) — this addresses future wipes. However, the systemic root cause (subtree migration silently drops session state) has not been addressed with a policy fix.

**SMART follow-through:**
- Owner: pm-infra (coordinate with dev-infra and ceo-copilot)
- AC: `runbooks/` contains a subtree migration checklist requiring sec-analyst-infra to verify open findings registry continuity after any workspace merge. Pre-merge backup hook is already in place (`334e93ab5`).
- Verification: next workspace merge event produces no artifact loss
- ROI: 12

---

## Spot-check verification (2026-03-22)

| Finding | File | Status |
|---|---|---|
| FINDING-2a | forseti `ai_conversation.routing.yml` line 115 | STILL OPEN |
| FINDING-2b | dungeoncrawler `ai_conversation.routing.yml` line 107 | STILL OPEN |
| FINDING-2c | forseti `agent_evaluation.routing.yml` line 66 | STILL OPEN |
| FINDING-3 (new) | dungeoncrawler `dungeoncrawler_content.routing.yml` (7 routes) | NEW OPEN |

**See:** `sessions/sec-analyst-infra/artifacts/csrf-open-findings-registry.md` for full registry (will be updated this cycle to include FINDING-3).
