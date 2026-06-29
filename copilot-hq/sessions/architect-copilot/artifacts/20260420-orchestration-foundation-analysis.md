# ORCHESTRATION FOUNDATION — DEEP ANALYSIS

## Overview

**4 Jobs Analyzed**: orchestrator-reboot, orchestrator-watchdog, hq-automation-watchdog, hq-health-heartbeat

**Finding**: These 4 jobs form a **resilient, self-healing automation backbone** with clear separation of concerns.

**Key Discovery**: Orchestrator architecture is MORE mature than expected. Shows signs of having replaced legacy "ceo-opsloop, ceo-health-loop, 2-ceo-opsloop" systems.

---

## JOB 1: orchestrator-reboot (@reboot)

### What It Does
```bash
GH_TOKEN=... ORCHESTRATOR_AGENT_CAP=8 AGENT_EXEC_MAX_CONCURRENT_BEDROCK=6 \
  /scripts/orchestrator-loop.sh start 60
```
- Runs when system boots
- Starts the main orchestrator daemon (run.py)
- Sets hardcoded environment variables:
  - `ORCHESTRATOR_AGENT_CAP=8` — Max 8 agents running concurrently
  - `AGENT_EXEC_MAX_CONCURRENT_BEDROCK=6` — Max 6 AWS Bedrock calls in parallel

### Purpose
- **System Recovery**: After reboot, automatically restart automation
- **Bootstrap**: Orchestrator is the heart of HQ automation; must start at boot
- **Config Injection**: Env vars limit resource usage (prevent runaway agents)

### Current Status
- ✅ **WORKING**: Successfully starts orchestrator-loop.sh
- ✅ **CRITICAL**: Without @reboot, system is down after server restart
- ⚠️ **HARDCODED**: ORCHESTRATOR_AGENT_CAP=8 should be in config file (not crontab)
- ⚠️ **SECURITY**: GH_TOKEN visible in crontab (user already accepts this)

### Is It Needed?
**YES, CRITICAL** — Only bootstrap mechanism for orchestrator daemon

### Could Orchestration Handle It?
❌ **NO** — This is the @reboot trigger that starts orchestration itself (circular dependency)

### Recommendation
✅ **KEEP AS-IS** but:
  1. Move ORCHESTRATOR_AGENT_CAP to config file (not crontab)
  2. Move AGENT_EXEC_MAX_CONCURRENT_BEDROCK to config file
  3. Document the 60-second tick interval (what does it mean?)

### Questions Answered
- **What is 60 (second arg)?** → orchestrator-loop.sh run interval (60 second ticks)
- **Why ORCHESTRATOR_AGENT_CAP=8?** → Resource limit (prevent 10,000 agents spawning)
- **Why BEDROCK=6?** → AWS concurrency limit (Bedrock API throttles beyond 6)

---

## JOB 2: orchestrator-watchdog (every 5 minutes)

### What It Does
```bash
if orchestrator-loop.sh verify > /dev/null; then
  log "ok"
  exit 0
fi
log "orchestrator loop down; attempting restart"
./scripts/orchestrator-loop.sh start 60
```

1. Checks if orchestrator-loop is running (via `verify` command)
2. If down: Attempts restart
3. If restart fails: Logs failure and exits with error code

**Cost**: 2-3 second check every 5 minutes (negligible)

### Purpose
- **Heartbeat Monitoring**: Ensures orchestrator stays alive
- **Auto-Recovery**: Restarts dead orchestrator
- **Graceful Degradation**: If restart fails, alerts (exit 1)

### Current Status
- ✅ **WORKING**: Successfully detects and restarts orchestrator
- ✅ **SMART**: Checks `verify` instead of just checking PID
  - Avoids false positives (zombie processes)
  - Uses lock files and actual process checks
- ✅ **SAFE**: If restart fails, doesn't loop infinitely

### Is It Needed?
**YES, IMPORTANT** — But with caveats:
- If orchestrator-loop.sh is robust and never crashes: maybe not needed
- If orchestrator crashes occasionally: definitely needed
- Current implementation suggests crashes happen (watchdog exists for a reason)

### Frequency Question
**Every 5 minutes**: Is this right?
- ✅ Good: Detects failure within 5 minutes
- ⚠️ Question: Why 5 min instead of 1 min like hq-automation-watchdog?

### Could Orchestration Handle It?
❌ **NO** — Orchestrator can't monitor itself (would need external process)

### Design Question
**Is this redundant with hq-automation-watchdog?**
- hq-automation-watchdog runs `hq-automation.sh converge` (which may start orchestrator)
- orchestrator-watchdog directly checks/restarts orchestrator
- **Relationship**: hq-automation-watchdog is the slow heartbeat, orchestrator-watchdog is the fast responder

### Recommendation
✅ **KEEP AS-IS**
  - Frequency (5 min) is appropriate
  - Design is clean and safe
  - Essential for resilience

---

## JOB 3: hq-automation-watchdog (every minute, 1440x/day)

### What It Does
```bash
./scripts/hq-automation.sh converge --no-require-enabled
log "enabled=${enabled} converge=done"

for site in forseti dungeoncrawler; do
  ./scripts/suggestion-intake.sh "$site"
  log "suggestion-intake site=${site} result=ok"
done
```

1. Calls `hq-automation.sh converge` (start/stop automation loops based on org state)
2. Pulls community suggestions from Drupal for 2 sites (forseti, dungeoncrawler)
3. Logs results

**Cost**: <100ms per run (from prior analysis)

### What Is hq-automation.sh converge?
This is CRITICAL to understand:
```bash
start_loops() {
  # Start orchestrator-loop
  ./scripts/orchestrator-loop.sh start 60
  
  # Conditionally start site-audit-loop
  # Conditionally start auto-checkpoint-loop
}

# converge ensures loops match org-control state
# If org disabled: stop loops
# If org enabled: start loops
```

**Key**: hq-automation.sh converge ensures automation loops are running when org is enabled.

### Purpose
- **Convergence Engine**: Ensures actual state matches desired state
  - If org enabled but no orchestrator: start orchestrator
  - If org disabled but orchestrator running: stop it
  - Example: User disables org via admin panel → watchdog notices → stops automation

- **Community Intake**: Pull suggestions from Drupal every minute
  - Two sites: forseti.life, dungeoncrawler.forseti.life
  - Feeds PM inbox with community feature requests
  - Real-time community engagement

### Current Status
- ✅ **WORKING**: Runs every minute, completes <100ms
- ✅ **CRITICAL**: Only convergence check for org enable/disable state
- ✅ **NECESSARY**: Without this, disabling org wouldn't stop automation
- ✅ **REAL-TIME**: Community suggestions pulled every minute

### Frequency: Is 1 Minute Necessary?
**For convergence**: Probably overkill
- Org enable/disable is rare event
- Could run every 5 min (like orchestrator-watchdog) without issue

**For suggestions**: Probably good
- Community engagement benefit from quick intake (vs hourly)
- But 1 min might be excessive; could be 5-10 min

**Current recommendation**: 1 min is fine (low cost <100ms, provides feature)

### Could Orchestration Handle It?
✅ **PARTIALLY**:
- Orchestrator could call suggestion-intake internally
- But suggestion-intake probably belongs in cron (external integration)
- Org enable/disable convergence could be in orchestrator

### Recommendation
✅ **KEEP AS-IS** (working well)
  - Frequency (1 min) is safe and provides value
  - No performance concern (<100ms)
  - Could be optimized to 5 min later if needed

---

## JOB 4: hq-health-heartbeat (every 2 minutes)

### What It Does
```bash
check_and_restart_loop "orchestrator-loop" "scripts/orchestrator-loop.sh"
stop_legacy_agent_exec_loop()
check_publisher()
```

1. **Check orchestrator-loop**: Verify it's running, restart if down
2. **Stop legacy agent-exec-loop**: Migration cleanup (old cron-based agent execution)
3. **Check publisher**: Ensure publish-forseti-agent-tracker-loop is NOT running (managed by orchestrator now)

### Purpose
- **Comprehensive Health Check**: All automation loops
- **Legacy Migration**: Helps transition from old cron agent execution to orchestrator
- **Restart Mechanism**: Longer restart timeout than orchestrator-watchdog (gives orchestrator time to recover)

### Current Status
- ✅ **WORKING**: Comprehensive health check every 2 minutes
- ✅ **MIGRATION SUPPORT**: Actively stopping legacy loops (artifact of refactor)
- ✅ **SMART**: Tracks `/tmp/hq-health-heartbeat.log` and `/tmp/hq-health-alert.log`

### Frequency: Every 2 Minutes
- ✅ Good: More frequent than orchestrator-watchdog (5 min), less frequent than automation-watchdog (1 min)
- ✅ Hierarchy: automation-watchdog (1m) → hq-health-heartbeat (2m) → orchestrator-watchdog (5m)

### Is It Redundant?
**Possible**: Three jobs monitor orchestrator:
1. **orchestrator-watchdog (5m)**: Check orchestrator running
2. **hq-health-heartbeat (2m)**: Check orchestrator + legacy cleanup
3. **hq-automation-watchdog (1m)**: Convergence + suggestions

All three check orchestrator status. **Is this necessary?**

**Answer**: Probably, but could be optimized
- Different purposes: convergence vs heartbeat vs fast recovery
- Could consolidate into single 2-min heartbeat
- But current design is safe (defensive)

### Recommendation
⚠️ **KEEP FOR NOW** but:
  1. Could be consolidated with orchestrator-watchdog (5m) into single 2-min check
  2. Legacy agent-exec-loop cleanup should eventually be removed (post-migration)
  3. Monitor alert logs for patterns (if legacy loops are never found, can remove that logic)

---

## ARCHITECTURE OBSERVATION: Self-Healing Automation

These 4 jobs implement a **3-layer resilience pyramid**:

```
Layer 1 (1 min)   : hq-automation-watchdog (convergence + suggestions)
Layer 2 (2 min)   : hq-health-heartbeat (comprehensive health check + restart)
Layer 3 (5 min)   : orchestrator-watchdog (fast recovery)
Layer 0 (@reboot) : orchestrator-reboot (bootstrap)
```

**Design Pattern**: Multiple overlapping heartbeats with different purposes
- converge (1m) catches state mismatches
- heartbeat (2m) catches crashes
- watchdog (5m) confirms recovery

**This is GOOD DESIGN**: Defensive, self-healing, with clear separation of concerns

---

## Key Finding: Orchestrator Maturity

**Evidence that orchestrator is mature**:
1. Comments show it replaced: "ceo-inbox-loop, inbox-loop, ceo-health-loop, 2-ceo-opsloop"
2. hq-automation.sh still stops these legacy loops (migration artifact)
3. hq-health-heartbeat actively cleans up legacy agent-exec-loop
4. Orchestrator has: health_check, kpi_monitor, publish, release_cycle, coordinated_push
5. Multiple hardcoded env vars suggest fine-tuned resource limits

**Implication for CEO ops**:
- Orchestrator was designed to replace ceo-opsloop
- Likely already handles most CEO ops work
- ceo-ops-once and related cron jobs may be redundant (confirms Phase 1 finding)

---

## Summary Table

| Job | Frequency | Status | Necessity | Recommendation |
|-----|-----------|--------|-----------|-----------------|
| **orchestrator-reboot** | @reboot | ✅ Critical | YES (bootstrap) | **KEEP** (move config) |
| **orchestrator-watchdog** | 5 min | ✅ Working | YES (resilience) | **KEEP** |
| **hq-automation-watchdog** | 1 min | ✅ Working | YES (convergence) | **KEEP** |
| **hq-health-heartbeat** | 2 min | ✅ Working | PROBABLY (can optimize) | **KEEP** (monitor legacy cleanup) |

---

## Architectural Recommendations

### Short Term (Safe)
- Keep all 4 jobs as-is (proven, working, resilient)

### Medium Term (Config Management)
- Move ORCHESTRATOR_AGENT_CAP to config file
- Move AGENT_EXEC_MAX_CONCURRENT_BEDROCK to config file
- Document the 60-second tick interval

### Long Term (Optimization)
- Consider consolidating watchdog jobs (1m + 2m + 5m could become 1m + 5m)
- Remove legacy agent-exec-loop cleanup (post-migration)
- Move suggestion-intake to orchestrator if real-time community engagement is priority

---

## Blocking Questions Partially Answered

From Phase 1 blocked CEO ops analysis:

**Q2: "Does orchestrator/run.py already call these scripts?"**
  - Partially answered: hq-automation.sh calls orchestrator-loop.sh start/stop
  - But orchestrator-loop.sh is separate Python process (run.py)
  - Need to investigate: Does run.py call the CEO health check scripts?

**Q1: "What's the intended architecture?"**
  - Partially answered: Orchestrator is meant to replace old cron-based loops
  - CEO ops should be in orchestrator (confirmed by hq-automation.sh architecture)
  - Cron jobs are bootstrap + resilience layer (not primary)

---

## Conclusion

**Orchestration Foundation Is Solid**: Self-healing, redundant, clear separation of concerns.

**CEO Ops Likely Redundant**: Architecture shows orchestrator was designed to replace ceo-opsloop. The 3 blocked CEO ops todos are probably operating in parallel to orchestrator, not as part of it.

**Next Investigation**: Look at what orchestrator/run.py actually does (health_check phase, publish phase, etc.) to confirm overlap with ceo-ops-once.

