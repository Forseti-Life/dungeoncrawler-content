- Agent: dev-forseti
- Status: pending
- Routed-by: agent-code-review
- Review-source: sessions/agent-code-review/outbox/20260406-code-review-forseti.life-20260406-forseti-release.md
- Finding-severity: LOW (x2)

## Release review findings: 2 LOW items for next cycle

### Finding A — DashboardController `langgraphPath()` fallback path (NEW in 20260406-forseti-release)

**File**: `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php`
**Introduced by commit**: `62b95688`

**Issue**: The `langgraphPath()` helper introduced in this release uses a wrong fallback:

```php
$root = rtrim((string) (getenv('COPILOT_HQ_ROOT') ?: '/home/keithaumiller/copilot-sessions-hq'), '/');
```

The path `/home/keithaumiller/copilot-sessions-hq` does not exist on the production server. The correct HQ root is `/home/ubuntu/forseti.life/copilot-hq`. If `COPILOT_HQ_ROOT` env var is unset, all LangGraph dashboard sections silently return from non-existent file reads with no output.

Additionally, three glob calls in the same file were NOT updated to use `langgraphPath()` (pre-existing from a prior commit):
- `inferReleaseIdFromQaPreflightArtifacts()` (~L1451, ~L1456): hardcoded glob patterns
- `inferReleaseIdFromSignoffs()` (~L1495): hardcoded glob pattern

**Required fix**:
1. Change `langgraphPath()` fallback to `'/home/ubuntu/forseti.life/copilot-hq'`
2. Update the three remaining hardcoded glob calls to use `$this->langgraphPath(...)`:
   - `inferReleaseIdFromQaPreflightArtifacts()`: replace hardcoded paths with `$this->langgraphPath('sessions/qa-*/...')`
   - `inferReleaseIdFromSignoffs()`: replace hardcoded pattern with `$this->langgraphPath('sessions/pm-*/...')`

**Acceptance criteria**: `grep -n 'keithaumiller' DashboardController.php` returns 0 matches. LangGraph dashboard sections load from correct path when `COPILOT_HQ_ROOT` is unset.

---

### Finding B — ai_conversation.api_send_message missing method enforcement (pre-existing)

**File**: `sites/forseti/web/modules/custom/ai_conversation/ai_conversation.routing.yml`
**Route**: `ai_conversation.api_send_message`

**Issue**: Route uses `_method: 'POST'` in `requirements:` — not enforced by Drupal 11 routing system. Method restriction requires `methods: [POST]` at route level. Without it, the route is accessible via GET, bypassing CSRF protection. Also missing `_csrf_request_header_mode: TRUE`.

**Current (incorrect)**:
```yaml
ai_conversation.api_send_message:
  path: '/api/ai-conversation/{conversation_id}/message'
  defaults:
    _controller: '\Drupal\ai_conversation\Controller\ApiController::sendMessage'
  requirements:
    _permission: 'use ai conversation'
    _method: 'POST'
    _format: 'json'
    conversation_id: \d+
```

**Required fix**:
```yaml
ai_conversation.api_send_message:
  path: '/api/ai-conversation/{conversation_id}/message'
  defaults:
    _controller: '\Drupal\ai_conversation\Controller\ApiController::sendMessage'
  methods: [POST]
  requirements:
    _permission: 'use ai conversation'
    _csrf_request_header_mode: TRUE
    _format: 'json'
    conversation_id: \d+
```

Remove `_method: 'POST'` from requirements, add `methods: [POST]` at route level, add `_csrf_request_header_mode: TRUE`.

**Acceptance criteria**: `grep -A12 'api_send_message:' ai_conversation.routing.yml` shows `methods: [POST]` and `_csrf_request_header_mode: TRUE`; no `_method:` in requirements.
