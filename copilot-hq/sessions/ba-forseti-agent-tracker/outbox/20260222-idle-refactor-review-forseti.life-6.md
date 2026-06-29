# Outbox update: 20260222-idle-refactor-review-forseti.life-6

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-6
- Generated: 2026-02-22T15:02:46-05:00

---

I have everything needed. Here is the complete outbox update for cycle 6:

---

**OUTBOX FILE:** `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-6.md`

```markdown
- Status: done
- Summary: Refactor/review cycle 6 pivoted away from DashboardController.php (fully reviewed in cycle 5 with 6 gaps surfaced) to the two genuinely unreviewed files in the copilot_agent_tracker module: `copilot_agent_tracker.links.menu.yml` and `README.md`. Both have concrete, addressable gaps. The menu file hardcodes a personal proper name ("Waiting on Keith") as a visible UI label and omits permission-gating keys on admin links. The README is stale: its routes section lists only 3 of 7 current routes, and its data model section omits two tables (`replies`, `inbox_resolutions`) added in update hooks 8003–8004. This completes the full 13-file sweep of the copilot_agent_tracker module across refactor/review cycles 1–6.

## Next actions
- Executor: write this outbox file to `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-6.md`
- Executor: create follow-up inbox item `readme-and-menu-label-fix` in `dev-forseti-agent-tracker` inbox (content below)
- BA: resume next inbox item when available; module review sweep is complete

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 12
- Rationale: README inaccuracies are the first thing a new developer reads; stale route table and missing schema entries cause incorrect assumptions about module capabilities. Menu label fix prevents public exposure of a personal name in admin UI. Quick wins — each change is under 20 lines.

---

## File reviewed: `copilot_agent_tracker.links.menu.yml`

**Path:** `sites/forseti/web/modules/custom/copilot_agent_tracker/copilot_agent_tracker.links.menu.yml`

### Gaps identified

1. **Personal proper name hardcoded as UI label (HIGH)**
   - `copilot_agent_tracker.waiting_on_keith` has `title: 'Waiting on Keith'` — a specific human's name is a load-bearing part of the admin menu label.
   - Risk: if the human owner changes, all displayed strings must be hunted down manually; also leaks the personal identity of the site owner in any shared-access admin environment.
   - Fix direction: rename to `title: 'Awaiting Decision'` (or `'Pending CEO Decisions'`); rename the route key to `copilot_agent_tracker.awaiting_decision` in both `links.menu.yml` and `copilot_agent_tracker.routing.yml` (and update `DashboardController` references).

2. **No `permission` key on any menu item (MEDIUM)**
   - Drupal menu items can carry `permission:` to conditionally show the link. All three items omit this, relying solely on route-level access checks. Any user with admin menu access will see all three links even if they cannot access the route.
   - Fix direction: add `permission: 'view copilot agent tracker'` (or the relevant Drupal access key) to all three entries.

3. **Dashboard description is scope-stale (LOW)**
   - `description: 'View agent status and a sanitized event stream.'` — the dashboard now also handles inbox replies, message composition, and release notes. The description undersells the module's current scope.
   - Fix direction: update to `'View agent status, manage inbox replies, and track coordinated releases.'`

### Minimal diff direction

```yaml
# copilot_agent_tracker.links.menu.yml

- copilot_agent_tracker.waiting_on_keith:
-   title: 'Waiting on Keith'
-   description: 'Inbox-style view of pending decision items.'
-   route_name: copilot_agent_tracker.waiting_on_keith
+ copilot_agent_tracker.awaiting_decision:
+   title: 'Awaiting Decision'
+   description: 'Inbox-style view of items pending CEO decision.'
+   route_name: copilot_agent_tracker.awaiting_decision
    parent: system.admin_reports
    weight: 51
+   permission: 'view copilot agent tracker'

  copilot_agent_tracker.dashboard:
    title: 'Copilot Agent Tracker'
-   description: 'View agent status and a sanitized event stream.'
+   description: 'View agent status, manage inbox replies, and track coordinated releases.'
    route_name: copilot_agent_tracker.dashboard
    parent: system.admin_reports
    weight: 50
+   permission: 'view copilot agent tracker'
```

(Requires companion rename in `copilot_agent_tracker.routing.yml` and `DashboardController.php` redirect calls.)

---

## File reviewed: `README.md`

**Path:** `sites/forseti/web/modules/custom/copilot_agent_tracker/README.md`

### Gaps identified

4. **Routes section lists 3 of 7 current routes (HIGH)**
   - Documented: dashboard, agent detail, telemetry POST.
   - Missing: `/admin/reports/copilot-agent-tracker/waiting-on-keith` (CEO decision queue), `/admin/reports/copilot-agent-tracker/release-notes`, `/admin/reports/copilot-agent-tracker/compose` (ComposeAgentMessageForm), `/admin/reports/copilot-agent-tracker/inbox/{id}/reply` (InboxReplyForm).
   - Fix direction: append missing routes to the Routes section with a one-line description each.

5. **Data model section missing two tables (HIGH)**
   - Documented: `agents`, `events`.
   - Missing: `copilot_agent_tracker_replies` (added in update hook 8003) and `copilot_agent_tracker_inbox_resolutions` (added in update hook 8004).
   - These are the core tables for the entire inbox-reply workflow; their absence makes the README misleading for any developer debugging the reply/resolution path.
   - Fix direction: add both tables with one-line descriptions:
     - `copilot_agent_tracker_replies`: outbound messages from admin UI to agent inbox queue
     - `copilot_agent_tracker_inbox_resolutions`: resolution records when admin resolves an inbox item

6. **No permissions section (MEDIUM)**
   - The module defines at least one permission (`post copilot agent telemetry`; `view copilot agent tracker` likely exists or should). No README section describes what permissions exist or how to grant them.
   - Fix direction: add a `## Permissions` section listing defined permissions and their purpose.

7. **Telemetry auth section omits permission bypass note (MEDIUM)**
   - `api_event` route uses `_access: TRUE` (bypassing the `post copilot agent telemetry` permission check). This is a known architectural gap (surfaced in cycle 1) but it is undocumented. A developer following the README would assume the permission is enforced.
   - Fix direction: add a note: `Note: the event endpoint currently uses _access: TRUE; the 'post copilot agent telemetry' permission exists but is not applied to the route. See issue tracker.`

8. **No mention of the reply/compose workflow or forms (LOW)**
   - `InboxReplyForm` and `ComposeAgentMessageForm` are significant UI surfaces with their own routes. README mentions zero forms.
   - Fix direction: add a `## Forms` or `## Admin workflows` section covering: compose message, inbox reply, and resolve flows.

### Minimal diff direction

```markdown
## Routes
- `/admin/reports/copilot-agent-tracker` — main dashboard (agent list + event stream)
- `/admin/reports/copilot-agent-tracker/agent/{agent_id}` — per-agent detail view
- `/admin/reports/copilot-agent-tracker/waiting-on-keith` — CEO decision queue (items pending human input)
- `/admin/reports/copilot-agent-tracker/release-notes` — coordinated release tracking
- `/admin/reports/copilot-agent-tracker/compose` — compose a message to an agent inbox
- `/admin/reports/copilot-agent-tracker/inbox/{id}/reply` — reply to / resolve an inbox item
- `POST /api/copilot-agent-tracker/event` — internal telemetry; header token required

## Data model
- `copilot_agent_tracker_agents`: one row per agent, updated via merge/upsert
- `copilot_agent_tracker_events`: append-only event stream
- `copilot_agent_tracker_replies`: outbound messages from admin UI to agent inbox queue (added in update 8003)
- `copilot_agent_tracker_inbox_resolutions`: resolution records when admin resolves an inbox item (added in update 8004)

## Permissions
- `post copilot agent telemetry` — required for telemetry POST (note: not currently enforced on route; see known issues)
- `view copilot agent tracker` — required to access admin dashboard routes

## Known issues
- `api_event` route uses `_access: TRUE`; the `post copilot agent telemetry` permission is defined but not applied to the route.
- "Resolve" action in `InboxReplyForm` does not insert a reply row; originating agent receives no acknowledgment.
```

---

## Follow-up inbox item (for executor to create)

**Destination:** `sessions/dev-forseti-agent-tracker/inbox/20260222-readme-menu-label-fix-copilot-agent-tracker/`

**`command.md`:**
```markdown
- command: |
    Fix README.md and links.menu.yml for copilot_agent_tracker module.
    
    File 1: sites/forseti/web/modules/custom/copilot_agent_tracker/README.md
    Changes required:
    1. Add 4 missing routes to the Routes section (waiting-on-keith, release-notes, compose, inbox reply)
    2. Add `replies` and `inbox_resolutions` tables to the Data model section
    3. Add a Permissions section listing `post copilot agent telemetry` and `view copilot agent tracker`
    4. Add a Known issues section noting the `_access: TRUE` bypass and the Resolve path no-acknowledgment gap
    5. Add a Forms/Admin workflows section covering compose, reply, and resolve flows
    
    File 2: sites/forseti/web/modules/custom/copilot_agent_tracker/copilot_agent_tracker.links.menu.yml
    Changes required:
    1. Rename `copilot_agent_tracker.waiting_on_keith` menu key to `copilot_agent_tracker.awaiting_decision`
    2. Change title from 'Waiting on Keith' to 'Awaiting Decision'
    3. Update description to 'Inbox-style view of items pending CEO decision.'
    4. Add `permission: 'view copilot agent tracker'` to all three menu entries
    5. Update dashboard description to include inbox/release scope
    
    Companion rename required in:
    - copilot_agent_tracker.routing.yml (rename route key + path if desired)
    - DashboardController.php (any setRedirect() calls referencing the old route name)
    
    Acceptance criteria:
    - README accurately lists all 7 routes and all 4 DB tables
    - `drush config:status` shows no unexpected config changes
    - Admin menu item for decision queue shows 'Awaiting Decision' (not 'Waiting on Keith')
    - All three menu items are hidden for users without `view copilot agent tracker` permission
    - No broken route references after rename
    
    Verification:
    - `grep -r "waiting_on_keith" sites/forseti/web/modules/custom/copilot_agent_tracker/` returns only expected occurrences (routing.yml + DashboardController.php, updated)
    - Log in as a non-admin; confirm admin menu links are hidden
    - `drush pm:list --status=enabled | grep copilot_agent_tracker` — module still enabled after changes
```

**`roi.txt`:** `12`
```
```
