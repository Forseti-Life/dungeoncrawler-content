# BOARD-DAILY-REMINDER & NOTIFY-PENDING — DUAL ANALYSIS

## BOARD-DAILY-REMINDER (daily at 08:00 UTC)

### What It Does
- Scans `inbox/commands/` for pending .md files (CEO decisions awaiting Board input)
- Extracts topic + created_at from YAML
- Sends email to Keith at keith.aumiller@stlouisintegration.com if items found
- Hardcoded sendmail; runs daily at 08:00 UTC

### Purpose
- **Push Notification**: Keith gets email reminder of pending decisions
- **Non-Blocking**: Doesn't prevent anything; just reminds
- **Daily Digest**: Fires once daily while items pending

### Current Status
- ✅ Working (email sends successfully)
- ✅ Frequency appropriate (daily for human notification)
- ⚠️ Hardcoded email (should be in board.conf)

### Critical Question: Does Orchestrator Have Equivalent?
Orchestrator routes inbox/commands to PM/CEO inboxes, but unclear if:
- Orchestrator triggers email notifications?
- Orchestrator has daily digest logic?
- Or is this cron job the notification mechanism?

### Necessity
**CONDITIONAL YES** — If no other notification system exists
**CONDITIONAL NO** — If orchestrator will handle this

### Recommendation
🟡 **KEEP FOR NOW** (low cost, provides known daily reminder)
- Flag for consolidation when orchestrator notification story is clear
- Move email to board.conf to remove hardcoding
- Otherwise working as designed

---

## NOTIFY-PENDING (every 10 minutes)

### What It Does
- Monitors "pending decision items" (source unclear)
- Sends email/SMS alerts to CEO about pending decisions
- 4-hour cooldown (won't spam if same item is pending)
- Configurable: NOTIFY_METHOD (log|sendmail|twilio), NOTIFY_COOLDOWN_SECONDS
- Routes to active CEO agent (ceo-copilot-*) dynamically

### Purpose
- **Real-Time Escalation**: CEO notified quickly of critical decisions
- **10-Minute Heartbeat**: Checks frequently
- **4-Hour Cooldown**: Doesn't spam if same item stays pending
- **Smart Routing**: Finds active CEO agent dynamically

### Current Status
- ✅ Working (runs every 10 min with cooldown)
- ✅ Intelligent (routes to active CEO agent)
- ❓ Unknown: What is "pending decision items" source?

### Critical Unknowns
1. **Decision Tracking Source**: Where are "pending decisions" stored?
   - Is it a database table? File? API?
   - How are decisions created/marked?
   - Who creates them?

2. **Frequency Justification**: 10-minute checks with 4-hour cooldown seems overly conservative
   - Why not event-driven (notify when decision is created)?
   - Why 10 minutes if cooldown is 4 hours?

3. **Orchestrator Integration**: Does orchestrator have equivalent?
   - Orchestrator has escalation matrix
   - Does orchestrator trigger notifications?
   - Or is this cron job the only notification?

### Necessity
**YES, IF:**
- Time-sensitive decisions need rapid CEO escalation
- Event-driven not possible (system limitation)
- No other notification mechanism exists

**NO, IF:**
- Orchestrator already handles escalation
- All decisions are non-critical
- CEO agent monitors its own inbox continuously

### Risk of Removing Without Understanding
- Critical escalations might be missed
- Teams depending on this notification might fail
- No fallback if orchestrator fails

### Recommendation
🟡 **KEEP FOR NOW** (safety net for critical escalation)
- ⚠️ INVESTIGATE: What is the decision tracking source?
- ⚠️ INVESTIGATE: Does orchestrator have equivalent?
- ⚠️ OPTIMIZE: Frequency might be tunable (10 min vs event-driven)
- FLAG: For future consolidation/optimization

---

## SUMMARY: CEO NOTIFICATION JOBS

| Job | Purpose | Status | Necessity | Recommendation |
|-----|---------|--------|-----------|-----------------|
| auto-checkpoint | Git persist | ✅ Critical | YES | **KEEP** |
| ceo-ops-once | Health check | ✅ Working | DEPENDS on orchestrator | **KEEP** (pending decision) |
| board-daily-reminder | Daily digest | ✅ Working | DEPENDS on orchestrator notifications | **KEEP** (low cost) |
| notify-pending | Escalation alert | ✅ Working | DEPENDS on decision tracking source | **KEEP** (safety net) |

---

## KEY ARCHITECTURAL QUESTION

**Should CEO operations be cron-based or orchestration-driven?**

### Current Model (Distributed Cron)
- 4 cron jobs handle different aspects
- CEO agent runs separately in orchestrator loop
- Decoupled, testable, independent

### Proposed Model (Unified Orchestration)
- Single orchestrator loop handles all CEO ops
- Cron jobs retired
- Single point of truth but single point of failure

### Recommendation: HYBRID (Recommended)
- **auto-checkpoint**: Keep in cron (git operations)
- **ceo-ops-once**: Integrate into orchestrator health_check phase
- **board-daily-reminder**: Trigger from orchestrator when commands created
- **notify-pending**: Event-driven from orchestrator escalation matrix
- Cron acts as fallback, orchestrator as primary

---

## BLOCKING QUESTIONS FOR USER

Before retiring any CEO cron jobs:

1. "What's the intended architecture? Cron-based or orchestration-driven?"
2. "Does orchestrator already call hq-status.sh, ceo-system-health.sh, etc?"
3. "What's the 'pending decision' source that notify-pending monitors?"
4. "If we remove ceo-ops-once, where will teams get dispatch notifications?"
5. "If we remove notify-pending, how will critical escalations be handled?"

**Pending these answers, keep all 4 CEO cron jobs running.**

