# HQ Health Heartbeat Logic — Deep Dive

**Script**: `scripts/hq-health-heartbeat.sh`  
**Schedule**: Every 2 minutes (`*/2 * * * *`)  
**Purpose**: Verify orchestrator is running; auto-restart if dead  
**Part of**: 4-layer self-healing resilience architecture

---

## Overview

The health heartbeat is the **core health monitoring mechanism** for the entire automation system. Every 2 minutes, it performs three critical checks:

1. **Verify orchestrator is running** (primary check)
2. **Kill any legacy agent-exec-loop processes** (migration cleanup)
3. **Remove any stray publisher loop** (consolidation)

If orchestrator dies, heartbeat restarts it within ~2 minutes.

---

## Three-Part Logic

### 1️⃣ PRIMARY: CHECK & RESTART ORCHESTRATOR-LOOP

**Code** (line 113):
```bash
check_and_restart_loop "orchestrator-loop" "scripts/orchestrator-loop.sh" "start 60"
```

**Logic Flow**:
```
├─ Call: ./scripts/orchestrator-loop.sh verify
│
├─ IF running:
│  └─ Log "ok: orchestrator-loop"
│     Return 0 ✅
│
└─ IF NOT running:
   ├─ Alert: "orchestrator-loop is DOWN — attempting restart"
   ├─ Execute: ./scripts/orchestrator-loop.sh start 60
   ├─ Sleep 1 second
   ├─ Call: ./scripts/orchestrator-loop.sh verify
   │
   ├─ IF now running:
   │  └─ Log "restarted: orchestrator-loop"
   │     Return 0 ✅
   │
   └─ IF still not running:
      └─ Alert: "orchestrator-loop restart FAILED"
         Return 1 ❌ (manual intervention required)
```

**Why This Matters**:
- Orchestrator is the **automation engine** — executes all agents, dispatches tasks, drives release cycles
- Without it, nothing happens (no agents run, no tasks complete, system stalls)
- This watchdog ensures it never stays dead >2 minutes
- Restarts with `start 60` = 60-second startup timeout

**Exit Codes**:
- `0` = orchestrator is healthy OR was restarted successfully
- `1` = orchestrator died AND restart failed (needs human help)

---

### 2️⃣ SECONDARY: CLEAN UP LEGACY AGENT-EXEC-LOOP

**Code** (line 114):
```bash
stop_legacy_agent_exec_loop || any_failed=1
```

**Logic Flow**:
```
├─ Check if old agent-exec-loop.sh is running
│  ├─ Via: ./scripts/agent-exec-loop.sh verify
│  └─ Via: ps search for "/scripts/agent-exec-loop\.sh run"
│
├─ IF NOT found:
│  └─ Return 0 ✅ (healthy, nothing to do)
│
└─ IF found:
   ├─ Alert: "legacy agent-exec-loop is running"
   ├─ Attempt graceful stop: ./scripts/agent-exec-loop.sh stop
   ├─ Sleep 1 second
   │
   ├─ IF still running:
   │  └─ Send SIGTERM to all matching PIDs
   │     Sleep 0.2 seconds
   │
   ├─ IF still running:
   │  └─ Send SIGKILL (-9) to all PIDs
   │     Sleep 1 second
   │
   ├─ IF successfully stopped:
   │  └─ Log "stopped: legacy agent-exec-loop"
   │     Return 0 ✅
   │
   └─ IF won't die:
      └─ Alert: "legacy agent-exec-loop stop FAILED"
         Return 1 ❌ (manual intervention required)
```

**Why This Matters**:
- **Migration artifact**: Old `agent-exec-loop.sh` predates the orchestrator
- **Duplicate execution bug**: If both loops run simultaneously, agents execute twice
- **Goal**: Safely phase out old loop as orchestrator takes over
- **Non-destructive**: Sends graceful stop first, only force-kills if needed

**Context**:
- `hq-automation.sh` explicitly stops legacy loops when org is enabled
- Health heartbeat ensures they don't restart or linger

---

### 3️⃣ TERTIARY: CLEAN UP PUBLISHER LOOP

**Code** (line 115):
```bash
check_publisher || true
```

**Logic Flow**:
```
├─ Check for PID file: ./.publish-forseti-agent-tracker-loop.pid
│
├─ IF no file:
│  └─ Log "ok: publisher (managed by orchestrator tick)"
│     Return 0 ✅ (healthy, orchestrator manages it)
│
└─ IF file exists:
   ├─ Read PID from file
   │
   ├─ IF PID is valid AND process running:
   │  ├─ Alert: "publisher loop still running — stopping redundant loop"
   │  ├─ Send stop: ./scripts/publish-forseti-agent-tracker-loop.sh stop
   │  ├─ Sleep 1 second
   │  │
   │  ├─ IF still running:
   │  │  └─ Send SIGTERM
   │  │     Sleep 0.2 seconds
   │  │
   │  └─ IF still running:
   │     └─ Send SIGKILL (-9)
   │
   ├─ Delete PID file
   ├─ Log "ok: publisher (managed by orchestrator tick)"
   └─ Return 0 ✅ (always succeeds due to || true)
```

**Why This Matters**:
- **Consolidation**: Publisher loop publishes Job Hunter results to Agent Tracker
- **Orchestrator has it**: Orchestrator includes a dedicated "publish" phase
- **Remove redundancy**: Old separate loop is unnecessary, remove if found
- **Non-fatal**: `|| true` means this check never causes exit code 1

**Note**: Publisher cleanup is important but non-critical (unlike orchestrator restart).

---

## Final Decision Logic

**Code** (lines 117-123):
```bash
if [ "$any_failed" -eq 0 ]; then
  log "heartbeat: all loops healthy"
  exit 0
else
  alert "heartbeat: one or more loops could not be restarted — see ${ALERT_LOG}"
  exit 1
fi
```

**Summary**:
- If all three checks succeeded (or were non-critical) → **Exit 0** (all good)
- If orchestrator or legacy loop failed → **Exit 1** (alert user, needs help)

---

## Example Logs

### Scenario 1: All Healthy (Normal Case)
```
[2026-04-20T15:30:45+00:00] ok: orchestrator-loop
[2026-04-20T15:30:45+00:00] legacy agent-exec-loop is not running
[2026-04-20T15:30:45+00:00] ok: publisher (managed by orchestrator tick)
[2026-04-20T15:30:45+00:00] heartbeat: all loops healthy
EXIT 0
```

### Scenario 2: Orchestrator Died & Restarted
```
[2026-04-20T15:30:45+00:00] WARN orchestrator-loop is DOWN — attempting restart
[2026-04-20T15:30:46+00:00] restarted: orchestrator-loop
[2026-04-20T15:30:46+00:00] legacy agent-exec-loop is not running
[2026-04-20T15:30:46+00:00] ok: publisher (managed by orchestrator tick)
[2026-04-20T15:30:46+00:00] heartbeat: all loops healthy
EXIT 0
```

### Scenario 3: Orchestrator Won't Restart (Failure)
```
[2026-04-20T15:30:45+00:00] WARN orchestrator-loop is DOWN — attempting restart
[2026-04-20T15:30:46+00:00] WARN orchestrator-loop restart FAILED — manual intervention required
[2026-04-20T15:30:46+00:00] heartbeat: one or more loops could not be restarted — see /tmp/hq-health-alert.log
EXIT 1 ← ALERT to user
```

### Scenario 4: Legacy Loop Still Running
```
[2026-04-20T15:30:45+00:00] ok: orchestrator-loop
[2026-04-20T15:30:45+00:00] WARN legacy agent-exec-loop is running — stopping it to avoid duplicate agent execution
[2026-04-20T15:30:45+00:00] legacy agent-exec-loop stop FAILED — manual intervention required
[2026-04-20T15:30:45+00:00] heartbeat: one or more loops could not be restarted — see /tmp/hq-health-alert.log
EXIT 1 ← ALERT to user
```

---

## Key Properties

### ✅ What It Guarantees

1. **Orchestrator uptime**: Never stays dead >2 minutes (detects + restarts within 2-minute cycle)
2. **No duplicate execution**: Kills any legacy agent-exec-loop (prevents agents running twice)
3. **Centralized publishing**: Removes stray publisher loops (consolidates to orchestrator)
4. **Self-healing**: Automatic recovery without human intervention (unless restart fails)
5. **Clear failure modes**: Exit code 1 signals when manual intervention is needed

### ⏲️ Timing Characteristics

| Metric | Value |
|--------|-------|
| **Check frequency** | Every 2 minutes |
| **Restart latency** | ~1-2 seconds (start + verify) |
| **Max downtime** | ~2 minutes (if crash at T+0, detected/restarted by T+2) |
| **Log overhead** | ~100 bytes per heartbeat (minimal) |

### 🚨 Failure Modes

| Failure | Detection | Alert | Action |
|---------|-----------|-------|--------|
| Orchestrator dies | verify check | WARN | restart it |
| Restart fails | verify check | WARN | exit 1, alert user |
| Legacy loop won't die | verify check | WARN | exit 1, alert user |
| Publisher cleanup fails | (ignored) | none | log only, continue |

---

## Architecture Context

Health heartbeat is **Layer 2** of a 4-layer self-healing resilience pyramid:

```
Layer 3 (every 5 min):   orchestrator-watchdog.sh
  └─ Detects stuck/hung processes, kills + restarts

Layer 2 (every 2 min):   hq-health-heartbeat.sh ← YOU ARE HERE
  └─ Detects dead orchestrator, restarts it, cleans up legacy loops

Layer 1 (every 1 min):   hq-automation-watchdog.sh
  └─ Convergence engine (ensures automation state matches config)

Layer 0 (@reboot):       orchestrator-reboot (in crontab @reboot)
  └─ Bootstrap orchestrator on server restart
```

**Design principle**: Multiple overlapping health checks at different time scales ensure:
- Quick detection (2-min heartbeat)
- Process health (5-min watchdog)
- State consistency (1-min automation watchdog)
- Server recovery (bootstrap at reboot)

---

## Related Scripts

- **`scripts/orchestrator-loop.sh`**: Main orchestrator loop (starts orchestrator/run.py)
- **`scripts/orchestrator-watchdog.sh`**: 5-min watchdog (handles stuck processes)
- **`scripts/hq-automation-watchdog.sh`**: 1-min convergence engine
- **`orchestrator/run.py`**: Core orchestration engine (multi-phase tick loop)

---

## Conclusion

**hq-health-heartbeat.sh** is the critical health check for the entire automation system. Its simple but powerful logic ensures:

1. The orchestrator (brain of automation) never stays dead >2 minutes
2. Legacy code doesn't interfere with current architecture
3. Failures are immediately visible (exit code 1 = alert)

**Key insight**: Rather than one complex health check, heartbeat performs three focused checks:
- ✅ Primary: Is orchestrator running? (critical)
- ✅ Secondary: Are legacy loops gone? (important)
- ✅ Tertiary: Is publisher centralized? (nice-to-have)

This keeps the logic simple, readable, and maintainable.

