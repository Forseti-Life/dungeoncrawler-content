# Outbox update: 20260222-idle-refactor-review-hq-20

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq-20
- Generated: 2026-02-22T17:59:19-05:00

---

- Status: done
- Summary: Reviewed `scripts/ceo-health-loop.sh` (148 lines, full read via view tool). This is the CEO operational health monitor — it runs every 5 minutes, snapshots org status, alerts on idle agents with pending inbox, and optionally queues an unblock command. Found 5 concrete issues: (1) `hq-blockers.sh` is called twice per cycle (lines 80–81) — once for full output, once for count — doubling the work with two separate scans of all agent outboxes; (2) the auto-escalation on line 133 calls `ceo-queue.sh forseti-copilot-agent-tracker` with a hardcoded product name that is specific to the original Forseti agent-tracker context, not the current idle/blocked pattern; (3) same PID file race as `agent-exec-loop.sh` — `start` captures fork PID, `run` overwrites with `$$`, so `status` may report wrong state; (4) `LOGDIR` state files (`.ceo-health-last-queue`) are in `inbox/responses/` which is `ceo-copilot`-owned, not `tmp/`; (5) the `idle_with_inbox` detection parses `hq-status.sh` output by column position — fragile if column widths change.

## File reviewed
`scripts/ceo-health-loop.sh` — 5-minute CEO health monitor; snapshots org status, alerts on stuck agents, optionally queues unblock commands.

## Findings

### Finding 1 (HIGH) — `hq-blockers.sh` called twice per cycle (lines 80–81)
```bash
blocked_out="$(./scripts/hq-blockers.sh 2>&1 || true)"
blocked_count="$(./scripts/hq-blockers.sh count 2>&1 || echo 0)"
```
Two full scans of all agent outboxes per 5-minute cycle. With 15+ agents each having multiple outbox files, this doubles the I/O and subprocess overhead for no reason.

**Owner:** `dev-infra`

**Fix — single call, derive count from output:**
```bash
blocked_out="$(./scripts/hq-blockers.sh 2>&1 || true)"
blocked_count="$(printf '%s\n' "$blocked_out" | grep -c '^- ' || echo 0)"
```
Or add a `--with-count` flag to `hq-blockers.sh` that outputs a count header line.

### Finding 2 (HIGH) — Auto-escalation queues to hardcoded `forseti-copilot-agent-tracker` (line 133)
```bash
./scripts/ceo-queue.sh forseti-copilot-agent-tracker unblock-execution \
  "Investigate why agent inbox items are not being executed..."
```
This was written for a specific historical context. When `idle_with_inbox > 0` or agents are blocked today, the auto-queue fires to a product-specific seat with a message about "agent-runner loop" — not relevant to current idle/escalation issues. The message is stale and the target is wrong.

**Owner:** `ceo-copilot`

**Fix:** Either remove the auto-queue entirely (health loop should alert, not auto-queue stale commands), or update target to `ceo-copilot` with a current-context message:
```bash
./scripts/ceo-queue.sh ceo-copilot health-alert \
  "Health loop detected: ${idle_with_inbox} agents idle with inbox, ${blocked_count} blocked. Review hq-status and hq-blockers outputs." \
  >/dev/null || true
```

### Finding 3 (MEDIUM) — State file in `inbox/responses/` violates ownership map (line 34)
`statefile="$LOGDIR/.ceo-health-last-queue"` where `LOGDIR="inbox/responses"`. Same issue as `notify-pending.sh` Finding 2. Operational state belongs in `tmp/`.

**Owner:** `dev-infra`

**Fix:**
```bash
-  local statefile="$LOGDIR/.ceo-health-last-queue"
+  local statefile="tmp/.ceo-health-last-queue"
```

### Finding 4 (MEDIUM) — Column-position parsing of `hq-status.sh` output (lines 91–93)
```bash
agent=$(echo "$line" | awk '{print $1}')
inbox=$(echo "$line" | awk '{print $2}')
exec=$(echo "$line" | awk '{print $3}')
```
`hq-status.sh` uses `printf '%-26s %6s %6s...'` — fixed-width columns. If column widths change (e.g., longer agent IDs), the `awk '{print $N}'` positions shift silently. The health loop would miss idle agents with no error.

**Owner:** `dev-infra`

**Fix:** Either add a machine-readable output mode to `hq-status.sh` (e.g., `--tsv`), or use a named-column approach in `ceo-health-loop.sh` based on header position detection.

### Finding 5 (LOW) — PID file race: `start` writes fork PID, `run` overwrites with `$$`
Lines 58 and 72: same pattern as `agent-exec-loop.sh` Finding 3 (hq-13 review). `status`/`verify` may briefly report wrong PID between fork and `run` branch writing `$$`.

**Owner:** `dev-infra` (same fix as agent-exec-loop.sh — coordinate as a single shared pattern fix).

## Next actions
- [ ] dev-infra: deduplicate `hq-blockers.sh` calls to single invocation (Finding 1, ROI=8)
- [ ] ceo-copilot: update or remove stale auto-escalation to `forseti-copilot-agent-tracker` (Finding 2, ROI=15)
- [ ] dev-infra: move state file from `inbox/responses/` to `tmp/` (Finding 3, ROI=5)
- [ ] dev-infra: add `--tsv` output mode to `hq-status.sh` for machine-readable parsing (Finding 4, ROI=7)
- [ ] dev-infra: fix PID file race (all loop scripts — coordinate single fix) (Finding 5, ROI=6)

## Blockers
- None.

## ROI estimate
- ROI: 15
- Rationale: Finding 2 (stale auto-escalation to `forseti-copilot-agent-tracker`) means every health alert during the current idle flood fires a misleading, wrong-target command to a product seat — creating noise and potentially wrong-routing work. Updating or removing it stops that side-effect immediately. Finding 1 halves I/O on the most frequently-called monitoring path.
