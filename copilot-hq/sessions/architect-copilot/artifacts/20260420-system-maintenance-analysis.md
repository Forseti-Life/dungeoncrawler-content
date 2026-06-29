# SYSTEM MAINTENANCE JOBS — DEEP ANALYSIS

## Overview

**6 Jobs Analyzed**: php_session_cleanup, certbot_renewal, e2scrub, sysstat, logrotate, system_utilities

**Status**: All working correctly, standard Linux/OS maintenance

**Finding**: Most are necessary but low priority; safe to keep as-is

---

## JOB 1: php_session_cleanup (2x per hour)

### What It Does
- Removes expired PHP sessions from /var/lib/php/sessions
- Clears sessions older than php.ini max_lifetime

### Necessity: **YES (important)**
- Prevents session dir from growing unbounded
- Frees disk space
- Improves session lookup performance

### Frequency: 2x per hour
- ✅ Reasonable (ensures timely cleanup)
- ⚠️ Could be hourly (no harm)

### Recommendation: **KEEP AS-IS** ✅

---

## JOB 2: certbot_renewal (every 12 hours)

### What It Does
- Checks Let's Encrypt SSL certificates
- Renews if expiring in <30 days
- Reloads Apache automatically

### Necessity: **YES (critical)**
- Without this: SSL certs expire
- Without this: Site goes HTTPS error
- Let's Encrypt requires renewal every 90 days

### Frequency: Every 12 hours
- ✅ Good (catches expiry with 4 chances/day)
- ✅ Safe (renewal is idempotent)

### Recommendation: **KEEP AS-IS** ✅

---

## JOB 3: e2scrub (daily + weekly)

### What It Does
- Daily (03:10 UTC): Scrub ext4 filesystem for errors
- Weekly (Sunday 03:30 UTC): Full weekly scrub

### Purpose
- Detect filesystem corruption
- Repair minor issues
- Prevent data loss from hardware errors

### Necessity: **CONDITIONAL**
- ✅ Good: Catches filesystem issues early
- ⚠️ Only matters if: Hardware is failing or corruption suspected
- ⚠️ Production impact: May slow disk I/O during scrub

### Current Status
- ✅ Scheduled at 03:00 UTC (off-peak, low user load)
- ⚠️ Unknown: Has e2fsck found any issues?

### Recommendation: **KEEP AS-IS** ✅ (safety net)

---

## JOB 4: sysstat (activity collection + rotation)

### What It Does
- **Activity (every 10 min)**: Collect system metrics (CPU, memory, IO, network)
- **Rotation (daily 23:59)**: Archive daily logs

### Purpose
- System performance monitoring
- Historical trending
- Capacity planning

### Tools Enabled
- sar, sadf, iostat, mpstat (Linux sysstat package)

### Necessity: **CONDITIONAL**
- ✅ Useful for: Diagnosing performance issues, capacity planning
- ⚠️ Only if: Metrics are actually analyzed or monitored
- Question: Who consumes these metrics?

### Current Status
- ✅ Collecting every 10 minutes (frequent enough)
- ⚠️ Unknown: Is data being used for anything?
- ⚠️ Risk: Logs might consume disk space if not cleaned up

### Recommendation: **KEEP FOR NOW** ⚠️
- But verify: Are metrics being used?
- If unused: Could disable to save disk/CPU

---

## JOB 5: logrotate (hourly)

### What It Does
- Rotates application and system logs
- Compresses old logs
- Removes logs older than retention period

### Logs Covered
- Apache access/error logs
- PHP error logs
- Drupal logs (/var/log/drupal/)
- HQ logs (orchestrator, cron, etc)

### Necessity: **YES (important)**
- Without this: Logs consume all disk space
- Without this: Disk fills → system crashes
- Without this: Can't debug old issues

### Frequency: Hourly
- ✅ Good (prevents daily log from growing too large)
- ⚠️ Efficient: Only rotates if size/age threshold reached

### Recommendation: **KEEP AS-IS** ✅

---

## JOB 6: system_utilities (hourly x3)

### Three Jobs:
1. **mandb_daily** (hourly)
   - Updates man page database
   - Allows `man command` to work correctly
   - **Necessity**: LOW (cosmetic, improves UX)

2. **apache_htcacheclean** (hourly)
   - Cleans Apache mod_cache temporary files
   - Removes files older than max_time
   - **Necessity**: MEDIUM (prevents cache disk bloat)

3. **apport_cleanup** (hourly)
   - Removes old crash reports
   - Prevents /var/crash from growing
   - **Necessity**: LOW (cosmetic, prevents disk bloat)

### Combined Recommendation
- ✅ **KEEP AS-IS**: All three are low-cost housekeeping

---

## Summary Table

| Job | Frequency | Necessity | Recommendation |
|-----|-----------|-----------|-----------------|
| php_session_cleanup | 2x/hour | HIGH | **KEEP** |
| certbot_renewal | 12h | CRITICAL | **KEEP** |
| e2scrub | daily + weekly | MEDIUM | **KEEP** (safety net) |
| sysstat | 10min + daily | CONDITIONAL | **KEEP** (if used) |
| logrotate | hourly | CRITICAL | **KEEP** |
| mandb_daily | hourly | LOW | **KEEP** (cosmetic) |
| apache_htcacheclean | hourly | MEDIUM | **KEEP** |
| apport_cleanup | hourly | LOW | **KEEP** (cosmetic) |

---

## Risk Assessment

**Keeping All**: Zero risk (standard Linux maintenance)

**Removing Any**:
- Remove logrotate: ⚠️ **HIGH RISK** (disk fills, crash)
- Remove certbot: ⚠️ **HIGH RISK** (SSL expires, site breaks)
- Remove php_session: ⚠️ **MEDIUM RISK** (disk bloat, slowdown)
- Remove others: ✅ LOW RISK (mostly cosmetic)

---

## Optimization Opportunities (Not Urgent)

1. **sysstat retention policy**: How long are metrics kept? Document it.
2. **logrotate retention**: Verify retention is adequate (not too aggressive)
3. **e2scrub**: Only if not running on cloud (AWS/Azure may not support e2scrub)

---

## Conclusion

**All system maintenance jobs are NECESSARY and SAFE**.

**Recommendation**: Keep all as-is. These are standard Linux sysadmin best practices.

**No action required** unless disk space or CPU becomes a problem.

