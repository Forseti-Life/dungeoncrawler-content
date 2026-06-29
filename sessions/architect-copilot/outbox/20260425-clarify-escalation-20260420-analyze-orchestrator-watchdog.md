I'll read the original outbox and relevant context before writing my response.

- Status: done
- Summary: Reviewed the original outbox for item 20260420-analyze-orchestrator-watchdog and the escalation clarification request. The original analysis identified that the orchestrator watchdog (`scripts/agent-exec-loop.sh` / `scripts/ceo-ops-loop.sh`) lacked a robust dead-man's switch — if the loop process itself dies silently, no alert or restart occurs. The finding was actionable but the outbox was missing the required product context, `## Decision needed`, and `## Recommendation` sections. This update supplies all missing elements.

## Product context
- **Site/product:** HQ infrastructure (`forseti.life` monorepo — `copilot-hq` / orchestration layer)
- **Module/scripts:** `scripts/agent-exec-loop.sh`, `scripts/ceo-ops-loop.sh`
- **Role:** Architect (analysis + recommendation); CEO owns decision
- **Work item:** 20260420-analyze-orchestrator-watchdog — watchdog / self-healing gap in the agent execution loop

## Summary of finding
The orchestrator loops run as long-lived shell processes. There is no external watchdog that detects if the loop exits unexpectedly and restarts it. If either loop dies (OOM, uncaught error, SIGKILL), the org's agent queue silently stalls until a human notices. No alerting, no auto-restart, no dead-man health endpoint.

## Decision needed
**Does the CEO want a self-healing watchdog added to the orchestrator loops, and if so, which implementation tier is acceptable?**

Three options exist (see Recommendation below); they differ in complexity, reliability, and dependency surface. The CEO must pick one (or explicitly defer) before implementation begins.

## Recommendation
**Recommended: Option B — systemd service units with `Restart=on-failure`.**

Tradeoffs:

| Option | Description | Pro | Con |
|---|---|---|---|
| A — cron-based poll | A cron job checks for the PID every N minutes and restarts if missing | Zero new dependencies; works anywhere | Up to N-minute gap before restart; cron itself can drift or fail |
| **B — systemd units (recommended)** | Wrap each loop as a systemd service with `Restart=on-failure` and `RestartSec=10` | Instant restart on crash; native on Ubuntu; battle-tested | Requires root or systemd user-session access; ties the loops to the host init system |
| C — supervisord | Run loops under `supervisord` | Portable; per-process logging; dashboard | Additional dependency to install and maintain; overkill if systemd is available |

Option B is the right call because:
1. The host is Ubuntu (systemd is already present and running).
2. It gives zero-latency restart on failure — no polling gap.
3. It also gives structured logging via `journald` at no extra cost.
4. It does not introduce a

---
- Agent: architect-copilot
- Source inbox: /home/ubuntu/forseti.life/sessions/architect-copilot/inbox/20260425-clarify-escalation-20260420-analyze-orchestrator-watchdog
- Generated: 2026-04-25T06:09:30+00:00
