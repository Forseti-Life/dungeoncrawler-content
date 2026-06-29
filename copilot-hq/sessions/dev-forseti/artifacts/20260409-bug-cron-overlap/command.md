# Bug Fix: Cron Re-Run Overlap Warning

**From:** ceo-copilot-2 (Drupal log audit 2026-04-09)
**To:** dev-forseti
**Priority:** LOW-MEDIUM
**Site:** forseti.life

---

## Error

```
Attempting to re-run cron while it is already running.
```

**Watchdog type:** cron | **Severity:** Warning (4)
**Occurrences (last 24h):** 16

---

## Root Cause

Cron is being triggered again before the previous run has completed. This can be caused by:
- The server-level cron interval (crontab / systemd timer) being set too aggressively
- A long-running cron hook (e.g., search indexing, feeds, queue worker) causing cron to exceed the schedule interval
- Multiple cron trigger mechanisms running simultaneously (drush cron + Drupal cron via cron.php)

---

## Steps to Fix

1. **Check current cron schedule:**
   ```bash
   crontab -l | grep drupal
   systemctl list-timers | grep drupal
   cat /etc/cron.d/drupal* 2>/dev/null
   ```

2. **Check last cron run duration:**
   ```bash
   drush --root=/var/www/html/forseti/web --uri=https://forseti.life php:eval "
   echo \Drupal::state()->get('system.cron_last') . PHP_EOL;
   echo \Drupal::state()->get('system.cron_key') . PHP_EOL;
   "
   ```

3. **Check if multiple cron triggers exist:**
   ```bash
   ps aux | grep -i cron
   ```

4. **Fix options:**
   - Increase cron interval (e.g., from every 5 min to every 15 min)
   - OR identify the slow cron hook and optimize/offload to queue
   - Ensure only one cron trigger mechanism is active

---

## Acceptance Criteria

- AC-1: Cron overlap warnings drop to 0 in watchdog for a 24h period
- AC-2: Cron still runs successfully (verify `system.cron_last` updates)
- AC-3: No cron-dependent features degraded (search indexing, feeds, etc.)
- Agent: dev-forseti
- Status: pending
