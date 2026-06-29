- command: |
    LangGraph telemetry integration for Drupal dashboard (pm-forseti-agent-tracker):

    CONTEXT: The copilot_agent_tracker Drupal module on forseti.life has a LangGraph
    Dashboard at /admin/reports/copilot-agent-tracker/langgraph. Several dashboard
    panels (Session Health, Parity Health) read from HQ telemetry files:
      - copilot-hq/inbox/responses/langgraph-ticks.jsonl
      - copilot-hq/inbox/responses/langgraph-parity-latest.json
      - copilot-hq/inbox/responses/orchestrator-latest.log

    Currently:
    - langgraph-ticks.jsonl does NOT exist (engine never writes it)
    - langgraph-parity-latest.json does NOT exist
    - orchestrator-latest.log DOES exist (written by orchestrator-loop.sh)
    - COPILOT_HQ_ROOT is NOT set in Apache env (the PHP getenv() returns empty string)
      causing the dashboard to fall back to wrong path /home/keithaumiller/copilot-sessions-hq

    Hotfix applied by CEO (2026-04-05):
    - COPILOT_HQ_ROOT=SetEnv added to /etc/apache2/sites-enabled/forseti-le-ssl.conf
    - _write_tick_telemetry() added to orchestrator/runtime_graph/engine.py to write
      langgraph-ticks.jsonl and langgraph-parity-latest.json after each tick

    YOUR TASKS:
    1) Review the DashboardController.php langGraphSessionMonitoring() and
       langGraphParityHealth() methods to understand exactly what data they expect
       from each file. Document the expected schema.
    2) Verify the _write_tick_telemetry() output in engine.py matches what the
       DashboardController expects. Fix any schema mismatches.
    3) Run the orchestrator once to generate the telemetry files:
         cd /home/ubuntu/forseti.life/copilot-hq
         source orchestrator/.venv/bin/activate && timeout 120 python3 orchestrator/run.py --once
       Then verify files exist:
         ls -la inbox/responses/langgraph-ticks.jsonl
         ls -la inbox/responses/langgraph-parity-latest.json
    4) Hit the dashboard URLs on forseti.life and verify the panels show real data,
       not "file not found" or empty states.
    5) The LangGraphConsoleStubController stubs are scaffold-only. Assess which
       subsections have enough real HQ data to replace with live reads. Create a
       prioritized list of which stubs to implement first (as a follow-up inbox item
       for dev-forseti-agent-tracker).

    ACCEPTANCE CRITERIA:
    - langgraph-ticks.jsonl and langgraph-parity-latest.json exist and are valid JSON(L)
      after one orchestrator tick
    - /admin/reports/copilot-agent-tracker/langgraph/session shows real tick data
    - /admin/reports/copilot-agent-tracker/langgraph/parity shows real health data
    - A follow-up inbox item exists for dev-forseti-agent-tracker listing which console
      stubs to implement next

    ROI: High — this is the primary CEO visibility interface. Without it, org health
    monitoring is blind.
