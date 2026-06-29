# Inbox Mode (Fire-and-forget commands)

Yes: treat this repo as an **inbox system**.

## How it works
1) You drop a command into `inbox/commands/` (no waiting).
2) CEO processes the inbox and converts each command into a PM work request.
3) PM/Dev/QA proceed via their normal `sessions/<agent>/inbox|outbox|artifacts` workflow.

## Creating a command item
Preferred: use the helper script:

```bash
./scripts/inbox-new-command.sh <pm-agent-id> <work-item-id> <short-topic> "<command text>"
```

Alternatively: create a markdown file manually under `inbox/commands/`.

## Processing the inbox
```bash
./scripts/inbox-process.sh
```

This will:
- create a PM inbox request under `sessions/<pm-agent-id>/inbox/...`
- move the original command into `inbox/processed/`

## Notes
- This is designed to be fast: you can queue multiple items, then process in batches.
- For cross-module work, PM should use passthrough.

## CEO single-command workflow (recommended)
Queue a command without worrying about PM ids:

```bash
./scripts/ceo-queue.sh <work-item-id> <short-topic> "<command text>"
```

Then dispatch everything queued:

```bash
./scripts/ceo-dispatch.sh
```

## Continuous CEO loop (no waiting)
Start the CEO loop (processes commands in order):

```bash
./scripts/ceo-inbox-loop.sh start 2
```

Watch responses:

```bash
./scripts/ceo-watch.sh
```

Stop the loop:
- Send SIGTERM to the numeric PID in `.ceo-inbox-loop.pid`.

## CEO queue loop (fast command entry)
If you want to rapidly enqueue many CEO commands without retyping `ceo-queue.sh` each time:

```bash
./scripts/ceo-queue-loop.sh <work-item-id> <topic>
```

Then type one command per line; each line is enqueued via `ceo-queue.sh`.

## Interactive monitoring
Live status dashboard:

```bash
./scripts/hq-watch.sh 2
```

Interactive shell:

```bash
./scripts/hq-interactive.sh
```

## CEO health checks (every 5 minutes)
Start the CEO health loop:

```bash
./scripts/ceo-health-loop.sh start 300
```

Watch health output:

```bash
./scripts/ceo-health-watch.sh
```

## Agent execution loop (actually moves work)
The recommended unattended runtime is the LangGraph orchestrator (systemd user service).

Install/start:

```bash
./scripts/install-systemd-orchestrator.sh
```

Status/logs:

```bash
systemctl --user status copilot-sessions-hq-orchestrator.service
journalctl --user -u copilot-sessions-hq-orchestrator.service -n 200 --no-pager
```

Legacy (do not run alongside orchestrator):

```bash
./scripts/agent-exec-loop.sh start 60
./scripts/agent-exec-watch.sh
```

Details: see `runbooks/orchestration.md`.

## Blocked/needs-info flow (CEO responsibility)
Agents must mark their latest outbox with one of:
- `- Status: blocked`
- `- Status: needs-info`

When blocked, they must fill:
- `## Blockers`
- `## Needs from CEO`

CEO monitoring:
- `hq-status.sh` now includes a global `Blocked:` count.
- `ceo-health-loop` includes a "BLOCKED items detected" section.
- Block events are written to:
  - `inbox/responses/blocked-latest.log`
  - `inbox/responses/blocked-YYYYMMDD.log`

Behavior:
- If an agent is blocked on an item, it is still archived and the agent moves to the next inbox item automatically.
- If blocked items persist, CEO health loop will auto-queue an unblock request (cooldown 1 hour).

### Up-chain communication (required)
If an agent is `blocked` or `needs-info`, they must communicate up-chain in writing:
- Fill `## Needs from CEO` with the exact missing info/resources/clarifications.
- Automation will also create a CEO inbox item under the active CEO seat (`sessions/ceo-copilot*/inbox/`) so the CEO sees and can unblock quickly.

## Forseti (CEO) interactive improvement
Start the CEO interactive shell:

```bash
./scripts/forseti.sh
```

### Supervisor escalation
Blocked/needs-info items are escalated to the agent's supervisor (Dev/QA -> PM, PM -> CEO).
