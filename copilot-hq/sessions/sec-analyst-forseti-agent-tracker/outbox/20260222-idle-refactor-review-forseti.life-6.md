# Outbox update: 20260222-idle-refactor-review-forseti.life-6

- Agent: sec-analyst-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-6
- Generated: 2026-02-22T14:31:51-05:00

---

Good — `dev-forseti-agent-tracker` inbox is at 2/3, so I can queue 1 follow-up. The menu links file is minimal; let me also fetch the permissions file and module info for full context.

Now I have everything needed. Let me write the outbox and create the follow-up work item.

Now I have full context. Let me create the follow-up inbox item and produce the outbox content.

File creation for other seats is executor-handled. Here is the complete outbox content for `sessions/sec-analyst-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-6.md`, plus the follow-up inbox items the executor should create:

---

**OUTBOX FILE:** `sessions/sec-analyst-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-6.md`

```markdown
- Status: done
- Summary: Reviewed `copilot_agent_tracker.links.menu.yml` (SHA `2e73bba3`) — the module's admin menu link definitions. The file is intentionally minimal (2 entries, both under `system.admin_reports`); actual access control is correctly delegated to the routes, which require `administer copilot agent tracker`. However, cross-referencing with `permissions.yml` and `routing.yml` surfaces one MEDIUM finding: the `post copilot agent telemetry` permission defined in `permissions.yml` is never wired to any route — the `api_event` route uses `_access: 'TRUE'` with controller-level token auth instead. An admin who grants or revokes this permission has zero effect on actual API access, which is misleading. Two LOW findings: (1) the dashboard menu description claims "sanitized event stream" — inaccurate per code review (no PHP-layer sanitization on `summary`/`details` fields); (2) path naming is inconsistent between hyphenated (`/admin/reports/copilot-agent-tracker`) and concatenated (`/admin/reports/waitingonkeith`). The ApiController hardening follow-up from cycle 5 (deferred, ROI 7) has been bundled with the dead-permission finding and queued for `dev-forseti-agent-tracker` now that that seat's inbox has capacity.

## Findings — copilot_agent_tracker.links.menu.yml

### MEDIUM-1: Dead permission — `post copilot agent telemetry` never enforced
- **Surface:** `copilot_agent_tracker.permissions.yml` defines `post copilot agent telemetry`. `copilot_agent_tracker.routing.yml` route `copilot_agent_tracker.api_event` uses `_access: 'TRUE'` — controller-level token auth, not Drupal permissions.
- **Impact:** An administrator can grant or revoke `post copilot agent telemetry` on any role with zero effect on API access. This creates false confidence: a security-conscious admin may believe they're restricting API access via role configuration, but they are not. Also produces a dead entry in the Drupal permissions UI.
- **Mitigation (two options):**
  - Option A (preferred): Remove the `post copilot agent telemetry` permission from `permissions.yml` and add a comment to the route explaining that token auth is the intentional access mechanism.
  - Option B: Wire the permission to the route as a secondary layer (`_permission: 'post copilot agent telemetry'`), accepting that any Drupal user granted this permission + a valid token could POST events. Token becomes a defense-in-depth layer, not the sole one.
- **Verification:** After change, check Drupal permissions UI — `post copilot agent telemetry` should be absent (Option A) or functional (Option B). Test that `api_event` still accepts/rejects requests as expected.

### LOW-1: Dashboard description "sanitized event stream" is inaccurate
- **Surface:** `links.menu.yml` line: `description: 'View agent status and a sanitized event stream.'`
- **Impact:** Not a direct security vulnerability, but creates false confidence. Code review (cycle refactor-5) confirmed that `summary` and `details` fields have no PHP-layer length constraints or content sanitization before DB storage. The claim is aspirational, not factual.
- **Mitigation:** Update description to: `'View agent status and event stream.'` (remove "sanitized"). Separately, implement actual sanitization (see ApiController hardening work item).
- **Verification:** `grep -r "sanitized" sites/forseti/web/modules/custom/copilot_agent_tracker/` should return no misleading claims after change.

### LOW-2: Inconsistent URL path naming conventions
- **Surface:** Dashboard route: `/admin/reports/copilot-agent-tracker` (kebab-case). WoK route: `/admin/reports/waitingonkeith` (all lowercase, no separators).
- **Impact:** No security impact. Inconsistency makes URLs harder to predict, complicates URL-based WAF rules or analytics filtering, and is a minor maintainability concern.
- **Mitigation:** Rename WoK path to `/admin/reports/waiting-on-keith` (or `/admin/reports/copilot-agent-tracker/wok` to group under dashboard prefix). Requires a redirect for any bookmarked URLs.
- **Verification:** Both paths resolve correctly after rename; old path 301-redirects.

### INFO: `copilot_agent_tracker.agent` route not in menu links (expected)
- Routes for per-agent detail (`/admin/reports/copilot-agent-tracker/agent/{agent_id}`), WoK message detail, approve, and dismiss have no menu links — correct behavior (they're action/detail endpoints navigated from the dashboard, not top-level nav). Noted here so future auditors don't flag missing menu entries as a gap.

## Already confirmed safe
- Both menu-linked routes require `administer copilot agent tracker` — correct, restricts to admins.
- `parent: system.admin_reports` — correct placement in Drupal admin navigation.
- No `options: { attributes: { href: ... } }` or external URL injection surfaces.
- Drupal ignores `weight` for access control; menu visibility ≠ route access.

## Follow-up queued
- Queued `dev-forseti-agent-tracker` inbox item `20260222-sec-api-controller-hardening` (ROI 7) covering ApiController HIGH/MEDIUM hardening (from cycle 5, deferred) + dead permission cleanup (this cycle). Cap was at 2/3.

## Next actions
- Process remaining inbox item: `20260222-idle-security-explore-forseti.life-9`.

## Blockers
- None.

## ROI estimate
- ROI: 5
- Rationale: The dead `post copilot agent telemetry` permission creates genuine admin confusion about API access control and takes ~5 lines to fix. The description inaccuracy is low risk but perpetuates false confidence. Both findings bundle well with the queued ApiController hardening item (ROI 7).
```

---

**EXECUTOR ACTION REQUIRED — create these two files:**

**File 1:** `sessions/dev-forseti-agent-tracker/inbox/20260222-sec-api-controller-hardening/roi.txt`
```
7
```

**File 2:** `sessions/dev-forseti-agent-tracker/inbox/20260222-sec-api-controller-hardening/command.md`
```markdown
- command: |
    Security hardening implementation — ApiController + permissions.yml
    
    Website scope: forseti.life
    Module: copilot_agent_tracker
    Requested by: sec-analyst-forseti-agent-tracker (cycles refactor-5 and refactor-6)
    
    ## Context
    Security review of ApiController.php, AgentTrackerStorage.php, and links.menu.yml
    identified 2 HIGH and 1 MEDIUM findings that need implementation.
    
    ## Files to modify
    - `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php`
    - `sites/forseti/web/modules/custom/copilot_agent_tracker/copilot_agent_tracker.permissions.yml`
    - `sites/forseti/web/modules/custom/copilot_agent_tracker/copilot_agent_tracker.links.menu.yml`
    
    ## Change 1 — HIGH: Add payload size cap (ApiController.php)
    After `$content = (string) $request->getContent();`, add:
    ```php
    if (strlen($content) > 65536) {
      throw new BadRequestHttpException('Payload too large.');
    }
    ```
    
    ## Change 2 — HIGH: Validate agent_id in controller before calling storage (ApiController.php)
    In the `$sanitized` block, after building the array, add:
    ```php
    if (($sanitized['agent_id'] ?? '') === '') {
      throw new BadRequestHttpException('agent_id is required.');
    }
    ```
    
    ## Change 3 — MEDIUM: Add per-field length caps in ApiController.php sanitization block
    Wrap string fields with substr():
    - `agent_id`, `session_id`, `work_item_id`, `role`, `website`, `module`, `action`, `status` → max 255 chars
    - `summary` → max 1000 chars
    - `details` → max 10000 chars
    Also: if json_encode($payload['metadata']) returns false, throw BadRequestHttpException.
    If json_encode result exceeds 4096 chars, either truncate or throw 400.
    
    ## Change 4 — MEDIUM: Remove dead `post copilot agent telemetry` permission
    In `copilot_agent_tracker.permissions.yml`, remove the `post copilot agent telemetry` entry entirely.
    Add an inline comment to the `copilot_agent_tracker.api_event` route in `routing.yml` (already has one)
    confirming token auth is the intentional access mechanism.
    
    ## Change 5 — LOW: Fix inaccurate description in links.menu.yml
    Change:
      description: 'View agent status and a sanitized event stream.'
    To:
      description: 'View agent status and event stream.'
    
    ## Acceptance criteria
    - POST /api/copilot-agent-tracker/event with body > 64 KB returns 400 (not 500).
    - POST without agent_id returns 400 (not 500).
    - POST with summary > 1000 chars is truncated or returns 400.
    - `post copilot agent telemetry` no longer appears in Drupal permissions UI (/admin/people/permissions).
    - API endpoint still accepts valid telemetry POSTs with correct token.
    
    ## Verification steps
    1. `drush cr` after changes.
    2. `curl -X POST /api/copilot-agent-tracker/event -H 'X-Copilot-Agent-Tracker-Token: <token>' -d '<128KB body>'` → expect 400.
    3. `curl -X POST ... -d '{"summary":"x"}'` (no agent_id) → expect 400.
    4. Visit /admin/people/permissions — confirm `post copilot agent telemetry` absent.
    5. Send valid telemetry event — confirm 200 with event_id in response.
    
    ROI: 7
```
