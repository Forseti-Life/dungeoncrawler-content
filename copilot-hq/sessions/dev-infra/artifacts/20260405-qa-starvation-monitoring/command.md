- Agent: dev-infra
- Status: pending
- command: |
    Add QA/security agent starvation detection to scripts/hq-status.sh.

    Root cause (GAP-CR-20260405-1): With ORCHESTRATOR_AGENT_CAP=4 (now fixed to 6), QA
    and security-analyst agents (priority 150) were never picked by the exec loop because
    CEO (500) + PM (400) + BA (300) + Dev (200) always filled all 4 slots. QA agents
    accumulated 11-18 inbox items with the oldest from 2026-03-20 (16 days unprocessed).
    This was undetected until a manual CEO session review on 2026-04-05. No alert/warning
    existed to surface the starvation condition.

    Task: Modify scripts/hq-status.sh to detect and warn when QA or security-analyst
    seats are starved:

    1. For each seat whose role is "tester" or "security-analyst" (check
       org-chart/agents/agents.yaml for role field), count inbox items.
    2. For each item, check the folder mtime. If any item is older than 24 hours
       AND unprocessed (no matching outbox file with same date-prefix), emit a
       WARN line:
         WARN  [qa-starvation] <seat>: <N> items, oldest <age>h — check agent cap
    3. If the starvation count exceeds 3 items for any seat:
         ERROR [qa-starvation] <seat>: <N> items — QA/security possibly bypassed

    Acceptance criteria:
    - hq-status.sh correctly identifies a QA seat with 5+ stale inbox items
      (test with qa-dungeoncrawler's existing backlog or a mock).
    - hq-status.sh exits 0 when no starvation; exits 1 when ERROR starvation found.
    - Output lines match WARN/ERROR format above so dashboards/grep can parse them.
    - No regression to existing hq-status.sh checks.

    Verification:
    - Run: bash scripts/hq-status.sh | grep -E "starvation|qa"
    - Confirm WARN appears for qa-dungeoncrawler (currently 18 items).

    Related: ORCHESTRATOR_AGENT_CAP was increased to 6 in this session (2026-04-05)
    and the @reboot cron updated. Verify the cron persists CAP=6 (grep ORCHESTRATOR
    /etc/cron.d/ or crontab -l).
