- Status: done
- Completed: 2026-04-10T17:18:19Z

- Agent: dev-forseti
- Status: pending
- command: |
    Clean up two LOW findings from the forseti-release-c code review BLOCK:

    ## Finding A — Dead `form_token` hidden field in forseti-conversations template
    File: `sites/forseti/web/modules/custom/ai_conversation/templates/forseti-conversations.html.twig`
    Line: ~71 — `<input type="hidden" name="form_token" value="{{ csrf_token(delete_url) }}">`

    This hidden field is dead code. `CsrfAccessCheck` reads only `?token=` from the query
    string — it never inspects the POST body. The token is already correctly appended to
    the form action URL by `RouteProcessorCsrf::processOutbound()` via Twig `path()`.
    Remove this input element.

    Acceptance criteria:
    - `<input type="hidden" name="form_token"...>` removed from forseti-conversations.html.twig
    - Verify: `grep -n 'name.*form_token' sites/forseti/web/modules/custom/ai_conversation/templates/forseti-conversations.html.twig` returns no results
    - Verify form still works (conversation delete action still fires correctly)

    ## Finding B — Hardcoded absolute path constant in DashboardController
    File: `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php`
    Line: ~41 — `const RELEASE_CYCLE_CONTROL_FILE_LEGACY = '/home/ubuntu/forseti.life/copilot-hq/tmp/release-cycle-control.json';`

    This is an admin-only path used for legacy fallback; no user data exposure. However
    hardcoded absolute paths are a maintenance risk and break portability.

    Fix: Replace the hardcoded constant with an `getenv('COPILOT_HQ_ROOT') ?: '/home/ubuntu/forseti.life/copilot-hq'`
    pattern (matching the existing pattern in the same controller). If the constant is no longer
    used in release-c scope, remove it entirely. Check usage at line ~1180.

    Acceptance criteria:
    - No hardcoded `/home/ubuntu/forseti.life/copilot-hq` absolute path in DashboardController constants
    - Verify: `grep -n 'home/ubuntu' sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php` returns no results
    - If constant is removed, verify `readJsonFile(self::RELEASE_CYCLE_CONTROL_FILE_LEGACY)` call at line ~1180 is also removed or replaced

    ## Source
    Code review: `sessions/agent-code-review/outbox/20260410-code-review-forseti.life-20260410-forseti-release-c.md`
    Release: 20260410-forseti-release-c
    Priority: LOW (non-blocking, cleanup only — do in next available cycle)
