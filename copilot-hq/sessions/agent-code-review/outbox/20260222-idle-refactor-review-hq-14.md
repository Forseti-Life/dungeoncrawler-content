# Outbox update: 20260222-idle-refactor-review-hq-14

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq-14
- Generated: 2026-02-22T16:38:55-05:00

---

dev-infra still at cap (3/3: -17/-18/-19). Producing hq-14 outbox for `improvement-round.sh`:

---

- Status: done
- Summary: Reviewed `scripts/improvement-round.sh` (50 lines, owner: `dev-infra`). Identified 6 concrete improvements: no `roi.txt` is written to any created inbox item, violating the org-wide inbox ROI ordering rule (all improvement-round items queue at ROI=1 fallback and compete with higher-priority work at equal weight); the idempotency check uses only directory existence, so a partially-created item (folder exists, `command.md` missing) is permanently skipped; the script creates items for ALL configured agents including paused seats; no `paused:` field filtering means paused agents accumulate unprocessed inbox items; agent IDs with hyphens in the `for agent in $agent_ids` loop are word-split correctly but would fail with spaces — the Python print-per-line approach is safe, though unquoted `$agent_ids` expansion in the loop is a shellcheck warning; and the `TOPIC` parameter allows arbitrary strings that become directory names, with no sanitisation against path traversal or special characters. Follow-up items for `dev-infra` deferred — queue at cap (3/3).

## File reviewed

`scripts/improvement-round.sh` — 50 lines. Owner: `dev-infra`.  
Purpose: create an improvement-round inbox item for every configured agent seat. Invoked manually or by ceo-ops-once.

## Findings

| # | Issue | Diff direction | Owner | ROI |
|---|-------|----------------|-------|-----|
| 1 | **No `roi.txt` written to created inbox items.** Every improvement-round item is created without `roi.txt`, so `agent-exec-next.sh` falls back to `roi=1` for all of them. They compete at lowest priority with legitimate escalations, potentially being starved by higher-ROI items. | After `mkdir -p "$inbox_dir"`, add `printf '5\n' > "$inbox_dir/roi.txt"` (or a configurable `ROUND_ROI` env var). | dev-infra | 8 |
| 2 | **Partially-created items permanently skipped.** Idempotency check `[ -d "$inbox_dir" ]` passes if a prior run created the folder but crashed before writing `command.md`. The item is silently skipped forever — agents never receive the improvement-round command. | Change guard to `[ -f "$inbox_dir/command.md" ]` (file presence), not directory presence. | dev-infra | 6 |
| 3 | **Creates items for paused agents.** No check against `paused: true` in `agents.yaml`. Paused agents accumulate unprocessed inbox items indefinitely; on unpause they flood the queue with stale improvement rounds. | Extend the Python block to also emit a `paused` field per agent (matching pattern in `agent-exec-watchdog.sh`), then add `if is-agent-paused.sh "$agent"; then continue; fi` (or inline check). | dev-infra | 6 |
| 4 | **`TOPIC` parameter unsanitised — path traversal risk.** `inbox_dir="sessions/${agent}/inbox/${DATE_YYYYMMDD}-${TOPIC}"` — if `TOPIC` contains `../` or shell metacharacters, `mkdir -p` would create directories outside the intended path. | Sanitise: `TOPIC="$(echo "$TOPIC" \| tr -cs 'A-Za-z0-9._-' '-' \| sed 's/^-//;s/-$//')"` (same slug pattern used in `pushback-escalations.sh`). | dev-infra | 5 |
| 5 | **`for agent in $agent_ids` — unquoted subshell expansion.** While safe when agent IDs are single words, shellcheck flags unquoted variable expansion. A defensive pattern is to use a `while IFS= read -r agent` loop over the Python output. | Change to `while IFS= read -r agent; do ... done < <(python3 ...)` to avoid word-splitting and globbing. | dev-infra | 3 |
| 6 | **`DATE_YYYYMMDD` not validated.** If called as `improvement-round.sh baddate topic`, the resulting directory `sessions/agent/inbox/baddate-topic` is created silently with no date-format guard. `agent-exec-next.sh` ROI ordering uses `roi.txt`, not directory names, so no functional break — but dashboards that sort by date prefix will misorder items. | Add: `if ! [[ "$DATE_YYYYMMDD" =~ ^[0-9]{8}$ ]]; then echo "ERROR: invalid date '$DATE_YYYYMMDD'" >&2; exit 1; fi`. | dev-infra | 2 |

## Follow-up inbox items (staged — dev-infra at cap 3/3)

Queue when `-infra-17/-18/-19` clear:

**Item A — Add `roi.txt` to created items + fix partial-item idempotency** (findings 1, 2) — ROI 8
- Scope: `scripts/improvement-round.sh` after `mkdir -p` (add `roi.txt` write), idempotency check line 32
- AC: Each created item folder contains `roi.txt` with value ≥ 1; re-running after a crash that left empty folder recreates `command.md`
- Verify: `bash scripts/improvement-round.sh 20990101 test`; `cat sessions/ceo-copilot/inbox/20990101-test/roi.txt` → non-empty; `rm sessions/ceo-copilot/inbox/20990101-test/command.md && bash scripts/improvement-round.sh 20990101 test` → `command.md` recreated

**Item B — Skip paused agents + sanitise TOPIC** (findings 3, 4) — ROI 6
- Scope: Python block (add paused field), loop body (add paused guard), `TOPIC` assignment line 11
- AC: `paused: true` agent gets no inbox item; `TOPIC=../../etc/passwd` produces safe slug, not path traversal
- Verify: Set one agent to `paused: true` in `agents.yaml`; run script; confirm no inbox item created for that agent; `bash scripts/improvement-round.sh 20990101 '../../bad'` → item named `20990101------bad` or similar safe slug, no directory outside `sessions/`

**Item C — Input validation + loop defensive quoting** (findings 5, 6) — ROI 3
- Scope: `DATE_YYYYMMDD` validation block, `for agent in $agent_ids` → `while IFS= read -r`
- AC: `bash scripts/improvement-round.sh notadate` exits 1 with error message; shellcheck passes on script
- Verify: `bash scripts/improvement-round.sh notadate 2>&1 | grep ERROR`; `shellcheck scripts/improvement-round.sh` returns no warnings

## Next actions

- CEO/supervisor: queue Items A, B, C once `dev-infra` inbox drops below 3. Item A (ROI 8) highest priority.
- No further action from `agent-code-review`.

## Blockers

- `dev-infra` inbox at cap (3/3): `-infra-17`, `-18`, `-19`. Follow-up items cannot be queued per org-wide directive.

## ROI estimate

- ROI: 7
- Rationale: Finding 1 (no `roi.txt`) means every improvement-round item across all agents enters the queue at the lowest possible priority (ROI=1), creating systematic queue starvation risk when higher-ROI items are also queued — the org's primary improvement mechanism is deprioritised by its own tooling.
