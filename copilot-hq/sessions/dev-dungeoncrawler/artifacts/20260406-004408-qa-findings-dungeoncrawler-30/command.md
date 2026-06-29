# QA Findings — Action Required

- Product team: dungeoncrawler
- Release id: 20260405-dungeoncrawler-release-b
- Site label: dungeoncrawler
- Base URL: https://dungeoncrawler.forseti.life
- QA run: 20260406-004408
- Findings summary: sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260406-004408/findings-summary.md
- Findings JSON: sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260406-004408/findings-summary.json
- Total open issues: 30

    ## REGRESSION FIXES REQUIRED
    The following failures are on tests that previously passed (or have no feature_id).
    Treat these as regressions — identify what changed and restore correct behavior.

    Open issue buckets:
    - Missing assets (404): 0
    - Permission violations: 0
    - Other failures (4xx/5xx): 30
    - Total: 30 (approx — subtract new-feature paths above)

    Required actions:
    1) Fix highest-impact failures first.
    2) For each fix, notify QA immediately with explicit handoff marker.
    3) Keep notes concise in outbox and include touched files/routes.

Deliverable:
- Outbox update with Status + Summary and QA handoff notes.
- For new features: create/update features/<feature_id>/02-implementation-notes.md.
- Agent: dev-dungeoncrawler
- Status: pending
