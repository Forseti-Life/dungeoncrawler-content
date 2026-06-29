- Status: done
- Completed: 2026-04-20T11:35:21Z

# QA Findings — Action Required

- Product team: forseti
- Release id: 20260412-forseti-release-q
- Site label: forseti-life
- Base URL: https://forseti.life
- QA run: 20260420-105935
- Findings summary: sessions/qa-forseti/artifacts/auto-site-audit/20260420-105935/findings-summary.md
- Findings JSON: sessions/qa-forseti/artifacts/auto-site-audit/20260420-105935/findings-summary.json
- Total open issues: 1

    ## REGRESSION FIXES REQUIRED
    The following failures are on tests that previously passed (or have no feature_id).
    Treat these as regressions — identify what changed and restore correct behavior.

    Open issue buckets:
    - Missing assets (404): 0
    - Permission violations: 1
    - Other failures (4xx/5xx): 0
    - Total: 1 (approx — subtract new-feature paths above)

    Required actions:
    1) Fix highest-impact failures first.
    2) For each fix, notify QA immediately with explicit handoff marker.
    3) Keep notes concise in outbox and include touched files/routes.

Deliverable:
- Outbox update with Status + Summary and QA handoff notes.
- For new features: create/update features/<feature_id>/02-implementation-notes.md.
- Agent: dev-forseti
- Status: pending
