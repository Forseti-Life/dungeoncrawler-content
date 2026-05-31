# Test Plan: forseti-langgraph-console-run-session

- Feature: forseti-langgraph-console-run-session
- Module: copilot_agent_tracker
- Author: pm-forseti (skeleton from AC traceability; QA to fill commands)
- Date: 2026-04-13
- QA owner: qa-forseti

## Prerequisites

- Admin/authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- `COPILOT_HQ_ROOT` env var set in web context (or fallback to `/home/ubuntu/forseti.life/copilot-hq`)
- `langgraph-ticks.jsonl` exists with at least one tick entry
- `langgraph-parity-latest.json` exists

## Test cases

### TC-1: Run panel loads without error (smoke)

- **Type:** functional / smoke
- **When:** GET `/langgraph-console/run` as authenticated admin
- **Then:** HTTP 200, page contains "Threads & Runs" section heading

---

### TC-2: Threads & Runs subsection shows latest tick data

- **Type:** functional
- **When:** `langgraph-ticks.jsonl` has a recent entry with `selected_agents`
- **Then:** agent names from latest tick are rendered in Threads & Runs subsection

---

### TC-3: Threads & Runs empty-state message

- **Type:** functional / edge-case
- **When:** `langgraph-ticks.jsonl` is absent or empty
- **Then:** "No run data available — start a workflow to populate this panel." is shown

---

### TC-4: Stream Events subsection renders step_results

- **Type:** functional
- **When:** latest tick has non-empty `step_results`
- **Then:** Stream Events subsection lists results with 120-char truncated summary

---

### TC-5: Resume & Retry lists blocked/needs-info items

- **Type:** functional
- **When:** at least one `sessions/*/outbox/*.md` (most-recent per seat) contains `^- Status: blocked` or `^- Status: needs-info`
- **Then:** Resume & Retry subsection lists: seat ID, outbox filename, status badge, and last-modified timestamp for each blocked/needs-info item

**Verification command:**
```bash
# Confirm at least one outbox has blocked/needs-info (prerequisite check)
grep -rl "^- Status: blocked\|^- Status: needs-info" \
  /home/ubuntu/forseti.life/copilot-hq/sessions/*/outbox/*.md 2>/dev/null

# Verify the UI renders the correct seat/file entries
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" \
  https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console/run/resume-retry \
  | grep -i "blocked\|needs-info"
```

**Note:** If no outboxes are currently blocked/needs-info, verify the empty-state renders: "No blocked or needs-info items detected."

---

### TC-6: Session Health shows parity_ok from parity file

- **Type:** functional
- **When:** `langgraph-parity-latest.json` has `parity_ok: true`
- **Then:** Session Health shows ✓ or "OK" parity status

---

### TC-7: COPILOT_HQ_ROOT unset — warning banner

- **Type:** functional / env warning
- **When:** `COPILOT_HQ_ROOT` env var is absent from the web server context (`getenv('COPILOT_HQ_ROOT') === false`)
- **Then:** yellow warning banner "Live data unavailable: COPILOT_HQ_ROOT environment variable is not configured in the web server context." is visible on the Run page; live data still renders (fallback path is used)

**Verification — positive case (env IS set, banner must NOT appear):**
```bash
# Confirm env var is set in production Apache context
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" \
  https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console \
  | grep -i "COPILOT_HQ_ROOT"

# Verify run page has NO warning banner when env is set
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" \
  https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console/run \
  | grep -c "COPILOT_HQ_ROOT environment variable is not configured"
# Expected output: 0
```

**Verification — negative case (env NOT set, banner must appear):**
```bash
# Simulate unset env via PHP eval (requires drush access)
cd /home/ubuntu/forseti.life/sites/forseti && \
  vendor/bin/drush php-eval \
  'echo getenv("COPILOT_HQ_ROOT") === false ? "UNSET" : "SET: " . getenv("COPILOT_HQ_ROOT");'
# If output is SET, banner will not appear in production (correct behavior)
# To test banner: temporarily remove COPILOT_HQ_ROOT from Apache env config,
# run `sudo systemctl reload apache2`, load the run page, verify banner text appears
```

---

### TC-8: Unauthenticated access is blocked

- **Type:** security / ACL
- **When:** GET `/langgraph-console/run` without auth
- **Then:** 403 or redirect to login

---

### TC-9: No state mutations from Run panel

- **Type:** security / read-only
- **When:** all panel actions are GET requests only
- **Then:** no POST/PATCH/DELETE handlers exposed on `/langgraph-console/run` routes
