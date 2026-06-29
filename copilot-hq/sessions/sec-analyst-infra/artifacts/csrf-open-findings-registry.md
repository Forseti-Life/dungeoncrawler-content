# Open CSRF Findings Registry

**Maintained by:** sec-analyst-infra (ARGUS)
**Last updated:** 2026-04-05
**Spot-check 2026-04-05:** Path migration fix applied — all `/home/keithaumiller/` references updated to `/home/ubuntu/` (4 occurrences). No new route changes detected in last 5 HQ commits. All 15 findings remain OPEN pending dev-infra patch execution. No `patch-applied.txt` artifact found in `sessions/dev-infra/artifacts/` — GAP-FINDING-LIFECYCLE confirmed (see improvement-round outbox 20260405).
**Spot-check 2026-03-26:** FINDING-2a/2c confirmed STILL OPEN (forseti ai_conversation line 115, agent_evaluation line 66). FINDING-4 (NEW): 7 job_hunter routes missing CSRF — application submission steps 3/4/5 (browser-form POST) and addposting (GET/POST combo requiring dev judgment for fix pattern). MEDIUM severity — all require authentication.
**Spot-check 2026-03-22:** FINDING-2a/2b/2c confirmed STILL OPEN by direct code inspection (forseti ai_conversation line 115, dungeoncrawler ai_conversation line 107, forseti agent_evaluation line 66). FINDING-3 (NEW): 7 dungeoncrawler_content POST routes missing CSRF protection, including 2 fully public routes (`dice_roll`, `rules_check` with `_access: TRUE`). See gap-review artifact for patches.
**Spot-check 2026-03-19:** FINDING-1 CLOSED (confirmed by code inspection). FINDING-2 (ai_conversation + agent_evaluation MISPLACED) STILL OPEN across forseti and dungeoncrawler — patches were provided but MISPLACED-type was not in scope of the patch-mode execution (which targeted MISSING, not MISPLACED).
**Source of truth:** run `bash sessions/sec-analyst-infra/artifacts/csrf-scan-tool/csrf-route-scan.sh <repo_root>` to regenerate current status.

---

## Summary

| Finding ID | Site | Module | Route | Type | Status |
|---|---|---|---|---|---|
| FINDING-1a | forseti | `job_hunter` | credentials_delete | MISSING | **CLOSED** 2026-03-01 |
| FINDING-1b | forseti | `job_hunter` | credentials_test | MISSING | **CLOSED** 2026-03-01 |
| FINDING-2a | forseti | `ai_conversation` | ai_conversation.send_message | MISPLACED (options:) | **OPEN** — patches written, 4th escalation cycle |
| FINDING-2b | dungeoncrawler | `ai_conversation` | ai_conversation.send_message | MISPLACED (options:) | **OPEN** — patches written, 4th escalation cycle |
| FINDING-2c | forseti | `agent_evaluation` | agent_evaluation.send_message | MISPLACED (options:) | **OPEN** — patches written, 4th escalation cycle |
| FINDING-3a | dungeoncrawler | `dungeoncrawler_content` | api.dice_roll | MISSING + no auth | **OPEN** — HIGH — 2026-03-22 |
| FINDING-3b | dungeoncrawler | `dungeoncrawler_content` | api.rules_check | MISSING + no auth | **OPEN** — HIGH — 2026-03-22 |
| FINDING-3c | dungeoncrawler | `dungeoncrawler_content` | api.game_action | MISSING | **OPEN** — MEDIUM — 2026-03-22 |
| FINDING-3d | dungeoncrawler | `dungeoncrawler_content` | api.game_transition | MISSING | **OPEN** — MEDIUM — 2026-03-22 |
| FINDING-3e | dungeoncrawler | `dungeoncrawler_content` | campaign_create | MISSING | **OPEN** — MEDIUM — 2026-03-22 |
| FINDING-3f | dungeoncrawler | `dungeoncrawler_content` | character_step | MISSING | **OPEN** — MEDIUM — 2026-03-22 |
| FINDING-3g | dungeoncrawler | `dungeoncrawler_content` | game_objects | MISSING | **OPEN** — LOW-MED — 2026-03-22 |
| FINDING-3h | dungeoncrawler | `dungeoncrawler_content` | api.inventory_sell_item | MISSING | **OPEN** — MEDIUM — 2026-03-27 |
| FINDING-4a | forseti | `job_hunter` | application_submission_step3/3_short | MISSING | **OPEN** — MEDIUM — 2026-03-26 |
| FINDING-4b | forseti | `job_hunter` | application_submission_step4/4_short | MISSING | **OPEN** — MEDIUM — 2026-03-26 |
| FINDING-4c | forseti | `job_hunter` | application_submission_step5/5_short | MISSING | **OPEN** — MEDIUM — 2026-03-26 |
| FINDING-4d | forseti | `job_hunter` | addposting (GET/POST combo) | MISSING — special case | **OPEN** — MEDIUM — requires dev judgment on fix pattern — 2026-03-26 |

---

## FINDING-1 (CLOSED)

**Description:** `job_hunter.credentials_delete` and `job_hunter.credentials_test` POST routes were missing `_csrf_token: 'TRUE'` in `requirements:`.

**Fix:** Applied via CSRF patch-mode execution (dev-infra commits `74a4a6633` forseti + `603223bb4` dungeoncrawler, QA PASS `0e415d34`).

**Verified:** 2026-03-19 — `grep -n -A10 "credentials_delete" job_hunter.routing.yml` confirms `_csrf_token: 'TRUE'` at line 1107 under `requirements:`.

---

## FINDING-2 (OPEN — escalated to pm-infra)

**Description:** `ai_conversation.send_message` and `agent_evaluation.send_message` have `_csrf_token: TRUE` under `options:` instead of `requirements:`. Drupal's access checker does not read `options:` — this is a silent no-op.

**Impact:** LLM chat endpoints and agent evaluation endpoint are unprotected against CSRF. Attacker can force logged-in user to submit arbitrary AI prompts (conversation injection, API credit consumption).

**Severity:** Medium (original classification). Elevated concern: LLM endpoint abuse (API credit drain) adds financial dimension beyond typical CSRF.

### FINDING-2a — forseti ai_conversation.send_message

**File:** `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/ai_conversation.routing.yml`
**Line:** 107 (route start), 115 (`_csrf_token` under `options:`)

**Ready-to-apply patch:**
```yaml
ai_conversation.send_message:
  path: '/ai-conversation/send-message'
  defaults:
    _controller: '\Drupal\ai_conversation\Controller\ChatController::sendMessage'
  methods: [POST]
  requirements:
    _permission: 'use ai conversation'
    _csrf_token: 'TRUE'
```
Remove `_method: 'POST'` from `requirements:` and remove `options:` block entirely.

### FINDING-2b — dungeoncrawler ai_conversation.send_message

**File:** `/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/ai_conversation/ai_conversation.routing.yml`
**Line:** 99 (route start), 107 (`_csrf_token` under `options:`)

Same patch as FINDING-2a.

### FINDING-2c — forseti agent_evaluation.send_message

**File:** `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/agent_evaluation/agent_evaluation.routing.yml`
**Line:** 58 (route start), 65-66 (`_csrf_token` under `options:`)

**Ready-to-apply patch:**
```yaml
agent_evaluation.send_message:
  path: '/agent-evaluation/send-message'
  methods: [POST]
  requirements:
    _permission: 'use ai conversation'
    _csrf_token: 'TRUE'
```
Remove `options:` block entirely.

### Verification command (all three)

```bash
bash sessions/sec-analyst-infra/artifacts/csrf-scan-tool/csrf-route-scan.sh /home/ubuntu/forseti.life
# Expected: exit 0, zero MISPLACED or MISSING flags for ai_conversation / agent_evaluation
```

---

## FINDING-3 (OPEN — new 2026-03-22 — HIGH)

**Description:** 7 routes in `dungeoncrawler_content.routing.yml` have POST methods with `_controller` handlers and no CSRF protection. Two routes (`dice_roll`, `rules_check`) additionally have `_access: TRUE` — no authentication required.

**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml`
**Introduced:** release-next character/ancestry routing surface expansion

**Fix pattern:**
- JSON API routes: add `_csrf_request_header_mode: TRUE` to `requirements:`
- Browser routes: add `_csrf_token: 'TRUE'` to `requirements:`
- `dice_roll` and `rules_check`: also add `_permission: 'access dungeoncrawler characters'`

**Patches:** see `sessions/sec-analyst-infra/artifacts/20260322-improvement-round-20260322-dungeoncrawler-release-next/gap-review.md`

**Verification command:**
```bash
python3 sessions/sec-analyst-infra/artifacts/csrf-scan-tool/verify-dungeoncrawler-content.py
# Exit 0 = all controller POST routes have CSRF; Exit 1 = list unprotected routes
```

---

## FINDING-4 (OPEN — new 2026-03-26 — MEDIUM)

**Description:** 7 `job_hunter` controller routes accept POST with `_permission` or `_user_is_logged_in` requirements but no CSRF protection. These routes were missed by the GAP-002 patch (`694fc424f`), which targeted 6 other routes.

**File:** `sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml`

**Routes:**
- `application_submission_step3` / `step3_short` — `/jobhunter/application-submission/{id}/identify-auth-path`
- `application_submission_step4` / `step4_short` — `/jobhunter/application-submission/{id}/create-account`
- `application_submission_step5` / `step5_short` — `/jobhunter/application-submission/{id}/submit-application` ← **highest risk**: submits application
- `addposting` — `/jobhunter/addposting` — GET/POST combo; `_csrf_token: TRUE` was reverted (`60f2a7ab8`) due to GET 403 regression; requires dev-level fix (split route or controller-level CSRF)

**Fix for step3/4/5:** Add `_csrf_token: 'TRUE'` under `requirements:` in each route.
**Fix for addposting:** Requires dev judgment — split GET/POST into separate routes or apply CSRF at controller level.

**Patches:** see `sessions/sec-analyst-infra/artifacts/20260322-improvement-round-20260322-forseti-release-b/gap-review.md`

**Verification:**
```bash
python3 -c "
import re
with open('sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml') as f:
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
print('FAIL:', issues) if issues else print('PASS')
"
```

---

## Status legend
- **OPEN**: Finding identified; patch written; not confirmed applied
- **CLOSED**: Fix confirmed by direct code inspection or QA approval
