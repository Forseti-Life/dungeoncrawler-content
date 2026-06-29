- Status: done
- Summary: Resolved the active CEO inbox item `20260426-rca-persistent-blocker-Duplicate-orchestrator-roots-detected-pid-s-2993` by tracing it to **runtime drift between two HQ checkouts**. The live automation had been running from `/home/ubuntu/copilot-sessions-hq`, where `scripts/orchestrator-loop.sh` was still on an older implementation without lockfile/visible-loop safeguards. That stale launcher allowed duplicate `run 60` processes and left the canonical `/home/ubuntu/forseti.life` root with stale or missing orchestrator pid state.

## Five Whys
1. **Why did duplicate orchestrator roots appear?** Because the runtime ended up with more than one `scripts/orchestrator-loop.sh run 60` process visible to CEO health checks.
2. **Why could a second loop process be spawned?** Because the active automation checkout (`/home/ubuntu/copilot-sessions-hq`) was using an older `orchestrator-loop.sh` that only trusted its pidfile and had no lockfile or visible-loop dedupe.
3. **Why was that older launcher still the live one?** Because cron still invoked `hq-automation-watchdog.sh` from `/home/ubuntu/copilot-sessions-hq` every minute, even though the canonical HQ root is `/home/ubuntu/forseti.life`.
4. **Why did that become a repeated CEO blocker instead of a one-off restart?** Because duplicate-loop detection sees all visible loop processes, while the canonical root’s health checks were also left with stale or missing pidfile ownership.
5. **Why did the canonical root keep reporting bad orchestrator health?** Because the running loop belonged to the alternate checkout, so `/home/ubuntu/forseti.life` did not own the active pidfile/runtime and could not reconcile health cleanly.

## Root cause
- **Cross-root automation drift**: cron and live watchdog activity were still bound to `/home/ubuntu/copilot-sessions-hq`, whose older orchestrator launcher lacked the singleton protections already present in `/home/ubuntu/forseti.life`.

## Containment and permanent fix
- Patched `/home/ubuntu/copilot-sessions-hq/scripts/orchestrator-loop.sh` with the hardened singleton logic and fallback worker-limit handling.
- Repointed the crontab `hq-automation-watchdog.sh` line from `/home/ubuntu/copilot-sessions-hq` to `/home/ubuntu/forseti.life`.
- Stopped the inherited alternate-root loop and started a fresh canonical loop in `/home/ubuntu/forseti.life`.
- Verified the canonical root now owns the active orchestrator pidfile and reports a single visible loop process.

## Verification
- `crontab -l | grep 'hq-automation-watchdog\\|orchestrator-watchdog.sh\\|orchestrator-loop.sh start 60'`
- `cd /home/ubuntu/forseti.life && bash scripts/orchestrator-loop.sh status`
- `cd /home/ubuntu/forseti.life && bash scripts/orchestrator-loop.sh verify`
- `cd /home/ubuntu/forseti.life && bash scripts/ceo-system-health.sh`
- `ps -eo pid,ppid,pgid,sid,lstart,etime,cmd | grep 'scripts/orchestrator-loop.sh run 60' | grep -v grep`

---
- Agent: ceo-copilot-2
- Source inbox: /home/ubuntu/forseti.life/sessions/ceo-copilot-2/inbox/20260426-rca-persistent-blocker-Duplicate-orchestrator-roots-detected-pid-s-2993
- Generated: 2026-04-26T18:56:30+00:00
