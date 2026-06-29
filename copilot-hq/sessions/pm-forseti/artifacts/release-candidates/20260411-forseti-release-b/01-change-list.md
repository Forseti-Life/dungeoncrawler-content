# Change List: 20260411-forseti-release-b

- Release ID: 20260411-forseti-release-b
- Site: forseti.life
- Activated: 2026-04-11T16:08:00+00:00
- Features: 2

## Features in scope

### forseti-jobhunter-application-deadline-tracker
- Module: job_hunter
- Status: in_progress
- Summary: Add application deadline and follow-up date tracking per job application. Users can set deadline/follow-up dates; dashboard shows urgency indicators (overdue, due-soon, on-track). Notification hooks for approaching deadlines.
- Dev inbox: sessions/dev-forseti/inbox/20260411-160846-impl-forseti-jobhunter-application-deadline-tracker
- QA inbox: sessions/qa-forseti/inbox/20260411-160846-suite-activate-forseti-jobhunter-application-deadline-tracker

### forseti-langgraph-console-release-panel
- Module: forseti_agent_tracker (LangGraph Console)
- Status: in_progress
- Summary: Wire the Release Control Panel in the LangGraph Console to live state files (tmp/release-cycle-active/). Panel displays active release ID, started_at, feature count, and SLA status from real file reads rather than static placeholders.
- Dev inbox: sessions/dev-forseti-agent-tracker/inbox/20260411-160846-impl-forseti-langgraph-console-release-panel
- QA inbox: sessions/qa-forseti/inbox/20260411-160846-suite-activate-forseti-langgraph-console-release-panel

## Notes
- Only 2 `ready` features existed at scope-activate time; inbox requested 3+. No additional groomed features are in the ready pool for forseti. Release-g (3 features) is running in parallel under its own release ID.
- KB reference: none found for this specific release pattern.
