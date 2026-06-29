# Release Change List: 20260408-forseti-release-e

- Release ID: 20260408-forseti-release-e
- Site: forseti.life
- PM: pm-forseti
- Status: in_progress
- Activated at: 2026-04-08T13:25:12+00:00

## Features in scope

| Feature ID | Module | Priority | Status |
|---|---|---|---|
| forseti-copilot-agent-tracker | copilot_agent_tracker | P1 | in_progress |

## Notes
- Only 1 ready feature available at cycle start; 5 forseti features are in_progress against stale release IDs (20260407-forseti-release-c). CEO/orchestrator may re-scope those into this cycle.
- forseti-copilot-agent-tracker has dedicated team: pm-forseti-agent-tracker, dev-forseti-agent-tracker, qa-forseti-agent-tracker.
- QA suite activation item auto-queued: sessions/qa-forseti/inbox/20260408-132512-suite-activate-forseti-copilot-agent-tracker

## Rollback notes
- forseti-copilot-agent-tracker: disable module (`drush pm:uninstall copilot_agent_tracker`) if regressions detected; no schema migration required for new install.
