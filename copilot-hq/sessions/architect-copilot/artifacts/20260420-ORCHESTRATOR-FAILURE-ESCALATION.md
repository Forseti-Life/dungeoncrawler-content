# Orchestrator Failure Escalation — Implementation

**Date**: 2026-04-20  
**Change**: Enhanced hq-health-heartbeat.sh with critical email escalation  
**Trigger**: 3 consecutive orchestrator restart failures  
**Recipient**: Keith (Board email via board.conf)

---

## What Changed

**Before**:
- Orchestrator restart failure → logged to files only
- No automated escalation to user
- User relies on manual log checking

**After**:
- Orchestrator restart failure → tracked in failure counter
- 1st failure: logged as WARN
- 2nd failure: logged as WARN
- 3rd failure: **CRITICAL EMAIL sent to keith.aumiller@stlouisintegration.com**
- Successful restart: counter reset to 0

---

## How It Works

### Failure Tracking

**File**: `/tmp/orchestrator-restart-failures.count`
- Stores integer count of consecutive failures
- Incremented on each failed restart
- Reset to 0 on successful restart (health or manual)

**Timeline Example**:
```
T+0:00  Orchestrator dies
T+0:02  Heartbeat runs: detects down, tries restart, fails
        failure count = 1, logs WARN
T+0:04  Heartbeat runs: still down, tries restart, fails
        failure count = 2, logs WARN
T+0:06  Heartbeat runs: still down, tries restart, fails
        failure count = 3, **SENDS CRITICAL EMAIL**
T+0:08  Heartbeat runs: still down, tries restart, fails
        failure count = 4, no additional email (already sent at 3)
```

### Email Details

**Subject**: `CRITICAL FAILURE: ORCHESTRATOR DOWN (attempt N)`

**From**: `hq-noreply@forseti.life` (configurable via board.conf)

**To**: `keith.aumiller@stlouisintegration.com` (configurable via board.conf)

**Body includes**:
- Timestamp of failure
- Service name: orchestrator-loop
- Script path: scripts/orchestrator-loop.sh
- Failure count: 3
- Investigation steps
- Log file locations
- Manual restart command

**Example**:
```
CRITICAL FAILURE: HUMAN NEEDED

Orchestrator restart has failed 3 consecutive times.

Timestamp: 2026-04-20T15:45:32+00:00
Service: orchestrator-loop
Script: scripts/orchestrator-loop.sh
Failure count: 3

The orchestrator has stopped and cannot be automatically restarted.
Manual intervention is required immediately.

Check logs:
  - Heartbeat: /tmp/hq-health-heartbeat.log
  - Alerts: /tmp/hq-health-alert.log

To investigate:
  cd /home/ubuntu/forseti.life/copilot-hq
  ./scripts/orchestrator-loop.sh verify
  tail -50 /var/log/orchestrator.log (if available)
  
To manually restart once issue is fixed:
  ./scripts/orchestrator-loop.sh start 60
```

---

## Configuration

**Board email** (from `org-chart/board.conf`):
```bash
BOARD_EMAIL="keith.aumiller@stlouisintegration.com"
HQ_FROM_EMAIL="hq-noreply@forseti.life"
HQ_SITE_NAME="forseti.life HQ"
```

To change recipient:
```bash
# Edit org-chart/board.conf
BOARD_EMAIL="your.email@example.com"
```

---

## Implementation Details

### New Functions

**`send_critical_email(subject, body)`**
- Builds RFC 2822 email format
- Uses `/usr/sbin/sendmail -t` (standard SMTP)
- Logs success/failure to alert log

**`increment_failure_count()`**
- Reads current count from `/tmp/orchestrator-restart-failures.count`
- Increments by 1
- Writes back and echoes new count

**`reset_failure_count()`**
- Deletes failure count file
- Called on successful restart or when orchestrator is healthy

### Modified Logic

**check_and_restart_loop() function**:
1. If healthy → reset counter, return 0
2. If down, attempt restart
3. If restart succeeds → reset counter, return 0
4. If restart fails:
   a) Increment counter
   b) If counter >= 3 → send critical email
   c) Return 1

---

## Email Constraints

**Sendmail requirement**:
- Script uses `/usr/sbin/sendmail -t` (must be installed)
- Reads standard input for RFC 2822 format email
- Requires mail relay configured (usually postfix/sendmail running)

**If sendmail not available**:
- Email send will fail silently
- Alert will be logged: "CRITICAL EMAIL FAILED to send"
- Heartbeat will still exit 1 (failure is logged)

**Gmail Workspace note**: Emails from hq-noreply@forseti.life will go through Google Workspace SMTP (SPF/DKIM configured).

---

## When This Triggers

**Scenario 1: Orchestrator crash, won't restart**
- T+6 min: Email sent (3 × 2-minute cycles)
- Keep retrying every 2 minutes until fixed

**Scenario 2: Orchestrator process hangs (verify returns false)**
- Same: T+6 min email sent
- Process still running but not responding

**Scenario 3: Disk full, can't write PID file**
- Same: T+6 min email sent
- Fix: Clear disk space, script retries automatically

**Scenario 4: Successful restart between failures**
- If restart succeeds on attempt 2: counter resets, no email
- If restart succeeds on attempt 3: counter resets, no email
- Only sends email if 3 consecutive failures

---

## Testing

**Manual test**:
```bash
# Kill orchestrator manually
killall python3  # (or orchestrator-loop.sh process)

# Wait 6 minutes (3 cycles × 2 minutes)
# Check inbox for critical email
```

**Dry-run test** (without killing orchestrator):
```bash
# Temporarily break verify to simulate failure
# Or manually create /tmp/orchestrator-restart-failures.count with value 2
echo 2 > /tmp/orchestrator-restart-failures.count

# Run heartbeat (will increment to 3 and try to send email)
bash scripts/hq-health-heartbeat.sh

# Check /tmp/hq-health-alert.log for email result
tail -20 /tmp/hq-health-alert.log
```

---

## Failure Recovery

**After receiving critical email**:

1. **SSH into server**:
   ```bash
   ssh root@forseti.life
   cd /home/ubuntu/forseti.life/copilot-hq
   ```

2. **Diagnose**:
   ```bash
   ./scripts/orchestrator-loop.sh verify  # Check if running
   tail -50 /var/log/orchestrator.log      # Check logs
   ps aux | grep orchestrator              # Check process
   ```

3. **Fix** (depends on root cause):
   - Disk full? `df -h` and clean up
   - Port conflict? `lsof -i :8000` (or whatever port)
   - Python error? Check orchestrator/run.py syntax

4. **Manually restart**:
   ```bash
   ./scripts/orchestrator-loop.sh start 60
   ```

5. **Verify**:
   ```bash
   ./scripts/orchestrator-loop.sh verify  # Should exit 0
   ```

6. **Check counter reset**:
   ```bash
   ls -l /tmp/orchestrator-restart-failures.count  # Should not exist
   ```

---

## Logs

**Locations**:
- **Heartbeat**: `/tmp/hq-health-heartbeat.log` (all events)
- **Alerts**: `/tmp/hq-health-alert.log` (warnings + critical events)
- **Failure counter**: `/tmp/orchestrator-restart-failures.count` (integer)

**Log entries**:
```
[2026-04-20T15:44:32+00:00] WARN orchestrator-loop is DOWN — attempting restart
[2026-04-20T15:44:33+00:00] WARN orchestrator-loop restart FAILED — manual intervention required
[2026-04-20T15:44:33+00:00] WARN CRITICAL EMAIL SENT to keith.aumiller@stlouisintegration.com: CRITICAL FAILURE: ORCHESTRATOR DOWN (attempt 3)
```

---

## Summary

**hq-health-heartbeat.sh now escalates orchestrator failures to the human operator after 3 consecutive failures.**

- **Trigger**: 3rd consecutive restart failure
- **Escalation**: Critical email to board email address
- **Email includes**: Timestamp, failure count, investigation steps, manual restart command
- **Configuration**: Via `org-chart/board.conf` (shared with other board notifications)
- **Testing**: Manual kill of orchestrator, wait 6 minutes for email

**This ensures critical infrastructure failures are never silently ignored — after 6 minutes of heartbeat failures, the human is notified immediately.**
