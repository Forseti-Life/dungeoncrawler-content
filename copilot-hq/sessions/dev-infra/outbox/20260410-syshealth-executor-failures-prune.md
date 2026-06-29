Now I'll add the auto-prune after writing the failure record — keep the last 200, delete the rest:

Health check passes with PASS. Now commit and write outbox:

- Status: done
- Summary: The executor failure backlog had 2838 items at dispatch time but had already been reduced to 17 (all from today) by the time this task ran — the prior inbox close policy and orchestrator tick activity appear to have cleared the stale entries. The 17 current entries are legitimate signal and below the 100-item threshold for the backlog warning. To prevent future accumulation, `scripts/agent-exec-next.sh` now auto-prunes the `tmp/executor-failures/` directory to the 200 most-recent entries each time a new failure record is written. Health check verified: `✅ PASS Executor failures (last 24h): 0 (total: 17)`.

## Next actions
- None. Monitor health check on next syshealth run; PASS confirmed.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 8
- Rationale: Unbounded growth of the failures directory creates noise in health checks and wastes disk I/O on every health check scan. The cap prevents recurrence cheaply.

---
Commit: `331d193f5`

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260410-syshealth-executor-failures-prune
- Generated: 2026-04-10T17:23:15+00:00
