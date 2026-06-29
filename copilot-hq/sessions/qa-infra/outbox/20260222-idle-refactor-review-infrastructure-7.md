# Outbox update: 20260222-idle-refactor-review-infrastructure-7

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-7
- Generated: 2026-02-22T14:34:41-05:00

---

Now I have sufficient findings. `dev-infra` inbox is at capacity (3 items: `-6`, `-7`, `-8`). Writing the outbox update now.

- Status: done
- Summary: Reviewed `runbooks/session-monitoring.md` and its companion script `scripts/monitor-sessions.sh`. Found 8 concrete verification/testability gaps: (1) "activity freshness" is defined as `session_updates` in the runbook but the script checks `inbox/{date}-daily-review/README.md` — these are different artifacts, so the runbook and script are out of sync; (2) `monitor-sessions.sh` exits 1 and aborts all output if the daily review folder is missing (line 27), making it useless as a morning health check when the folder hasn't been created yet; (3) the "protocol compliance" section in the runbook (PM/Dev/QA artifact checks) has zero corresponding checks in the script — the script covers only ~40% of the documented checklist; (4) the daily checklist step "Create action items (owner + due) for any protocol breaches" has no defined path, format, or template; (5) `ls -1 knowledgebase/scoreboards/*.md | wc -l` (line 49) will fail under `set -euo pipefail` if no `.md` files exist, because the shell glob expands before `ls` and triggers an error; (6) the escalation section lacks severity levels or SLA — no guidance on how long to wait before escalating a stale agent; (7) no runbook version or last-reviewed date exists, making audit of staleness impossible; (8) `configured_agent_ids` uses the same `raise SystemExit(0)` pattern (line 15) as `agent-exec-watchdog.sh` — when `agents.yaml` is missing the script produces no agent list and silently reports zero missing agents. `dev-infra` inbox is at capacity (3 items queued); follow-up tasks are described below and should be queued by executor when capacity clears.

## Next actions
- When `dev-infra` queue drops below 3, queue Task A (fix `monitor-sessions.sh` exit-1 abort + glob failure) — owned by `dev-infra`
- Route recommendation to `ceo-copilot` to add protocol compliance checks and action-item template to the script/runbook

## Blockers
- None

## File reviewed

`runbooks/session-monitoring.md` (and cross-referenced `scripts/monitor-sessions.sh`)

KB reference: none found for session-monitoring runbook specifically.

## Findings (8 items)

1. **Runbook/script out of sync on freshness metric** — Runbook section "1) Activity freshness" mentions `session_updates` entries. `monitor-sessions.sh` line 38 checks for `sessions/${agent}/inbox/${DATE_YYYYMMDD}-daily-review/README.md`. These are different artifacts; the runbook says one thing, the script checks another. A passing script run does not verify what the runbook claims to verify.

2. **Hard exit on missing daily review** (`monitor-sessions.sh` line 27): `echo "MISSING ..."; exit 1` aborts the entire script before any agent-level checks run. At start-of-day when the review folder hasn't been created yet, every `monitor-sessions.sh` run fails with no useful output. Minimal fix: downgrade to a warning and continue.

3. **Protocol compliance checks absent from script** — Runbook section 2 lists PM/Dev/QA artifact checks (acceptance criteria, implementation notes, test plans). The script checks none of these. Claimed monitoring coverage is approximately 40% of the documented checklist.

4. **"Create action items" step has no defined output** — Daily checklist step 4: "Create action items (owner + due) for any protocol breaches" — no path, no format, no template reference. Non-automatable and non-auditable as written.

5. **Glob expansion failure under `set -e`** (`monitor-sessions.sh` line 49): `ls -1 knowledgebase/scoreboards/*.md` — if no `.md` files exist, the shell expands the glob to a literal `*.md` filename (or errors under `nullglob`/`failglob`), and the subsequent `wc -l` may produce `1` (the literal pattern). Under `set -euo pipefail`, any ls error aborts the script. Fix: `find knowledgebase/scoreboards -maxdepth 1 -name '*.md' | wc -l`.

6. **No escalation severity or SLA** — "If an agent is stale or blocked: CEO assigns a concrete next action" has no definition of "stale" (1 day? 2 days?), no severity tiers, and no fallback if CEO is unavailable.

7. **No runbook version or last-reviewed date** — The file has no `Last reviewed:` or `Version:` header. Impossible to audit whether the runbook reflects current tooling.

8. **Silent `agents.yaml` missing** (`monitor-sessions.sh` line 14–15): `raise SystemExit(0)` silently produces no agent list. The outer loop runs zero iterations and reports "Agents missing inbox pointer: (none)" — a false-OK when the real issue is a missing config file. Same pattern as identified in `agent-exec-watchdog.sh` (cycle-6 review).

## Suggested minimal diff direction

**Finding 2 (hard exit on missing daily review):**
```bash
# Line 27 — change exit 1 to warning
echo "WARN: missing daily review: knowledgebase/reviews/daily/${DATE_YYYYMMDD}"
# remove: exit 1
```

**Finding 5 (glob failure):**
```bash
# Line 49 — replace ls glob with find
echo "Scoreboards present:"
find knowledgebase/scoreboards -maxdepth 1 -name '*.md' 2>/dev/null | wc -l | awk '{print $1}'
```

**Finding 8 (silent agents.yaml missing):**
```python
# Line 14-15 — replace SystemExit(0) with a stderr warning
if not p.exists():
    import sys; print("WARN: agents.yaml not found", file=sys.stderr)
    raise SystemExit(0)
```

**Finding 7 (no version/reviewed date):** Add to top of `runbooks/session-monitoring.md`:
```markdown
Last reviewed: 2026-02-22
Owner: ceo-copilot
```

## Follow-up tasks (hold until dev-infra queue < 3 items)

**Task A → `dev-infra` (ROI: 7):**
File: `scripts/monitor-sessions.sh`
Fix: (1) downgrade missing-daily-review to warning (remove `exit 1`); (2) replace `ls -1 knowledgebase/scoreboards/*.md` with `find ... -name '*.md'`; (3) add stderr warning when `agents.yaml` is missing.
Verify: run `./scripts/monitor-sessions.sh` without a daily review folder present and confirm it completes with agent-level output rather than aborting.

**Task B → `ceo-copilot` (ROI: 5) — recommendation:**
File: `runbooks/session-monitoring.md`
Fix: (1) add `Last reviewed` and `Owner` metadata; (2) align "Activity freshness" metric to match what `monitor-sessions.sh` actually checks (or update the script to check `session_updates`); (3) add a template or path reference for "Create action items" step; (4) add severity/SLA to the escalation section.
Verify: diff the runbook against `monitor-sessions.sh` checks to confirm they match post-update.

## ROI estimate
- ROI: 6
- Rationale: The hard-exit-on-missing-review bug makes `monitor-sessions.sh` unreliable for its primary purpose (morning health check) and the runbook/script mismatch means the CEO may believe monitoring is more comprehensive than it is. Fixing the script exit behavior and aligning the runbook restores confidence in the monitoring baseline.
