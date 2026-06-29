# CEO OPERATIONS CRON JOBS — DEEP ANALYSIS

## Context
User flagged that "CEO Operations should be getting handled by the CEO agent via the orchestration loop."
This analysis verifies if the 4 cron jobs are necessary or redundant.

---

## JOB 1: auto-checkpoint (every 2 hours)

### What It Does
- Monitors 2 git repos: `/home/ubuntu/forseti.life` and `/home/ubuntu/forseti.life/copilot-hq`
- Auto-commits & pushes changes to GitHub
- Has comprehensive safety checks:
  - Won't commit if index.lock exists (git conflict)
  - Won't commit secrets (denylist: settings.php, services.yml, .env, .pem/.key files)
  - Won't commit >5000 changes at once
  - Won't commit volatile pointers (sessions/.../artifacts/.../latest)
- Uses flock to prevent concurrent runs
- Only commits if `git status` shows changes

### Purpose in Ecosystem
1. **Session State Persistence**: Copilot agents write artifacts to `sessions/<agent-id>/artifacts/` 
2. **Audit Trail**: Changes are tracked in git history with timestamps
3. **Recovery**: If agent crashes, state is persisted to GitHub
4. **Board Access**: Keith (Board) can review agent work via GitHub commits

### Current Status
- ✅ **WORKING**: Every 2h, pushes /home/ubuntu/forseti.life successfully
- ⚠️ **ISSUE**: copilot-hq repo shows "SKIP (not a git repo)" — this is actually correct since it's a symlink
- ✅ **SAFE**: All safety checks are well-designed

### Is It Needed?
**YES, CRITICAL**
- No other mechanism persists session state to GitHub
- Orchestrator has no git push logic
- CEO agent depends on this for audit trail
- Board (Keith) depends on this for visibility

### Could Orchestration Handle It?
❌ **NO** — Orchestrator is a Python process; git operations are properly delegated to bash.
Even if orchestrator could do it, cron-based auto-push is the right pattern (decoupled, testable, safe).

### Recommendations
1. ✅ Keep as-is (working correctly)
2. Optional: Make REPOS and frequency config-driven instead of hardcoded
3. Optional: Monitor git index.lock failures; alert if they become frequent

---

## JOB 2: ceo-ops-once (every 2 hours)

### What It Does
Runs a comprehensive health check suite:
1. **Priority Rankings** - Reads org-chart/priorities.yaml
2. **HQ Status** - Git merge conflicts, branch health
3. **CEO Inbox Scan** - Lists pending items in sessions/ceo-copilot-2/inbox/
4. **Gate 2 Backstop** - Clean-audit dry-run
5. **Release Health** - Gate 2 approvals, PM signoffs, SLA state
6. **Project Registry Audit** - Project linkage compliance
7. **System Health** - Orchestration warnings, runtime issues
8. **Blockers Report** - Latest blocker from each agent outbox
9. **Suggested Actions** - AI-generated CEO recommendations

### Purpose in Ecosystem
- **Weekly Health Dashboard**: Summarizes org health
- **Blocker Tracking**: Lists what's stuck
- **Auto-Dispatch**: Creates inbox items for failing checks (e.g., stale audits)
- **Board Awareness**: Gives Keith a snapshot of org state

### Current Status
- ✅ **WORKING**: Every 2h, runs all checks and auto-dispatches items
- ✅ **OUTPUT**: Detailed log with findings and suggested actions
- ✅ **AUTO-DISPATCH**: Creates inbox items for downstream teams (e.g., qa-dungeoncrawler for stale audit)
- Example output shows it caught: stale QA audit, executor failures, missing escalation matrix items

### Is It Needed?
**DEPENDS ON ARCHITECTURE CHOICE:**

**Option A: Keep Cron-Based (Current)**
- Pro: Scheduled, predictable, independent of orchestrator
- Con: Redundant if orchestrator already does similar scans
- Status: Works, no urgency to change

**Option B: Move to Orchestration Loop**
- Pro: Single unified monitoring system
- Con: Orchestrator run.py would need to import/run these check scripts
- Unclear: Does orchestrator already do these checks as part of kpi_monitor or health_check phases?

### Overlap with Orchestrator?
**UNKNOWN** — Need to investigate:
- Does `orchestrator/run.py` already run ceo-system-health.sh, ceo-release-health.sh?
- Does KPI monitor subsume the health checking?
- Is there redundant monitoring?

### Recommendations
1. ⚠️ **INVESTIGATE** if orchestrator duplicates this work
2. If no overlap: Keep as-is (provides predictable health snapshot)
3. If overlap exists: Consider consolidating (avoid dual reporting)
4. For now: Continue running, flag for future unification

---

## JOB 3: board-daily-reminder (daily at 08:00 UTC)

### What It Does
- Scans `inbox/commands/` for .md files (CEO decisions awaiting Board input)
- Sends email to Keith if items are pending
- Extracts topic and created_at from YAML frontmatter
- Uses hardcoded sendmail

### Purpose in Ecosystem
- **Push Notification**: Keith sees (via email) that there's work pending
- **Non-Blocking**: Keith can choose when to process items; reminder fires daily until cleared

### Current Status
- ✅ **WORKING**: Email sends successfully
- ✅ **FREQUENCY**: Daily at 08:00 UTC (reasonable for human inbox)
- ⚠️ **HARDCODED**: BOARD_EMAIL is in script (should be in board.conf)

### Is It Needed?
**CONDITIONALLY YES:**
- If Keith never checks inbox/commands/ directory on his own: YES, needed
- If Keith has other notification mechanisms: Maybe redundant
- If orchestrator dispatches decisions to CEO inbox: This might be superseded

### Does Orchestration Handle This?
❓ **UNCLEAR** — Orchestrator routes inbox/commands to PM/CEO inboxes, but:
- Does orchestrator trigger email notifications?
- Does orchestrator have a daily digest similar to board-daily-reminder?
- Check: orchestrator/run.py for email/notification logic

### Observations
1. Emails go to `inbox/commands/` which is different from `sessions/ceo-copilot-2/inbox/`
2. May be legacy (pre-orchestrator agent model?)
3. Frequency (daily) makes sense for human notification

### Recommendations
1. ⚠️ **INVESTIGATE** if orchestrator has equivalent notification
2. If orchestrator lacks daily digest: Keep this job
3. If orchestrator will handle it: Can retire this
4. For now: Continue running (low cost, useful to Keith)

---

## JOB 4: notify-pending (every 10 minutes)

### What It Does
- Monitors decision tracking system (unknown source)
- Sends email/SMS alerts to CEO about pending decisions
- Cooldown: 14400 sec (4 hours) — won't spam
- Configurable via env: NOTIFY_METHOD (log|sendmail|twilio), NOTIFY_COOLDOWN_SECONDS
- Tries to detect active CEO agent and notify that agent (not hardcoded email)

### Purpose in Ecosystem
- **Real-Time Alerts**: If critical decision needed, CEO notified quickly
- **Escalation**: Ensures CEO doesn't miss time-sensitive items
- **Frequency**: 10-min heartbeat, but 4h cooldown prevents spam

### Current Status
- ✅ **WORKING**: Runs every 10 min with 4h cooldown
- ✅ **SMART**: Doesn't re-notify if already notified in last 4h
- ✅ **INTELLIGENT**: Routes to active CEO agent dynamically

### Is It Needed?
**YES, if:**
- CEO makes time-sensitive decisions that can't wait for 2-hour ceo-ops-once cycle
- Job Hunter / Drupal issues need immediate escalation
- Board (Keith) wants push notifications for critical items

**NO, if:**
- Orchestrator already has event-driven escalation (detects critical failures)
- CEO agent monitors its own inbox continuously
- All decisions are non-time-critical

### Does Orchestration Handle This?
❓ **UNCLEAR** — Orchestrator has:
- Agent routing logic
- Inbox monitoring
- Escalation matrix
- But unclear if it can trigger real-time notifications

### Observations
1. "Pending decision items" source is undocumented (need to find the decision tracking system)
2. 10-minute frequency seems high for 4-hour cooldown (overly conservative?)
3. Better as event-driven (when decision is created) than time-based (every 10 min)

### Recommendations
1. ⚠️ **INVESTIGATE** decision tracking source and frequency need
2. ⚠️ **INVESTIGATE** if orchestrator has equivalent escalation
3. If needed: Keep as-is (working correctly)
4. If redundant: Consider retiring in favor of orchestrator event-driven approach

---

## SUMMARY TABLE

| Job | Current Status | Necessity | Arch Fit | Recommendation |
|-----|---|---|---|---|
| auto-checkpoint | ✅ Working | CRITICAL | ✅ Git ops belong in cron | Keep as-is |
| ceo-ops-once | ✅ Working | CONDITIONAL | ❓ May overlap with orchestrator | Investigate overlap |
| board-daily-reminder | ✅ Working | CONDITIONAL | ❓ May be superseded by orchestrator | Investigate orchestrator notifications |
| notify-pending | ✅ Working | CONDITIONAL | ❓ May be event-driven in orchestrator | Investigate escalation mechanism |

---

## ARCHITECTURAL QUESTION FOR USER

**How should CEO operations be handled in the future?**

### Option A: Distributed Model (Current)
- auto-checkpoint: cron (git ops)
- ceo-ops-once: cron (health check)
- board-daily-reminder: cron (push notification)
- notify-pending: cron (escalation)
- CEO agent: continuous (in orchestrator loop)

**Pros**: Clear separation, independent, testable
**Cons**: Dual monitoring, potential gaps/overlaps

### Option B: Unified Orchestration Model (Proposed)
- Orchestrator subsumes all CEO ops
- Cron jobs retired except auto-checkpoint (git is special)
- Single source of truth for CEO health/notifications
- CEO agent consumes orchestrator output

**Pros**: Single system, no redundancy, coordinated
**Cons**: Complex, single point of failure, harder to debug

### Option C: Hybrid Model (Recommended)
- auto-checkpoint: keep in cron (git operations)
- ceo-ops-once: integrate into orchestrator publish phase
- board-daily-reminder: trigger from orchestrator when items created
- notify-pending: event-driven from orchestrator escalation matrix
- Cron acts as fallback, orchestrator as primary

**Pros**: Best of both, resilient, coordinated
**Cons**: More complex to implement

---

## NEXT STEPS

1. **Investigate Orchestrator Coverage**
   - Does orchestrator/run.py already call ceo-system-health.sh, etc?
   - Are KPI monitor and health check already doing CEO ops?
   - What's the email/notification system?

2. **Ask User**
   - What's the intended architecture?
   - Which cron jobs should we retire/consolidate?
   - What's the decision tracking system for notify-pending?

3. **Make Decision**
   - Based on architecture choice, update crontab
   - Document the new model
   - Create migration plan if consolidating

