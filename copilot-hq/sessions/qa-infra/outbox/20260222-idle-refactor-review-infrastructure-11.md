# Outbox update: 20260222-idle-refactor-review-infrastructure-11

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-11
- Generated: 2026-02-22T15:22:09-05:00

---

`dev-infra` inbox is still clear. I have thorough findings on `scripts/sla-report.sh`. Writing outbox now.

- Status: done
- Summary: Reviewed `scripts/sla-report.sh` (the SLA breach detector that feeds `notify-pending.sh`). Found 7 concrete verification/testability gaps: (1) `oldest_inbox_item` uses `find -printf '%T@'` which is GNU/Linux-only — silently returns empty on macOS/BSD, causing the agent to appear to have no pending items and all outbox-lag breaches to be missed; (2) the script only checks the single oldest inbox item per agent, so if an agent has 3 queued items and the oldest has an outbox file, the other two items age past SLA undetected; (3) `outbox_status` uses a `tail -n 1` grep on the status line — if an outbox file has two `- Status:` lines (e.g., a header and a value) the last one is used, which may not reflect the actual status; (4) `needs_escalation_exists` uses a glob `sessions/"${supervisor}"/inbox/*-needs-"${agent}"-*` — the glob produces a literal string when no match exists, and the `for` loop body then checks `[ -f "$d/README.md" ]` which silently fails, making the function return 1 (no escalation exists) correctly, but only because of the file-existence guard; this is fragile and undocumented; (5) `inbox_item_epoch` uses `stat -c %Y` which is Linux-only (`stat -f %m` on BSD) — same portability gap as finding 1; (6) `configured_agent_ids` inherits the same `SystemExit(0)` silent-failure pattern when `agents.yaml` is missing, reporting zero agents and therefore zero breaches — a false-OK during config failures; (7) the `breach` variable is set to `1` but never explicitly reset or aggregated across agents — the exit code of the script is always 0 (the final `if` block only controls stdout), so callers like `notify-pending.sh` that use `grep -c '^BREACH'` to count breaches must parse stdout rather than use the exit code, which is undocumented. `dev-infra` inbox is clear; follow-up tasks are queued below.

## Next actions
- Queue Task A to `dev-infra`: fix portability of `find -printf '%T@'` and `stat -c %Y` (findings 1 and 5)
- Queue Task B to `dev-infra`: extend per-agent SLA check to all inbox items, not just the oldest (finding 2)

## Blockers
- None

## File reviewed

`scripts/sla-report.sh` (and cross-referenced `scripts/supervisor-for.sh`)

KB reference: same `find -printf` portability pattern noted in prior cycle review of `idle-work-generator.sh` (cycle-5 outbox).

## Findings (7 items)

1. **`find -printf '%T@'` GNU-only** (line 32): `find ... -printf '%T@ %f\n'` is not POSIX and silently produces no output on BSD/macOS. The function returns empty string, `oldest_inbox_item` finds no items, and all outbox-lag breaches are missed silently. Pattern matches finding 1 from `idle-work-generator.sh` review (cycle 5).

2. **Only oldest inbox item checked per agent** (line 76): `oldest_inbox_item` returns only the single oldest item. If an agent has items `A` (has outbox) and `B` (no outbox, 2h old), item `B` never triggers a breach. SLA coverage is approximately 1/N of actual pending items.

3. **`outbox_status` uses `tail -n 1`** (line 53): if an outbox file contains the literal template placeholder `- Status: done | in_progress | blocked | needs-info` as a header and then `- Status: done` below it, `tail -n 1` returns `done` correctly — but if the file is malformed with the status line appearing only in a header, the parsed status is wrong. More robustly: match only the first occurrence, or require the status to appear on the first 5 lines per the outbox format spec.

4. **`needs_escalation_exists` glob fallback is undocumented** (lines 60–67): when no matching directories exist, `$d` takes the literal value of the glob pattern (e.g., `sessions/pm-infra/inbox/*-needs-qa-infra-*`). The `[ -f "$d/README.md" ]` guard catches this, but the behavior depends on the shell not enabling `nullglob`. The function returns 1 (no escalation) which is correct, but this is fragile — if called in a shell with `nullglob` set, the `for` loop body never executes and the behavior is unchanged for the wrong reason. Should use `compgen -G` or explicit directory test instead.

5. **`stat -c %Y` Linux-only** (line 38): `stat -c %Y` is a GNU coreutils extension. On BSD/macOS `stat -f %m` is the equivalent. Same portability class as finding 1.

6. **Silent `agents.yaml` missing produces false-OK** (lines 12–13): `configured_agent_ids` exits 0 with no output when `agents.yaml` is missing. The outer `for` loop runs zero iterations, no breaches are detected, and the script prints "OK: no SLA breaches" — a false green during configuration failures. Same pattern as `agent-exec-watchdog.sh` (cycle-6 review) and `monitor-sessions.sh` (cycle-7 review).

7. **Exit code does not indicate breach** (lines 101–103): `breach` is printed to stdout but the script always exits 0. Callers (`notify-pending.sh` line 45) must parse stdout with `grep -c '^BREACH'`. This contract is not documented in the script header. If a caller checks only exit code, it will never detect breaches.

## Suggested minimal diff direction

**Finding 1 + 5 (portability):**
```bash
oldest_inbox_item() {
  local agent="$1" dir="sessions/${agent}/inbox"
  [ -d "$dir" ] || { echo ""; return; }
  # POSIX-compatible: use ls -t instead of find -printf
  find "$dir" -mindepth 1 -maxdepth 1 -type d | while read -r d; do
    printf '%s %s\n' "$(stat -c %Y "$d" 2>/dev/null || stat -f %m "$d" 2>/dev/null || echo 0)" "$(basename "$d")"
  done | sort -n | head -n 1 | awk '{print $2}'
}
```
Or simpler: use `ls -dt sessions/${agent}/inbox/*/` and parse the last directory.

**Finding 2 (all items, not just oldest):**
```bash
# Replace: item="$(oldest_inbox_item "$agent")"
# With a loop over all inbox items:
for item_dir in sessions/${agent}/inbox/*/; do
  item="$(basename "$item_dir")"
  ...
done
```

**Finding 7 (exit code contract):** Add to script header comment:
```bash
# Exit code: always 0. Parse stdout for lines starting with 'BREACH'.
# Use: breach_count="$(./scripts/sla-report.sh | grep -c '^BREACH' || true)"
```

## Follow-up tasks to queue

**Task A → `dev-infra` (ROI: 7):**
File: `scripts/sla-report.sh`
Fix: (1) replace `find -printf '%T@'` with a portable alternative (finding 1); (2) replace `stat -c %Y` with a portable stat call (finding 5); (3) add a stderr warning when `agents.yaml` is missing instead of silent SystemExit(0) (finding 6); (4) add a comment documenting the exit-code contract (finding 7).
Verify: run `./scripts/sla-report.sh` in a fresh checkout; confirm it outputs either "OK: no SLA breaches" or BREACH lines; confirm it does not crash when `agents.yaml` is absent (should print a warning to stderr and exit 0).

**Task B → `dev-infra` (ROI: 6):**
File: `scripts/sla-report.sh`
Fix: extend the per-agent check loop (lines 74–97) to iterate over all inbox items (not just the oldest) and report a breach for each item that exceeds `SLA_OUTBOX` without an outbox file (finding 2).
Verify: create two inbox items for a test agent where the older one has an outbox file and the newer does not; confirm the script reports a breach for the newer item.

## ROI estimate
- ROI: 7
- Rationale: The portability bug (finding 1) means `sla-report.sh` silently produces no breaches on non-Linux systems, completely defeating the SLA monitoring system. The single-item-only check (finding 2) means multi-item backlog situations are invisible to breach detection. Both directly undermine the reliability of the monitoring and alerting pipeline that the CEO depends on.
