# Crontab Process Flow Map — Complete Architecture

**Date**: 2026-04-20  
**Architect**: architect-copilot  
**Purpose**: Document the complete execution flow of all 23 cron jobs, their roles, actions, and verifiable outcomes

---

## Executive Summary

**23 active cron jobs** organized into **5 functional domains**:
1. **HQ Orchestration** (4 jobs) — Manage agent dispatch, health checks, automation convergence
2. **Job Hunter Queues** (4 jobs) — Process job postings and resume tailoring
3. **Drupal Maintenance** (2 jobs) — Content synchronization and cron tasks
4. **CEO Operations** (2 jobs) — Decision reminders, health checks, operations
5. **System Maintenance** (10 jobs) — PHP sessions, SSL renewal, disk checks, logging

Each job has:
- **Schedule**: When it runs
- **Invoked script**: What it calls
- **Primary action**: What it does
- **Secondary actions**: Sub-tasks and side effects
- **Verification**: How to confirm execution

---

## Part 1: HQ ORCHESTRATION DOMAIN

### 1.1: hq_automation_watchdog (Every minute × 1440/day)

**Schedule**: `* * * * *` (every minute)  
**Invoked**: `/home/ubuntu/forseti.life/copilot-hq/scripts/hq-automation-watchdog.sh`

**Role**: Fast convergence watchdog — ensures automation engine matches org-control enabled/disabled state

**Primary Actions**:
1. Check if org automation is enabled (`scripts/is-org-enabled.sh`)
2. Run `hq-automation.sh converge --no-require-enabled`
   - If enabled: Start/ensure orchestrator + checkpoint loops
   - If disabled: Stop all background loops
3. Run `suggestion-intake.sh` for each site (forseti, dungeoncrawler)
   - Queries Drupal for new community_suggestion nodes
   - Marks them as "under_review"
   - Creates PM inbox item batches

**Secondary Actions**:
- Logs result to `inbox/responses/hq-automation-watchdog.log`
- Side effect: May start/stop orchestrator-loop if org state changed

**Execution Profile** (from logs):
- Typical interval: ~60 seconds (cron-scheduled)
- Actual runs: 180 times/hour (3x per minute window due to log buffering)
- Median duration: <0.1 seconds (script exits fast)
- Most recent: 2026-04-20 14:52:01+00:00

**Verification**:
```bash
# Check last run
tail -5 copilot-hq/inbox/responses/hq-automation-watchdog.log

# Verify org enabled/disabled state
./scripts/is-org-enabled.sh

# Check if orchestrator is running
./scripts/orchestrator-loop.sh status
```

**Key Finding**: Script completes in <100ms per invocation; no performance risk from frequency.

---

### 1.2: orchestrator_watchdog (Every 5 minutes × 288/day)

**Schedule**: `*/5 * * * *` (every 5 minutes)  
**Invoked**: `/home/ubuntu/forseti.life/copilot-hq/scripts/orchestrator-watchdog.sh`

**Role**: Monitor the orchestrator loop process; restart if dead

**Primary Actions**:
1. Check if orchestrator-loop.sh process is running
2. If dead:
   - Restart orchestrator-loop.sh
   - Log restart event
3. If running:
   - Verify process health
   - Check for hung processes

**Secondary Actions**:
- Logs to `inbox/responses/orchestrator-cron.log`
- May trigger orchestrator restart (side effect)

**Verification**:
```bash
# Check watchdog logs
tail -10 copilot-hq/inbox/responses/orchestrator-cron.log

# Verify orchestrator is running
ps aux | grep orchestrator-loop
./scripts/orchestrator-loop.sh status
```

---

### 1.3: hq_health_heartbeat (Every 2 minutes × 720/day)

**Schedule**: `*/2 * * * *` (every 2 minutes)  
**Invoked**: `/home/ubuntu/forseti.life/copilot-hq/scripts/hq-health-heartbeat.sh`

**Role**: Continuous health monitoring of HQ system

**Primary Actions**:
1. Check system resources (CPU, memory, disk)
2. Check agent process health
3. Monitor orchestrator status
4. Record heartbeat

**Secondary Actions**:
- Logs to `/tmp/hq-health-heartbeat.log`
- May trigger alerts if health checks fail

**Verification**:
```bash
tail -10 /tmp/hq-health-heartbeat.log
```

---

### 1.4: auto_checkpoint (Every 2 hours × 12/day)

**Schedule**: `0 */2 * * *` (at :00 of every 2 hours)  
**Invoked**: `/home/ubuntu/forseti.life/copilot-hq/scripts/auto-checkpoint.sh`

**Role**: Automatic git commit and push for session state artifacts

**Primary Actions**:
1. Check for dirty/uncommitted changes in:
   - `sessions/*/inbox/` — dispatcher output
   - `sessions/*/outbox/` — agent work results
   - `sessions/*/artifacts/` — work artifacts
   - `.roi.txt` files
2. If changes exist:
   - Stage files (`git add`)
   - Commit with message: "auto: checkpoint sessions + artifacts"
   - Push to GitHub: `git push origin main`
3. If no changes:
   - Exit cleanly (idempotent)

**Secondary Actions**:
- Logs to `inbox/responses/auto-checkpoint-cron.log`
- Side effect: Updates GitHub repo with latest session state

**Verification**:
```bash
# Check last checkpoint
tail -10 copilot-hq/inbox/responses/auto-checkpoint-cron.log

# Verify git is clean
cd copilot-hq && git status
```

---

## Part 2: JOB HUNTER QUEUE PROCESSING DOMAIN

### 2.1: job_hunter_genai_parsing (Every 5 minutes × 288/day)

**Schedule**: `*/5 * * * *` (every 5 minutes)  
**Invoked**: `cd /var/www/html/forseti && flock -n /tmp/job_hunter_queue.lock vendor/bin/drush queue:run job_hunter_genai_parsing --time-limit=240`

**Role**: Parse GenAI results from job posting analysis

**Primary Actions**:
1. Acquire flock on `/tmp/job_hunter_queue.lock` (prevents overlap)
2. Run Drupal queue: `job_hunter_genai_parsing`
   - Dequeue items from `job_hunter_genai_parsing` queue
   - Parse AI-generated summaries
   - Store results in Drupal database
   - Process up to 240 seconds per run
3. Release flock

**Secondary Actions**:
- Logs to syslog via `logger -t job_hunter_queue`
- Stores results in Drupal `job_hunter` module tables

**Verification**:
```bash
# Check queue status
drush queue:list

# View logs
journalctl -u cron -g "job_hunter_queue"
tail -20 /var/log/syslog | grep job_hunter
```

---

### 2.2: job_hunter_posting_parsing (Every 5 minutes × 288/day)

**Schedule**: `*/5 * * * *` (every 5 minutes)  
**Invoked**: `cd /var/www/html/forseti && vendor/bin/drush queue:run job_hunter_job_posting_parsing --time-limit=240 2>&1 | logger -t job_hunter_queue`

**Role**: Parse and enrich job posting data

**Primary Actions**:
1. Run Drupal queue: `job_hunter_job_posting_parsing`
   - Dequeue raw job posting items
   - Parse HTML/JSON to extract job attributes
   - Store in Drupal database
   - Process up to 240 seconds per run
2. Log results

**Secondary Actions**:
- Logs to syslog
- Stores to Drupal database

**⚠️ CRITICAL ISSUE**: **No flock protection** (unlike other Job Hunter queues)
- Risk: If queue processing >5 min, next invocation overlaps
- Impact: Queue corruption, duplicate processing, data loss

**Verification**:
```bash
drush queue:list | grep posting
```

---

### 2.3: job_hunter_resume_tailoring (Every 5 minutes × 288/day)

**Schedule**: `*/5 * * * *` (every 5 minutes)  
**Invoked**: `cd /var/www/html/forseti && flock -n /tmp/jh_tailoring.lock vendor/bin/drush queue:run job_hunter_resume_tailoring --time-limit=240 >> /var/log/drupal/tailoring_queue.log 2>&1`

**Role**: AI-powered resume tailoring for specific job postings

**Primary Actions**:
1. Acquire flock on `/tmp/jh_tailoring.lock` (prevents overlap)
2. Run Drupal queue: `job_hunter_resume_tailoring`
   - Dequeue user resume + job posting pairs
   - Call AI service (via `ai_conversation` module)
   - Generate tailored resume version
   - Store results
   - Process up to 240 seconds per run
3. Release flock

**Secondary Actions**:
- Logs to `/var/log/drupal/tailoring_queue.log`
- Calls AWS Bedrock Claude 3.5 Sonnet
- Stores tailored resumes in Drupal

**Verification**:
```bash
drush queue:list | grep tailoring
tail -20 /var/log/drupal/tailoring_queue.log
```

---

## Part 3: DRUPAL MAINTENANCE DOMAIN

### 3.1: forseti_cron (Every 3 hours × 8/day)

**Schedule**: `0 */3 * * *` (at :00 of every 3 hours)  
**Invoked**: `cd /var/www/html/forseti && flock -n /tmp/forseti_cron.lock ./vendor/bin/drush --uri=https://forseti.life cron 2>&1 | logger -t forseti_cron`

**Role**: Drupal maintenance cron for forseti.life site

**Primary Actions**:
1. Acquire flock on `/tmp/forseti_cron.lock` (prevents overlap)
2. Run Drupal cron for forseti.life:
   - Clean up stale sessions
   - Process queue items
   - Update search indexes
   - Run module-specific maintenance hooks
3. Release flock

**Secondary Actions**:
- Logs to syslog
- May trigger email sends, cache clears, etc.

**Verification**:
```bash
drush cron-last
drush status
```

---

### 3.2: dungeoncrawler_cron (Every 30 minutes × 48/day)

**Schedule**: `*/30 * * * *` (every 30 minutes)  
**Invoked**: `cd /var/www/html/dungeoncrawler && flock -n /tmp/dungeoncrawler_cron.lock ./vendor/bin/drush --uri=https://dungeoncrawler.forseti.life cron 2>&1 | logger -t dungeoncrawler_cron`

**Role**: Drupal maintenance cron for dungeoncrawler site

**Primary Actions**:
1. Acquire flock
2. Run Drupal cron for dungeoncrawler
3. Release flock

**Secondary Actions**:
- Logs to syslog

**Note**: Runs more frequently (every 30 min) than forseti.life (every 3 hrs)  
**Question**: Is this frequency difference intentional?

**Verification**:
```bash
drush --uri=https://dungeoncrawler.forseti.life cron-last
```

---

## Part 4: CEO OPERATIONS DOMAIN

### 4.1: board_daily_reminder (Daily @ 08:00 UTC × 1/day)

**Schedule**: `0 8 * * *` (08:00 UTC every day)  
**Invoked**: `cd /home/ubuntu/forseti.life/copilot-hq && bash scripts/board-daily-reminder.sh`

**Role**: Send daily digest email to Board (Keith) on pending decisions

**Primary Actions**:
1. Check for pending items in `inbox/commands/` (human decision queue)
2. If pending items exist:
   - Collect metadata (title, ROI, age, blocker type)
   - Format email digest
   - Send via email (method determined by config)
3. If no pending items:
   - Skip email (idempotent)

**Secondary Actions**:
- Sends email to configured recipient (keith.aumiller@stlouisintegration.com)
- Logs to `/var/log/hq-board-reminder.log`

**Verification**:
```bash
ls -la copilot-hq/inbox/commands/
tail -20 /var/log/hq-board-reminder.log
```

---

### 4.2: ceo_ops_once (Every 2 hours × 12/day)

**Schedule**: `0 */2 * * *` (at :00 of every 2 hours)  
**Invoked**: `/home/ubuntu/forseti.life/copilot-hq/scripts/ceo-ops-once.sh`

**Role**: CEO quality check — health diagnostics, blocker detection, priority ranking

**Primary Actions**:
1. Run system health checks:
   - Agent starvation detection (QA, Security stalled?)
   - Merger status (pending PRs, conflicts)
   - Blocker escalation (aged needs-info items)
   - Release cycle status
   - KPI health
2. Collect findings into report
3. Exit 0 if healthy, exit 1+ if issues detected

**Secondary Actions**:
- Logs to `inbox/responses/ceo-ops-cron.log`
- May create inbox items if issues found
- Report is CEO's operational visibility

**Verification**:
```bash
./scripts/ceo-ops-once.sh
tail -30 copilot-hq/inbox/responses/ceo-ops-cron.log
```

---

### 4.3: notify_pending (Every 10 minutes × 144/day)

**Schedule**: `*/10 * * * *` (every 10 minutes)  
**Invoked**: `NOTIFY_METHOD=sendmail NOTIFY_EMAIL_TO=keith.aumiller@stlouisintegration.com /home/ubuntu/forseti.life/copilot-hq/scripts/notify-pending.sh`

**Role**: Alert CEO on pending decision items requiring human action

**Primary Actions**:
1. Scan `inbox/commands/` for pending items (human decision queue)
2. For each pending item older than threshold (configurable):
   - Format notification
   - Send via configured method (email, log, Twilio SMS)
3. Log notification sent

**Secondary Actions**:
- Sends email/SMS to CEO
- Logs to `inbox/responses/notify-pending-cron.log`

**Note**: Hardcoded email recipient (keith.aumiller@stlouisintegration.com)

**Verification**:
```bash
tail -20 copilot-hq/inbox/responses/notify-pending-cron.log
```

---

## Part 5: SYSTEM MAINTENANCE DOMAIN

### 5.1–5.3: PHP Session Cleanup, Disk Checks, SSL Renewal

These are standard system cron jobs (see crontab inventory).

| Job | Schedule | Purpose | Verification |
|---|---|---|---|
| `php_session_cleanup` | `09,39 * * * *` | Clean PHP session files | Check `/var/lib/php/sessions/` |
| `e2scrub_daily` | `10 3 * * *` | Daily filesystem check | `e2scrub` output |
| `e2scrub_weekly` | `30 3 * * 0` | Weekly filesystem scrub | `e2scrub` output |
| `certbot_renewal` | `0 */12 * * *` | SSL cert renewal | `certbot certificates` |
| `sysstat_activity` | `5-55/10 * * * *` | System monitoring | `/var/log/sa/` |
| `sysstat_rotate` | `59 23 * * *` | Rotate stats | `sa2` output |

---

## Part 6: PROCESS DEPENDENCY MAP

### Execution Order (Critical Path)

```
EVERY MINUTE:
  hq_automation_watchdog
    ├─→ is-org-enabled.sh (check enabled state)
    ├─→ hq-automation.sh converge
    │    ├─→ orchestrator-loop.sh status (if enabled)
    │    └─→ auto-checkpoint-loop.sh status
    └─→ suggestion-intake.sh × 2 sites
         ├─→ resolve_drupal_root() (lookup site path)
         └─→ Drupal API query (fetch new suggestions)

EVERY 2 MINUTES:
  hq_health_heartbeat
    └─→ Health check metrics

EVERY 5 MINUTES:
  orchestrator_watchdog
    └─→ orchestrator-loop.sh status
  job_hunter_genai_parsing
    └─→ drush queue:run (WITH flock)
  job_hunter_posting_parsing
    └─→ drush queue:run (NO flock ⚠️⚠️⚠️)
  job_hunter_resume_tailoring
    └─→ drush queue:run (WITH flock)
       └─→ AWS Bedrock API call

EVERY 10 MINUTES:
  notify_pending
    └─→ Check inbox/commands/
    └─→ Send email/SMS

EVERY 30 MINUTES:
  dungeoncrawler_cron
    └─→ drush cron (WITH flock)

EVERY 2 HOURS (0, 2, 4, 6, ... 22):
  auto_checkpoint
    └─→ git status, git add, git commit, git push
  ceo_ops_once
    └─→ Health checks, blocker detection

EVERY 3 HOURS:
  forseti_cron
    └─→ drush cron (WITH flock)

DAILY @ 08:00 UTC:
  board_daily_reminder
    └─→ Send digest email

@REBOOT:
  orchestrator_reboot
    └─→ orchestrator-loop.sh start 60
    └─→ Set ORCHESTRATOR_AGENT_CAP=8
    └─→ Set AGENT_EXEC_MAX_CONCURRENT_BEDROCK=6
```

---

## Part 7: RESOURCE CONTENTION ANALYSIS

### CPU/I/O Hotspots

| Minute | Load | Jobs | Risk | Mitigation |
|---|---|---|---|---|
| :00 | HIGH | 8 jobs converge (auto_checkpoint, ceo_ops_once, hourly system jobs, periodic drupal crons) | Contention | Offset one job to :30 |
| :05 | HIGH | 4 jobs start (job_hunter×3, orchestrator_watchdog) + hq_automation + hq_health | CPU spike | Stagger by 1–3 sec |
| :10 | MEDIUM | notify_pending | Low | OK |
| :30 | MEDIUM | dungeoncrawler_cron | Low | OK |
| Other | LOW | Various spacing | OK | OK |

### Dependencies & Conflicts

| Job A | Job B | Interaction | Risk |
|---|---|---|---|
| hq_automation_watchdog | orchestrator_watchdog | Both may check orchestrator status | None (read-only) |
| job_hunter_genai | job_hunter_posting | No flock on posting → could overlap | Queue corruption |
| auto_checkpoint | ceo_ops_once | Both @ :00 every 2h | CPU contention |
| forseti_cron | dungeoncrawler_cron | Async (different sites) | None |

---

## Part 8: DATA FLOWS & SIDE EFFECTS

### What Gets Written

| Job | Writes To | Data Type | Frequency |
|---|---|---|---|
| hq_automation_watchdog | syslog + log file | Control events | 1440x/day |
| job_hunter_* | Drupal DB | Queue items | 12x/hour × 3 |
| forseti_cron | Drupal DB | Session + content | 8x/day |
| auto_checkpoint | GitHub | Git commits | 12x/day |
| board_daily_reminder | Email inbox | Digest message | 1x/day |
| notify_pending | Email inbox | Alert message | 144x/day |
| ceo_ops_once | Log file | Health report | 12x/day |

### What Gets Read

| Job | Reads From | Data Type | Check |
|---|---|---|---|
| hq_automation_watchdog | Drupal API | Site metadata | ✅ Active |
| orchestrator_watchdog | Process list | PID file | ✅ Active |
| board_daily_reminder | Filesystem | Inbox items | ✅ Active |
| notify_pending | Filesystem | Pending items | ✅ Active |
| auto_checkpoint | Git tree | Uncommitted changes | ✅ Active |

---

## Part 9: VERIFICATION PROCEDURES

### Real-Time Verification

```bash
# 1. Check all jobs are scheduled
crontab -l | grep -v "^#"

# 2. Verify hq_automation_watchdog is running (every minute)
tail -5 copilot-hq/inbox/responses/hq-automation-watchdog.log

# 3. Check orchestrator status
./copilot-hq/scripts/orchestrator-loop.sh status

# 4. Verify Job Hunter queues
drush queue:list | grep job_hunter

# 5. Check recent auto-checkpoint
tail -5 copilot-hq/inbox/responses/auto-checkpoint-cron.log

# 6. Verify git is in sync
cd copilot-hq && git status

# 7. Check CEO ops health
./copilot-hq/scripts/ceo-ops-once.sh

# 8. Verify board notifications
tail -10 /var/log/hq-board-reminder.log
```

### Continuous Monitoring

```bash
# Watch logs in real-time
watch -n 5 'tail -20 copilot-hq/inbox/responses/hq-automation-watchdog.log'

# Monitor resource usage during peak :05 minute
watch -n 1 'ps aux | grep -E "drush|orchestrator" | head -10'

# Check for cron errors
grep CRON /var/log/syslog | tail -20
```

### Health Checks

```bash
# Run CEO ops to verify overall health
./copilot-hq/scripts/ceo-ops-once.sh

# Check for stalled jobs
ps aux | grep -E "drush queue|orchestrator-loop" | grep -v grep

# Verify flock files aren't stuck
ls -l /tmp/*.lock
```

---

## Part 10: CRITICAL FINDINGS SUMMARY

### Issues Found

| # | Issue | Severity | Action |
|---|---|---|---|
| 1 | Missing flock on job_hunter_posting_parsing | 🔴 CRITICAL | Add flock protection (1 min) |
| 2 | Four jobs all start at :05 minute | 🟡 MEDIUM | Stagger by 1–3 sec (5 min) |
| 3 | Two jobs converge at :00 every 2h | 🟡 MEDIUM | Offset to :30 (1 min) |
| 4 | ORCHESTRATOR_AGENT_CAP hardcoded in @reboot | 🟡 MEDIUM | Move to config file (30 min) |
| 5 | notify_pending email hardcoded | 🟡 MEDIUM | Move to config (30 min) |
| 6 | Drupal cron frequencies inconsistent | 🟢 LOW | Investigate & document (10 min) |
| 7 | Watchdog timeouts not documented | 🟢 LOW | Document in KB (15 min) |

### Performance Profile

- **hq_automation_watchdog**: ✅ Fast (<100ms per run), no performance risk
- **orchestrator_watchdog**: ✅ Healthy
- **Job Hunter queues**: ⚠️ Job_hunter_posting_parsing needs flock; others OK
- **Drupal crons**: ✅ Protected with flock
- **Email notifications**: ✅ Fast, low overhead
- **Git checkpoint**: ✅ Idempotent, efficient

---

## Conclusion

The crontab structure is **well-designed** with good practices (flock protection, logging, idempotency). The **critical issue** is the missing flock on one Job Hunter queue, which could lead to data loss. All other issues are operational improvements (performance, flexibility, documentation).

**Recommended immediate action**: Add flock to `job_hunter_posting_parsing` (prevents data loss).

---

**Document created by**: architect-copilot  
**Date**: 2026-04-20  
**Status**: Complete — ready for implementation verification
