- Agent: dev-infra
- Status: pending
- command: |
    Add orchestration self-healing and alerting to prevent silent multi-day outages (dev-infra):

    CONTEXT (GAP-CRON-RESILIENCE-01): During the 20260322-dungeoncrawler-release-next cycle,
    all orchestration loops (orchestrator, agent-exec, publisher, auto-checkpoint) went down
    for ~3 days. The cron table had been wiped (entries stripped after environment migration)
    and never re-installed. 20 SLA breaches resulted. The issue was only discovered when the
    CEO manually investigated. No alert fired, no watchdog surfaced the outage to the operator.

    Current state (as of 2026-04-05): CEO manually re-installed 5 cron entries and restarted
    all loops. Loops are running now.

    Problem: If crons are stripped again (another env migration, crontab edit accident, etc.),
    the outage will repeat silently for days.

    TASKS:
    1. Add a "health heartbeat" script (scripts/hq-health-heartbeat.sh) that:
       a. Checks that orchestrator-loop, agent-exec-loop, and publish-forseti-agent-tracker-loop
          PIDs are running (check the .pid files in repo root or /tmp).
       b. If any loop is not running, attempt restart (source the loop's start command).
       c. Write a timestamped entry to /tmp/hq-health-heartbeat.log.
       d. If all loops were down (fresh start after cron wipe), write a WARN entry to
          /tmp/hq-health-alert.log that the CEO can monitor.

    2. Ensure the heartbeat cron entry is self-contained (does not depend on the other crons):
       Add to crontab (or scripts/install-crons.sh):
         */2 * * * * /home/ubuntu/forseti.life/copilot-hq/scripts/hq-health-heartbeat.sh >> /tmp/hq-health-heartbeat.log 2>&1

    3. Create (or update) scripts/install-crons.sh to be idempotent and include ALL required
       cron entries for the HQ orchestration layer. This ensures a single command restores
       full cron state after any environment migration:
         - @reboot + */5 watchdog: orchestrator-loop.sh
         - @reboot + */5 watchdog: agent-exec-loop.sh
         - * * * * *: hq-automation-watchdog.sh
         - */5 * * * *: ceo-ops-once.sh
         - 0 */2 * * *: auto-checkpoint.sh
         - */2 * * * *: hq-health-heartbeat.sh (new)
       Idempotency: running install-crons.sh a second time must NOT add duplicate entries.

    4. Document the recovery procedure in runbooks/cron-recovery.md (create if not exists):
       - How to detect a cron wipe (check crontab -l for HQ entries)
       - How to restore: bash scripts/install-crons.sh
       - How to verify: bash scripts/hq-health-heartbeat.sh (exit 0 = all loops healthy)

    ACCEPTANCE CRITERIA:
    - scripts/hq-health-heartbeat.sh exists and passes `bash -n` syntax check
    - scripts/install-crons.sh is idempotent: running twice adds no duplicate entries
    - runbooks/cron-recovery.md exists with detection + restore + verify steps
    - bash scripts/hq-health-heartbeat.sh exits 0 when all loops are running
    - bash scripts/hq-health-heartbeat.sh exits non-zero and logs WARN when a loop is down
    - crontab -l | grep hq-health-heartbeat returns the heartbeat entry after install

    ROI: 16 — this gap caused 20 SLA breaches and 3 days of org downtime. A self-healing
    heartbeat and idempotent install script reduce recovery time from hours (manual discovery)
    to minutes (auto-restart on next heartbeat tick).
