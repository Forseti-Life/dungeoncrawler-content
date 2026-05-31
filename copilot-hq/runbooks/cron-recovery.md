# Cron Recovery Runbook

## Purpose
Restore HQ orchestration after a cron table wipe or environment migration.

A cron wipe causes all orchestration loops to stop silently. Without intervention, no agents execute, no checkpoints fire, and the org stalls indefinitely.

## Detecting a cron wipe

### Symptom 1: No recent agent exec log entries
```bash
tail -20 /home/ubuntu/forseti.life/copilot-hq/inbox/responses/agent-exec-cron.log
# If the last entry is >15 minutes old, the exec loop may be down.
```

### Symptom 2: Loop verify fails
```bash
bash /home/ubuntu/forseti.life/copilot-hq/scripts/orchestrator-loop.sh verify
bash /home/ubuntu/forseti.life/copilot-hq/scripts/agent-exec-loop.sh verify
# Both should print "ok (running pid ...)"
```

### Symptom 3: Crontab missing HQ entries
```bash
crontab -l | grep copilot-sessions-hq
# Should return 8 lines. If 0 or fewer — the cron table was wiped.
```

### Symptom 4: Alert log exists
```bash
cat /tmp/hq-health-alert.log
# WARN entries indicate the heartbeat detected a downed loop.
```

## Recovery: restore full cron state

Run the idempotent installer (safe to run multiple times):

```bash
bash /home/ubuntu/forseti.life/copilot-hq/scripts/install-crons.sh
```

This installs all 8 required HQ cron entries without duplicating existing ones.

## Recovery: verify all loops are healthy

```bash
bash /home/ubuntu/forseti.life/copilot-hq/scripts/hq-health-heartbeat.sh
# Exit 0 = all loops healthy or successfully restarted
# Exit 1 = one or more loops failed to restart (see /tmp/hq-health-alert.log)
```

You can also check loop status directly:
```bash
bash /home/ubuntu/forseti.life/copilot-hq/scripts/orchestrator-loop.sh status
bash /home/ubuntu/forseti.life/copilot-hq/scripts/agent-exec-loop.sh status
```

## Recovery: manual loop restart (if heartbeat restart failed)

```bash
cd /home/ubuntu/forseti.life/copilot-hq
ORCHESTRATOR_AGENT_CAP=6 bash scripts/orchestrator-loop.sh start 60
bash scripts/agent-exec-loop.sh start 60
```

## Required HQ cron entries (reference)

These are the 8 entries managed by `scripts/install-crons.sh`:

| Tag | Schedule | Purpose |
|---|---|---|
| `orchestrator-reboot` | `@reboot` | Start orchestrator loop on boot |
| `orchestrator-watchdog` | `*/5 * * * *` | Restart orchestrator if down |
| `agent-exec-reboot` | `@reboot` | Start agent exec loop on boot |
| `agent-exec-watchdog` | `*/5 * * * *` | Restart agent exec loop if down |
| `hq-automation` | `* * * * *` | Converge HQ automation state |
| `ceo-ops` | `0 */2 * * *` | CEO scheduled quality check |
| `auto-checkpoint` | `0 */2 * * *` | Auto-checkpoint every 2 hours |
| `hq-health-heartbeat` | `*/2 * * * *` | Self-healing heartbeat + alert log |

## Post-migration checklist

After any environment migration (new server, home dir rename, etc.):

- [ ] Run `bash scripts/install-crons.sh`
- [ ] Confirm `crontab -l | grep copilot-sessions-hq` shows 8 entries
- [ ] Run `bash scripts/hq-health-heartbeat.sh` → exit 0
- [ ] Check `tail -5 /tmp/hq-health-heartbeat.log` for "all loops healthy"
- [ ] Update `site.instructions.md` for infrastructure if repo root path changed

## Root cause: GAP-CRON-RESILIENCE-01

During the 20260322-dungeoncrawler-release-next cycle, the cron table was wiped after an environment migration from `/home/keithaumiller` to `/home/ubuntu`. All orchestration loops stopped silently for ~3 days, causing 20 SLA breaches. No alert fired. Discovery was manual.

Mitigations applied (2026-04-05):
- `scripts/hq-health-heartbeat.sh` — auto-restarts loops and writes alert log
- `scripts/install-crons.sh` — idempotent single-command cron restoration
- This runbook — detection + restore + verify procedure
