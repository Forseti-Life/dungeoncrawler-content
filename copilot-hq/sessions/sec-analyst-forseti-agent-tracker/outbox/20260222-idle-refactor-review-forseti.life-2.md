# Outbox update: 20260222-idle-refactor-review-forseti.life-2

- Agent: sec-analyst-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-2
- Generated: 2026-02-22T13:56:06-05:00

---

- Status: done
- Summary: Idle refactor/review cycle targeting `copilot_agent_tracker.routing.yml` (SHA `2f421ca9`). Three concrete hardening improvements found: (1) no `methods: [GET]` constraint on the five admin GET routes — Drupal defaults to accepting any HTTP method on unspecified routes, meaning POST/PUT/DELETE requests reach the same controller actions; adding explicit method constraints is standard Drupal hardening; (2) path parameter `{reply_id}` on `dismiss_sent_message` has no regex constraint (`\d+`) — a non-numeric value bypasses the `int` type hint at the route level and produces a controller-level PHP type error rather than a clean Drupal 404, creating an unnecessary noise surface; (3) path parameters `{agent_id}` and `{item_id}` have no regex allowlist constraint — while SQL injection is not possible (prepared statements confirmed), unbounded parameters allow arbitrary strings of any length and character set to reach the controller, defeating defense-in-depth; a tight pattern like `[A-Za-z0-9_-]+` for agent IDs and `[A-Za-z0-9._-]+` for item IDs would reject malformed requests at the router layer. The state-changing GET routes (approve, dismiss) with CSRF token in query string are noted as a pre-existing MEDIUM finding from cycle 1 and are not re-reported here. All admin routes correctly require `administer copilot agent tracker` permission. The API route correctly constrains to `methods: [POST]` and is token-gated in the controller.

## File reviewed

`sites/forseti/web/modules/custom/copilot_agent_tracker/copilot_agent_tracker.routing.yml` (SHA `2f421ca9`)

## Findings

### [MEDIUM] No HTTP method constraint on admin GET routes
- **Affected routes**: all 5 admin non-API routes (dashboard, agent, waiting_on_keith, waiting_on_keith_message, waiting_on_keith_approve, dismiss_sent_message)
- **Current**: No `methods:` key on any admin route. Drupal accepts any HTTP method.
- **Risk**: A POST/PUT/DELETE to `/admin/reports/copilot-agent-tracker` reaches the same controller action as a GET. While the controller actions are GET-oriented (return render arrays), accepting unexpected methods is unnecessary exposure. The approve and dismiss routes are state-changing and are especially worth locking to GET (they use CSRF token for protection, but explicit method constraint adds a second filter).
- **Likelihood**: Low (requires admin credentials), but trivially exploited with `curl -X POST`.
- **Minimal diff**: Add `methods: [GET]` to each admin route. Example:
  ```yaml
  copilot_agent_tracker.agent:
    path: '/admin/reports/copilot-agent-tracker/agent/{agent_id}'
    methods: [GET]                          # ← add this line
    defaults:
      _controller: '...'
      _title: 'Agent'
    requirements:
      _permission: 'administer copilot agent tracker'
  ```
  Apply identically to: dashboard, waiting_on_keith, waiting_on_keith_message, waiting_on_keith_approve, dismiss_sent_message.
- **Verification**: `curl -s -X POST https://<site>/admin/reports/copilot-agent-tracker` (with valid session cookie) should return 405 Method Not Allowed after fix.
- **Owner**: `dev-forseti-agent-tracker`

### [MEDIUM] `{reply_id}` has no `\d+` regex constraint — non-numeric values produce PHP error instead of 404
- **Affected route**: `copilot_agent_tracker.dismiss_sent_message` — path `/admin/reports/waitingonkeith/sent/{reply_id}/dismiss`
- **Controller signature**: `dismissSentMessage(int $reply_id)` — PHP type hint forces cast, but Drupal resolves the route first and passes the raw string.
- **Current**: No `reply_id` constraint in `requirements:`. Drupal will match the route for any string (e.g., `/sent/foo/dismiss`) and attempt to pass `"foo"` to `dismissSentMessage(int $reply_id)`. PHP 8 strict types will throw a `TypeError`; Drupal may render a 500 rather than a 404.
- **Risk**: An admin probing arbitrary paths gets a noisy 500 error instead of a clean 404. Minor information leakage (stack trace in dev mode).
- **Minimal diff**:
  ```yaml
  copilot_agent_tracker.dismiss_sent_message:
    path: '/admin/reports/waitingonkeith/sent/{reply_id}/dismiss'
    methods: [GET]
    defaults: ...
    requirements:
      _permission: 'administer copilot agent tracker'
      reply_id: '\d+'                         # ← add this line
  ```
- **Verification**: `curl /admin/reports/waitingonkeith/sent/notanumber/dismiss` (with admin session) should return 404 after fix, not 500.
- **Owner**: `dev-forseti-agent-tracker`

### [MEDIUM] `{agent_id}` and `{item_id}` have no character-set regex constraints (defense-in-depth)
- **Affected routes**: `copilot_agent_tracker.agent` (`{agent_id}`), `copilot_agent_tracker.waiting_on_keith_message` (`{item_id}`), `copilot_agent_tracker.waiting_on_keith_approve` (`{item_id}`)
- **Current**: No regex constraints. Any character sequence (including URL-encoded special chars, null bytes, very long strings) is accepted and passed to the controller.
- **Risk**: SQL injection is not possible (confirmed — prepared statements used throughout). However: (a) the agent detail page uses `$agent_id` in `'Agent: @id'` title render — Drupal's `@` placeholder escapes HTML but the value is still reflected; (b) very long `{item_id}` values (>255 chars) reach the controller's linear search through `$meta['inbox_messages']` unchecked; (c) without constraints, security scanning tools will probe many variants and may trigger unexpected code paths. Defense-in-depth argues for tight constraints at the routing layer.
- **Known agent ID format**: `[a-z][a-z0-9-]+` (e.g., `pm-forseti-agent-tracker`)
- **Known item ID format**: `[0-9]{8}-[A-Za-z0-9._-]+` (e.g., `20260222-idle-foo-bar`)
- **Minimal diff**:
  ```yaml
  copilot_agent_tracker.agent:
    path: '/admin/reports/copilot-agent-tracker/agent/{agent_id}'
    requirements:
      _permission: 'administer copilot agent tracker'
      agent_id: '[A-Za-z0-9_-]+'             # ← add this line

  copilot_agent_tracker.waiting_on_keith_message:
    path: '/admin/reports/waitingonkeith/{item_id}'
    requirements:
      _permission: 'administer copilot agent tracker'
      item_id: '[A-Za-z0-9._-]+'             # ← add this line

  copilot_agent_tracker.waiting_on_keith_approve:
    path: '/admin/reports/waitingonkeith/{item_id}/approve'
    requirements:
      _permission: 'administer copilot agent tracker'
      item_id: '[A-Za-z0-9._-]+'             # ← add this line
  ```
- **Verification**: Request `/admin/reports/copilot-agent-tracker/agent/<script>` (URL-encoded) should return 404 after fix.
- **Owner**: `dev-forseti-agent-tracker`

### [INFO] Path prefix inconsistency (`/admin/reports/copilot-agent-tracker` vs `/admin/reports/waitingonkeith`)
- The dashboard and agent detail use `/admin/reports/copilot-agent-tracker/...` but the waiting-on-keith family uses `/admin/reports/waitingonkeith/...`. Not a security issue but makes the admin menu structure inconsistent.
- No action required; documenting for awareness.

### [INFO] State-changing GET routes with CSRF token in query string (pre-existing)
- `waiting_on_keith_approve` and `dismiss_sent_message` are state-changing GET routes. CSRF token validated in controller. Already reported as MEDIUM in cycle 1. Not re-escalated here.

## Confirmed safe (not new issues)
- All admin routes require `administer copilot agent tracker` permission — no privilege escalation risk.
- API route correctly uses `methods: [POST]` and `_access: 'TRUE'` (token-gated in controller).
- Route name `waiting_on_keith_message` path `/admin/reports/waitingonkeith/{item_id}` vs `dismiss_sent_message` path `/admin/reports/waitingonkeith/sent/{reply_id}/dismiss` — no routing ambiguity; Drupal resolves the `sent/` literal prefix before `{item_id}`.

## Follow-up work item (for `dev-forseti-agent-tracker`)

**Title**: Harden `copilot_agent_tracker.routing.yml` — add method constraints and regex requirements on path params

**File**: `sites/forseti/web/modules/custom/copilot_agent_tracker/copilot_agent_tracker.routing.yml`

**Changes** (minimal diff — 3 types of additions):
1. Add `methods: [GET]` to all 5 admin GET routes (dashboard, agent, waiting_on_keith, waiting_on_keith_message, waiting_on_keith_approve, dismiss_sent_message)
2. Add `reply_id: '\d+'` requirement to `dismiss_sent_message`
3. Add `agent_id: '[A-Za-z0-9_-]+'` to `copilot_agent_tracker.agent`; add `item_id: '[A-Za-z0-9._-]+'` to `waiting_on_keith_message` and `waiting_on_keith_approve`

**Acceptance criteria**:
- `curl -X POST .../admin/reports/copilot-agent-tracker` (admin session) → 405
- `curl .../admin/reports/waitingonkeith/sent/abc/dismiss` (admin session) → 404
- `curl .../admin/reports/copilot-agent-tracker/agent/foo<bar>` (URL-encoded, admin session) → 404
- All existing admin routes continue to work normally with valid inputs

**ROI**: 7 — low-effort hardening (YAML-only change, no PHP); closes three defense-in-depth gaps simultaneously; method constraints are a Drupal security best practice that prevents unexpected behavior on any future controller refactor.

## Next actions

- Executor: queue follow-up work item above as inbox item for `dev-forseti-agent-tracker` (route via `pm-forseti-agent-tracker`), with `roi.txt` = 7.
- No PHP changes needed — this is a pure routing YAML change.

## Blockers

- None.

## Needs from Supervisor

- None.

## ROI estimate
- ROI: 7
- Rationale: Pure YAML hardening with no PHP changes — minimal implementation risk, addresses three separate defense-in-depth gaps (method constraints, integer type enforcement, character-set restrictions) in a single file. Method constraints in particular are a Drupal community security best practice that pays ongoing dividends as the module evolves.
