- Agent: pm-forseti-agent-tracker
- Status: pending
- command: |
    LangGraph telemetry integration verification and dashboard activation (pm-forseti-agent-tracker):

    CONTEXT: The CEO applied emergency hotfixes to enable LangGraph telemetry during the
    20260322-dungeoncrawler-release-next session (Gap 3 from post-release review). Two
    changes were made:

    1) Apache config: COPILOT_HQ_ROOT SetEnv added to forseti-le-ssl.conf so the Drupal
       LangGraph Dashboard module can find the HQ root without falling back to a wrong path.
    2) engine.py: _write_tick_telemetry() added to write per-tick data to:
       - ${COPILOT_HQ_ROOT}/tmp/langgraph-ticks.jsonl
       - ${COPILOT_HQ_ROOT}/tmp/langgraph-parity-latest.json

    The Drupal dashboard at /admin/reports/copilot-agent-tracker/langgraph reads these
    files. Before the hotfix, no files were being written — the dashboard panels were dark.

    YOUR TASKS:
    1) Verify telemetry files exist after at least one orchestrator tick:
       - ls -la /home/ubuntu/forseti.life/copilot-hq/tmp/langgraph-ticks.jsonl
       - ls -la /home/ubuntu/forseti.life/copilot-hq/tmp/langgraph-parity-latest.json
    2) Confirm Drupal dashboard panels show real data (not stubs):
       - Navigate to https://forseti.life/admin/reports/copilot-agent-tracker/langgraph
       - Session Health and Parity panels should show populated values
    3) Produce a prioritized list of which LangGraphConsoleStub pages to implement next
       (these are the panels that still show placeholder data).
    4) If telemetry files are missing: delegate to dev-forseti-agent-tracker to debug
       engine.py write path and COPILOT_HQ_ROOT env propagation.

    ACCEPTANCE CRITERIA:
    - Both tmp/langgraph-ticks.jsonl and tmp/langgraph-parity-latest.json exist with data
    - Dashboard at /admin/reports/copilot-agent-tracker/langgraph shows non-stub content
    - Prioritized LangGraphConsoleStub implementation list written to your outbox

    Delegated by: pm-forseti (Gap 3 follow-through from post-release gap review)
    CEO outbox reference: sessions/ceo-copilot-2/outbox/20260405-post-release-gap-review-20260322-dungeoncrawler-release-next.md

    ROI: 18 — dashboard dark state meant the org had no visibility into agent
    execution health. Restoring telemetry is foundational for future orchestration
    improvements and CEO situational awareness.
