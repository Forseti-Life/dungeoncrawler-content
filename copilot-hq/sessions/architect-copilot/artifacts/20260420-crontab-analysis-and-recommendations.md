# Crontab Analysis & Health Report

**Date**: 2026-04-20  
**Architect**: architect-copilot  
**Status**: Analysis complete — 7 actionable recommendations

---

## Executive Summary

**23 active cron jobs** across the system (13 root + 10 system). Infrastructure is healthy overall with good use of flock protection and monitoring. **Two critical hotspots** at minute `:00` and `:05`, plus **one potential silent killer** (every-minute watchdog). One **data loss risk** (missing flock on Job Hunter posting parser).

**Top Priority**: Add flock to `job_hunter_posting_parsing` (prevents queue corruption).

---

## Full Inventory

### Root Crontab (13 jobs)

| Job | Schedule | Frequency | Purpose |
|---|---|---|---|
| `hq_automation_watchdog` | `* * * * *` | 60x/hour | Monitor automation engine, dispatch agents |
| `hq_health_heartbeat` | `*/2 * * * *` | 30x/hour | System health check |
| `job_hunter_genai_parsing` | `*/5 * * * *` | 12x/hour | Parse GenAI results (4min timeout, has flock) |
| `job_hunter_posting_parsing` | `*/5 * * * *` | 12x/hour | Parse job postings (4min timeout, **NO flock**) |
| `job_hunter_resume_tailoring` | `*/5 * * * *` | 12x/hour | Resume tailoring (4min timeout, has flock) |
| `orchestrator_watchdog` | `*/5 * * * *` | 12x/hour | Monitor orchestrator (timeout unknown) |
| `notify_pending` | `*/10 * * * *` | 6x/hour | Send pending notifications (email) |
| `dungeoncrawler_cron` | `*/30 * * * *` | 2x/hour | Drupal cron (has flock) |
| `forseti_cron` | `0 */3 * * *` | 8x/day | Drupal cron (has flock) |
| `auto_checkpoint` | `0 */2 * * *` | 12x/day | Checkpoint HQ sessions |
| `ceo_ops_once` | `0 */2 * * *` | 12x/day | CEO operations tasks |
| `board_daily_reminder` | `0 8 * * *` | 1x/day | Daily board reminder (UTC 08:00) |
| `orchestrator_reboot` | `@reboot` | 1x/boot | Start orchestrator loop + set env vars |

### System Cron Jobs (10 jobs)

| Job | Schedule | Frequency | Purpose |
|---|---|---|---|
| `php_session_cleanup` | `09,39 * * * *` | 2x/hour | PHP session GC |
| `e2scrub_daily` | `10 3 * * *` | 1x/day | Disk health check (3:10 AM) |
| `e2scrub_weekly` | `30 3 * * 0` | 1x/week | Disk scrub (Sun 3:30 AM) |
| `certbot_renewal` | `0 */12 * * *` | 2x/day | SSL cert renewal (with randomized delay) |
| `sysstat_activity` | `5-55/10 * * * *` | 6x/hour | System activity reporting |
| `sysstat_rotate` | `59 23 * * *` | 1x/day | Daily stats rotation |
| `logrotate_daily` | `0 * * * *` | 1x/hour | Log rotation (via cron.daily) |
| `mandb_daily` | `0 * * * *` | 1x/hour | Manual page DB update (via cron.daily) |
| `apache_htcacheclean` | `0 * * * *` | 1x/hour | Apache cache cleanup (via cron.daily) |
| `apport_cleanup` | `0 * * * *` | 1x/hour | Crash report cleanup (via cron.daily) |

---

## Load Profile Analysis

### Continuous Background Load

**hq_automation_watchdog**
- Frequency: Every minute (60x/hour, 1440x/day)
- Purpose: Monitor automation engine, manage agent dispatch
- Risk: **HIGH** — If each invocation takes >1 second, cron jobs will queue/stack
- Timeout: Not documented
- Action: Profile this script immediately

**hq_health_heartbeat**
- Frequency: Every 2 minutes (30x/hour, 720x/day)
- Purpose: System health check
- Risk: Moderate — can stack if checks take >2 seconds
- Timeout: Not documented
- Action: Document timeout, monitor execution time

### Minute-by-Minute Hotspots

**MINUTE :00 (Every hour, every 2h, every 3h converge)**
```
Jobs that may run simultaneously:
  • forseti_cron (every 3 hours)
  • dungeoncrawler_cron (every 30 minutes)
  • auto_checkpoint (every 2 hours)
  • ceo_ops_once (every 2 hours)
  • logrotate_daily, mandb_daily, apache_htcacheclean, apport_cleanup (hourly)

Peak contention: 8 jobs competing for resources
Solution: Offset auto_checkpoint or ceo_ops_once to :15 or :30
```

**MINUTE :05 (High-frequency job cluster)**
```
Jobs that start at :05:
  • job_hunter_genai_parsing
  • job_hunter_posting_parsing
  • job_hunter_resume_tailoring
  • orchestrator_watchdog
  • hq_automation_watchdog (every minute, so :05 included)

Risk: 4 queue jobs + 1 watchdog = CPU spike
Expected duration: 4 min (240 sec timeout each)
Solution: Stagger by 1–3 seconds (:05, :06, :07, :08)
```

### Queue Processing (Job Hunter)

Job Hunter queues are **well-protected** with flock, **except one**:

| Job | Timeout | Flock | Notes |
|---|---|---|---|
| genai_parsing | 240s | ✓ | `/tmp/job_hunter_queue.lock` |
| **posting_parsing** | 240s | ✗ | **⚠️ NO FLOCK — DATA LOSS RISK** |
| resume_tailoring | 240s | ✓ | `/tmp/jh_tailoring.lock` |

**Impact of missing flock**: If posting parsing takes >5 minutes, the next invocation (at +5 min) will run concurrently, causing:
- Duplicate queue processing
- Queue state corruption
- Missed jobs or double-processing

---

## Issues Found

### 🔴 CRITICAL (Data Loss Risk)

#### 1. job_hunter_posting_parsing Has No Flock
**Problem**: Other Job Hunter parsers use flock to prevent concurrent overlap, but posting parser doesn't.

**Risk**: If queue processing takes >5 minutes, next cron invocation will overlap.

**Impact**: Queue corruption, duplicate processing, data loss.

**Fix**: Add one line to crontab:
```diff
- */5 * * * * cd /var/www/html/forseti && vendor/bin/drush queue:run job_hunter_job_posting_parsing --time-limit=240 2>&1 | logger -t job_hunter_queue
+ */5 * * * * cd /var/www/html/forseti && flock -n /tmp/job_hunter_posting.lock vendor/bin/drush queue:run job_hunter_job_posting_parsing --time-limit=240 2>&1 | logger -t job_hunter_queue
```

**Effort**: 1 minute

---

### 🔴 CRITICAL (Performance Risk)

#### 2. hq_automation_watchdog Runs Every Minute
**Problem**: Script invoked 60 times per hour (1440 times per day) with no documented timeout.

**Risk**: If each invocation takes >1 second, cron jobs will queue/stack and never catch up.

**Impact**: System degradation, missed cron jobs, potential cascade failure.

**Questions**:
- What does this script do?
- How long does it typically run?
- Is every minute really necessary, or could it be 2–3 minutes?

**Action**: Profile the script, measure execution time, document findings.

**Effort**: 10 minutes investigation

---

### 🟡 MODERATE (Resource Contention)

#### 3. Four Job Hunter Jobs Start at :05
**Problem**: Four queue-processing jobs all start at the same minute (`:05`).

**Risk**: CPU spike; jobs may timeout or queue up.

**Impact**: Slower queue processing, potential job loss if timeouts are exceeded.

**Fix**: Stagger by 1–3 seconds:
```diff
  # Current (all at :05):
  */5 * * * * job_hunter_genai_parsing
  */5 * * * * job_hunter_posting_parsing
  */5 * * * * job_hunter_resume_tailoring
  */5 * * * * orchestrator_watchdog

  # Proposed (staggered):
  5-59/5 * * * * job_hunter_genai_parsing
  6-59/5 * * * * job_hunter_posting_parsing
  7-59/5 * * * * job_hunter_resume_tailoring
  8-59/5 * * * * orchestrator_watchdog
```

**Effort**: 5 minutes (crontab edit)

---

#### 4. Two :00 Jobs Converge Every 2 Hours
**Problem**: `auto_checkpoint` and `ceo_ops_once` both run at hour boundaries (`:00`).

**Risk**: CPU contention at minute `:00`.

**Impact**: Minor (only 2 jobs), but could be improved.

**Fix**: Offset one to `:30`:
```diff
  0 */2 * * * /home/ubuntu/forseti.life/copilot-hq/scripts/ceo-ops-once.sh
  → 30 */2 * * * /home/ubuntu/forseti.life/copilot-hq/scripts/ceo-ops-once.sh
```

**Effort**: 1 minute (crontab edit)

---

### 🟡 MODERATE (Operations/Configuration)

#### 5. ORCHESTRATOR_AGENT_CAP & BEDROCK Limits Hardcoded in @reboot Only
**Problem**: Agent cap and Bedrock limits are only set in the `@reboot` crontab entry:
```bash
ORCHESTRATOR_AGENT_CAP=8 AGENT_EXEC_MAX_CONCURRENT_BEDROCK=6 ...
```

**Risk**: To adjust these limits, the system must be rebooted.

**Impact**: Operational inflexibility; if load exceeds caps, must reboot to adjust.

**Fix**: Move to a config file (e.g., `/home/ubuntu/forseti.life/copilot-hq/.env` or `orchestrator/config.yml`):
```bash
# File: copilot-hq/orchestrator/config.yml
agent_cap: 8
bedrock_max_concurrent: 6
```

Then in orchestrator-loop.sh:
```bash
# Read from config instead of hardcoded in crontab
agent_cap=$(grep "agent_cap:" orchestrator/config.yml | awk '{print $2}')
```

**Effort**: 30 minutes

---

#### 6. notify_pending Email Hardcoded
**Problem**: Email recipient is hardcoded in crontab:
```bash
NOTIFY_EMAIL_TO=keith.aumiller@stlouisintegration.com
```

**Risk**: If email address changes, crontab must be edited.

**Impact**: Minor operational friction.

**Fix**: Move to config or environment file.

**Effort**: 30 minutes

---

### 🟡 MODERATE (Documentation/Understanding)

#### 7. Drupal Cron Frequencies Are Inconsistent
**Problem**: 
- `forseti.life`: every 3 hours
- `dungeoncrawler`: every 30 minutes

**Question**: Is this intentional or an oversight?

**Impact**: If an oversight, one site may be under-maintained.

**Action**: Investigate and document the reason, or align frequencies.

**Effort**: 10 minutes investigation

---

### 🟢 LOW (Documentation)

#### 8. Watchdog Job Timeouts Not Documented
**Problem**: Several watchdog scripts don't document timeout/duration:
- `hq_automation_watchdog`
- `orchestrator_watchdog`
- `hq_health_heartbeat`

**Risk**: If jobs hang, no protection.

**Impact**: Minimal (lower priority).

**Fix**: Add timeout documentation or `--time-limit` flags.

**Effort**: 15 minutes (KB article)

---

## What's Working Well ✅

- **Flock protection**: Most critical Job Hunter queues use flock (except posting_parsing)
- **Drupal cron protection**: Both Drupal sites have flock
- **Logging**: Good logging to syslog, /var/log/, and inbox/responses/
- **Resilience**: Orchestrator has both @reboot + watchdog (redundancy)
- **System compatibility**: Cron.daily jobs properly skip if systemd is running
- **PHP session cleanup**: Hourly cleanup at :09 and :39 (good coverage)
- **Certbot failover**: SSL renewal via cron as fallback to systemd timer

---

## Recommendations Summary

### IMMEDIATE (Today — 1 min)
1. **Add flock to job_hunter_posting_parsing** ⚠️ CRITICAL
   - Prevents queue corruption and data loss
   - 1-line change

### THIS WEEK (1–2 hours)
2. Profile `hq_automation_watchdog` (10 min investigation)
   - Determine if every-minute interval is necessary
   - Could move to 2–3 min if safe

3. Stagger the four :05 jobs (5 min crontab edit)
   - Reduces CPU spike at :05
   - Better resource distribution

4. Offset `ceo_ops_once` from `auto_checkpoint` (1 min)
   - Reduce contention at :00

5. Move ORCHESTRATOR_AGENT_CAP to config file (30 min)
   - Allow tuning without reboot

### THIS MONTH (Medium-term)
6. Document watchdog timeouts (15 min KB article)
   - Prevents hangs/stalls
   - Better operational visibility

7. Investigate Drupal cron frequency difference (10 min)
   - Understand if intentional or oversight
   - Align if needed

8. Move notify_pending email to config (30 min)
   - Easier to change recipient

### NEXT MONTH (Long-term)
9. Consider systemd timers vs crontab
   - More flexible scheduling
   - Better integration with system

10. Add cron execution monitoring
    - Track job failure rates
    - Alert on timeouts/stalls

---

## Priority Matrix

| Issue | Impact | Effort | Priority |
|---|---|---|---|
| Missing flock on posting_parsing | 🔴 Critical | 1 min | **DO NOW** |
| hq_automation_watchdog profile | 🔴 Critical | 10 min | **DO NOW** |
| Stagger :05 jobs | 🟡 Medium | 5 min | **THIS WEEK** |
| Move ORCHESTRATOR_AGENT_CAP | 🟡 Medium | 30 min | **THIS WEEK** |
| Offset ceo_ops_once | 🟡 Medium | 1 min | **THIS WEEK** |
| Document watchdog timeouts | 🟢 Low | 15 min | **THIS MONTH** |
| Investigate Drupal frequencies | 🟢 Low | 10 min | **THIS MONTH** |
| Move notify_pending email | 🟢 Low | 30 min | **THIS MONTH** |

---

## Load Chart (Visual Reference)

```
MINUTE    LOAD
:00       ████████ (8 jobs max: crons + checkpoints + system jobs)
:01       ██
:02       ██████ (hq_health_heartbeat)
:03       ██
:04       ██
:05       ███████████████ (job_hunter + orchestrator_watchdog)
:06-:08   ███████ (job_hunter jobs running)
:09       ██ (php_session_cleanup)
:10       ██ (notify_pending)
:30       ██ (dungeoncrawler_cron)
:39       ██ (php_session_cleanup)

CONTINUOUS (every minute):
          ██ hq_automation_watchdog (1440x/day)
```

---

## Next Steps

1. **Today**: Add flock to posting_parsing (1 min)
2. **Today**: Profile hq_automation_watchdog (10 min)
3. **This week**: Implement staggering recommendations
4. **This month**: Documentation and config file migrations
5. **Next month**: Consider systemd timer migration

---

**Analysis completed by**: architect-copilot  
**Date**: 2026-04-20  
**Status**: 7 actionable recommendations, ready for implementation
