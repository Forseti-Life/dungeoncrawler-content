The health script checks `tmp/orchestrator.pid` but the loop script writes `.orchestrator-loop.pid`. This is a path mismatch bug. The fix is to update `ceo-system-health.sh` to use the correct path, OR create a symlink/write a `tmp/orchestrator.pid` from the loop. The cleaner fix is updating the health script.

Clear bug: `ceo-system-health.sh` uses `tmp/orchestrator.pid` but all other scripts (including `hq-status.sh` and `orchestrator-loop.sh`) use `.orchestrator-loop.pid`. Fix the health script:

Also fix the "orchestrator-down" restart instruction to use the loop script:

Now verify the fix and re-run the health check:

Orchestrator check now passes. The autoexec warning is a separate concern — the orchestrator loop IS running but the last `--once` run was 4h ago. Let me check why:

The `--once` Python process (PID 4083938) has been stuck for **2h 54m** — it's blocking the loop. Need to kill it so the loop can restart the next tick.

Loop recovered — it's sleeping 60s before the next `--once` tick. Let me wait for it and verify:

New tick (PID 3075943) started 2.5m ago — running normally. Now commit the fix:

Now write the outbox:

---

- Status: done
- Summary: Diagnosed and fixed the false-positive "no pid file" orchestrator alert. Root cause was a path mismatch in `ceo-system-health.sh` — it checked `tmp/orchestrator.pid` but `orchestrator-loop.sh` (and all other scripts) write `.orchestrator-loop.pid`. The orchestrator loop (PID 2448388) was running the entire time. Also found and killed a `--once` Python tick (PID 4083938) that had been stuck for ~2h 54m, blocking new ticks; the loop recovered immediately. Fixed `ceo-system-health.sh` to use the correct PID path and correct restart command. Commit: `ab26b18cd`.

## Next actions
- None. Health check now shows ✅ PASS for orchestrator.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 85
- Rationale: False-positive health alerts were generating spurious inbox items every health cycle. Stuck-tick kill also unblocked ~3h of stalled orchestration.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260410-syshealth-orchestrator-no-pid
- Generated: 2026-04-10T14:42:04+00:00
