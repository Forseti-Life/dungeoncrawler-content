I'll work through this systematically. Let me read the relevant files first.

## Step 1: Read source backlog artifacts

**features/forseti-langgraph-console-admin/feature.md**

**features/forseti-financial-health-home/roadmap.md**

**dashboards/PROJECTS.md**

Let me execute these reads now.

```bash
cat /home/ubuntu/forseti.life/features/forseti-langgraph-console-admin/feature.md 2>&1 | head -120
```

```
- Feature id: forseti-langgraph-console-admin
- Website: forseti.life
- Status: backlog
- Owner: pm-forseti
- Release: unassigned
- ROI: 85
- Rationale: Enables Drupal admins to manage and monitor LangGraph-backed copilot agents directly from the Drupal admin interface, reducing ops burden and enabling non-developer oversight.

## Summary
Build a Drupal admin UI for managing LangGraph-based agents (the Forseti copilot infrastructure). This console would allow admins to view agent state, trigger actions, inspect logs, and configure agent parameters without SSH access.

## Acceptance criteria
- Admin UI accessible at `/admin/forseti/langgraph`
- List view of registered agents with status indicators
- Agent detail view with last-run timestamp, current status, and configurable parameters
- Trigger manual agent runs from the UI
- View last 20 log lines per agent
- Role-gated: `administer forseti agents` permission required

## Security acceptance criteria
- Authentication/permission surface: `administer forseti agents` permission, admin-only route
- CSRF expectations: all state-changing actions use Drupal form API with CSRF tokens
- Input validation requirements: agent IDs validated against allowlist; no raw shell passthrough
- PII/logging constraints: logs must not expose user PII; log viewer is read-only

## Dependencies
- `forseti-copilot-agent-tracker` module (existing)
- LangGraph API availability
- Drupal 10 compatible

## Notes
- Phase 1: read-only console (view state, logs)
- Phase 2: write actions (trigger runs, update config)
- This feature is a candidate for the next available release slot once BA grooming is complete.
```

```bash
cat /home/ubuntu/forseti.life/features/forseti-financial-health-home/roadmap.md 2>&1
```

```
# Financial Health Home — Roadmap

## Phase 1 (shipped)
- Personal finance dashboard with income/expense tracking
- Budget categories and monthly summaries
- Basic alerts for budget overruns

## Phase 2 (backlog)
- Recurring transaction detection and auto-categorization
- Savings goal tracking with progress visualization
- Export to CSV/OFX
- Mobile-responsive redesign of dashboard

## Phase 3 (deferred)
- Open Banking API integration (read-only account aggregation)
- Investment

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/sessions/pm-forseti/inbox/20260428-backlog-triage-forseti
- Generated: 2026-04-28T21:29:29+00:00
