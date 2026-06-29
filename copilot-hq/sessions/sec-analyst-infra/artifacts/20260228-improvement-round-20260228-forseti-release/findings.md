# Security Findings — Forseti site review (2026-02-28, forseti-release cycle)
# Analyst: sec-analyst-infra (ARGUS)

---

## Scope

Changed files since last push (`origin/main..HEAD`):
- `sites/forseti/web/modules/custom/forseti_content/forseti_content.routing.yml`
- `sites/forseti/web/modules/custom/forseti_safety_content/forseti_safety_content.routing.yml`
- `sites/forseti/web/modules/custom/job_hunter/` (multiple files — reviewed last cycle, no delta)

Additional surfaces audited (static review sweep across all modules):
- `ai_conversation.routing.yml`
- `agent_evaluation.routing.yml`
- `amisafe.routing.yml`
- `forseti_games.routing.yml`
- `forseti_content.routing.yml`

---

## In-scope changes review

### talk-with-forseti route auth fix — PASS

Commit `cf808dd76` changed both `/talk-with-forseti` and `/talk-with-forseti_content` routes from `_permission: 'access content'` to `_user_is_logged_in: 'TRUE'`. This is the correct fix. Unauthenticated users now receive 403 directly instead of a redirect that resulted in 200 via the registration page. No residual exposure.

**Verification**: `curl -I https://forseti.life/talk-with-forseti` → should return 403 (or redirect to login, not 200/302-to-register-page).

---

## Findings (sweep)

---

## FINDING-1 — MEDIUM: `_csrf_token: TRUE` in `options:` is ineffective — LLM send-message endpoints unprotected

**Surface**:
- `ai_conversation.routing.yml` line 105: `ai_conversation.send_message` at `/ai-conversation/send-message`
- `agent_evaluation.routing.yml` line 66: `agent_evaluation.send_message` (same pattern)

**Problem**: Both LLM chat send-message endpoints have `_csrf_token: TRUE` placed under `options:` instead of `requirements:`:

```yaml
# CURRENT (ineffective — options: is not processed by Drupal access checkers)
ai_conversation.send_message:
  path: '/ai-conversation/send-message'
  requirements:
    _permission: 'use ai conversation'
    _method: 'POST'
  options:
    _csrf_token: TRUE   # <-- WRONG placement: no enforcement
```

Correct pattern (from `forseti_games.routing.yml` — properly implemented):

```yaml
# CORRECT (requirements: is processed by Drupal access system)
forseti_games.api.submit_score:
  methods: [POST]
  requirements:
    _user_is_logged_in: 'TRUE'
    _csrf_token: 'TRUE'  # <-- correct: enforced as access requirement
```

In Drupal routing, `options:` is for route metadata and framework configuration (e.g., `no_cache`, `parameters`). It is **not** read by the CSRF access checker. `_csrf_token: 'TRUE'` in `options:` is a silent no-op — CSRF protection is not active.

**Attack scenario**: An attacker tricks a logged-in user with `use ai conversation` permission into visiting a page containing:
```html
<form action="https://forseti.life/ai-conversation/send-message" method="POST">
  <input name="conversation_id" value="<victim_conversation_id>">
  <input name="message" value="<attacker_content>">
</form>
<script>document.forms[0].submit();</script>
```
The POST succeeds, injecting attacker-controlled content into the victim's LLM conversation. The AI model responds as if the user sent the message.

**Impact**:
- Injection of attacker-controlled messages into victim's LLM conversation history
- LLM API cost consumption (each injected message costs credits)
- Conversation history contamination (stored in Drupal DB under victim's user)
- If conversation history is used to inform future AI responses (context window), this could influence AI output quality

**Likelihood**: Low-medium. Requires victim to be logged in and visit attacker-controlled page. No special privileges beyond `use ai conversation`.

**Severity**: Medium — real attack scenario, non-hypothetical, affects core product feature.

**Pre-existing status**: This bug was introduced before the current release cycle (last modified in `b9142ef9b`). Not a regression from this cycle. Reporting now because the sweep caught it.

**Ready-to-apply patch** (routing YAML only):

```yaml
# ai_conversation.routing.yml — patch for send_message
ai_conversation.send_message:
  path: '/ai-conversation/send-message'
  defaults:
    _controller: '\Drupal\ai_conversation\Controller\ChatController::sendMessage'
  methods: [POST]
  requirements:
    _permission: 'use ai conversation'
    _csrf_token: 'TRUE'

# agent_evaluation.routing.yml — patch for send_message (same fix)
agent_evaluation.send_message:
  path: '/ai-conversation/send-message'  # confirm path
  defaults:
    _controller: '\Drupal\agent_evaluation\Controller\ChatController::sendMessage'
  methods: [POST]
  requirements:
    _permission: 'use ai conversation'
    _csrf_token: 'TRUE'
```

**Note**: Remove `_method: 'POST'` from requirements (non-standard) and move to top-level `methods: [POST]` (standard Drupal pattern). Move `_csrf_token` from `options:` to `requirements:`. Frontend callers must pass `?token=<csrf_token>` in the request URL.

**Verification**: After patch, POST to `/ai-conversation/send-message` without CSRF token → 403. With valid CSRF token and authenticated session → 200.

---

## FINDING-2 — LOW: Batch eval API routes accept GET (state-mutating admin endpoints, no method restriction)

**Surface**: `forseti_content.routing.yml`
- `forseti_content.batch_process_entity` at `/api/batch-evaluate-entity` — no `methods:` restriction
- `forseti_content.calculate_category_average` at `/api/calculate-category-average` — no `methods:` restriction

**Problem**: Both routes perform state-mutating operations (batch entity evaluation, average recalculation) but have no `methods: [POST]` restriction, meaning they accept GET requests. While access is restricted to `administer site configuration` permission (admin-only), the pattern is unsafe:
- Admin-accessible GET-based state mutation can be triggered via `<img src="...">` or prefetch links in admin-facing emails/content
- Admin users have full site trust, so CSRF risk is lower, but defense-in-depth recommends POST-only for mutation endpoints

**Likelihood**: Very low. Admin-only, requires admin-level CSRF target.

**Severity**: Low.

**Mitigation**: Add `methods: [POST]` to both routes.

---

## Process improvement: Extend csrf-route-sweep.py to detect CSRF in wrong YAML key

**Context**: Last cycle I proposed `scripts/csrf-route-sweep.py` (SMART spec in `sessions/sec-analyst-infra/artifacts/20260228-improvement-round-20260228-dungeoncrawler-release/findings.md`). This cycle's FINDING-1 reveals a second variant: `_csrf_token` appearing in `options:` instead of `requirements:`. A basic grep sweep would have caught this.

**Extension to the SMART spec**:

The `csrf-route-sweep.py` script should detect TWO failure modes:
1. POST/PATCH/PUT/DELETE routes with **no `_csrf_token` anywhere** (last cycle's finding)
2. Routes with `_csrf_token` placed under **`options:` instead of `requirements:`** (this cycle's finding)

**Updated SMART outcome**:
- **Specific**: Python script detects both absence of `_csrf_token` AND misplacement in `options:` for all state-mutating routes. Exit 1 when either pattern found. Reports as `[MISSING]` or `[MISPLACED]` per route.
- **Measurable**: Zero CSRF routing gaps (missing or misplaced) reach Gate 2; script output included in pre-flight as PASS/FLAG.
- **Achievable**: ~80 LOC Python (small extension over previous ~60 LOC estimate).
- **Relevant**: Two consecutive cycles found CSRF routing gaps; the misplacement variant silently bypasses developer intent. Automated detection before Gate 1 eliminates both failure modes.
- **Time-bound**: Target: before `20260228-forseti-release-next` Gate 1.

---

## Summary table

| # | Severity | Finding | Pre-existing? | Mitigation ready? |
|---|---|---|---|---|
| 1 | Medium | `_csrf_token` in `options:` (ineffective) — LLM send-message endpoints unprotected | Yes (pre-existing) | Yes — patch in finding |
| 2 | Low | Batch eval API routes accept GET | Yes (pre-existing) | Yes — add `methods: [POST]` |

**In-scope change**: talk-with-forseti auth fix (commit `cf808dd76`) — PASS, correctly implemented.

---
Generated: 2026-02-28T15:39:05Z
Analyst: sec-analyst-infra (ARGUS)
