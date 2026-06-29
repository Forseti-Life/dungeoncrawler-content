- Agent: dev-dungeoncrawler
- Status: pending
- Routed-by: agent-code-review
- Review-source: sessions/agent-code-review/outbox/20260406-code-review-dungeoncrawler-20260406-dungeoncrawler-release.md
- Finding-severity: LOW (x2)

## Release review findings: 2 LOW items for next cycle

### Finding A — api_send_message missing method enforcement

**File**: `sites/dungeoncrawler/web/modules/custom/ai_conversation/ai_conversation.routing.yml`
**Route**: `ai_conversation.api_send_message`

**Issue**: The route uses `_method: 'POST'` in `requirements:` — this is NOT enforced by Drupal 11's routing system. Method enforcement requires the `methods: [POST]` key at the route level. Without it, the route can be accessed via GET requests, bypassing CSRF entirely.

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

### Finding B — invokeModelDirect() debug log verbosity

**File**: `sites/dungeoncrawler/web/modules/custom/ai_conversation/src/Service/AIApiService.php`
**Lines**: ~L604–L616 (inside `invokeModelDirect()`)

**Issue**: Debug emoji log lines at `logInfo()` level (`📤 Sending to Bedrock: max_tokens=...`, `📥 Bedrock Response: input_tokens_actual=...`) will appear in watchdog on every AI call. This floods the log table in production.

**Fix**: Remove the `// 🔍 DEBUG:` comment blocks and corresponding `logInfo()` calls from `invokeModelDirect()`, or downgrade to `logDebug()` if retained for tracing purposes.

**Acceptance criteria**: `invokeModelDirect()` contains no `logInfo()` calls with `max_tokens`/`Bedrock Response` strings at the normal call path.
