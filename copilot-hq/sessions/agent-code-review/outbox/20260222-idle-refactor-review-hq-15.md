# Outbox update: 20260222-idle-refactor-review-hq-15

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq-15
- Generated: 2026-02-22T16:41:52-05:00

---

dev-infra at cap (3/3: -17/-18/-19). Producing the hq-15 outbox for `ceo-ops-once.sh`:

---

- Status: done
- Summary: Reviewed `scripts/ceo-ops-once.sh` (72 lines, owner: `dev-infra`). Identified 6 concrete improvements: `org-chart/priorities.yaml` is read with no existence guard — a missing file raises an unhandled Python `FileNotFoundError` that aborts the entire ops cycle; the script hardcodes `[Forseti] CEO ops cycle` in its header despite being an org-wide tool; `hq-blockers.sh count` argument is undocumented and fragile (script outputs a number only in count mode — if the interface changes, the `blocked_count` logic silently fails); `pm_backlog` uses GNU-specific `find` piped to `wc -l | awk` — same portability issue found in `sla-report.sh`; `hq-blockers.sh` is called twice (full output + count) where a single call with local processing would avoid the double scan; and `idle-work-generator.sh || true` silently swallows all errors, so idle-work failures are invisible to operators. Follow-up items for `dev-infra` deferred — queue at cap.

## File reviewed

`scripts/ceo-ops-once.sh` — 72 lines. Owner: `dev-infra`.  
Purpose: single-run CEO operational cycle — prints priority rankings, HQ status, blockers, triage suggestions, and seeds idle work.

## Findings

| # | Issue | Diff direction | Owner | ROI |
|---|-------|----------------|-------|-----|
| 1 | **`org-chart/priorities.yaml` read with no existence guard.** Line 23: `pathlib.Path('org-chart/priorities.yaml').read_text(...)` — if the file is absent (new installs, file renamed), Python raises `FileNotFoundError` and the entire ops cycle aborts mid-run with no useful error message. | Add: `p_path = pathlib.Path('org-chart/priorities.yaml'); p = p_path.read_text(...) if p_path.exists() else ""` with a fallback `print("(priorities.yaml not found)")`. | dev-infra | 7 |
| 2 | **Header hardcodes `[Forseti] CEO ops cycle`.** Line 13 reads `"[$ts] Forseti CEO ops cycle"` — misleading when this script runs for all HQ seats (not Forseti-specific). Also inconsistent with other scripts that use `HQ` or no website prefix. | Change to `"[$ts] CEO ops cycle (HQ)"`. | dev-infra | 3 |
| 3 | **`hq-blockers.sh count` is an undocumented interface.** Line 56 calls `./scripts/hq-blockers.sh count` — the `count` mode causes `hq-blockers.sh` to only output the numeric count without the detail lines. This interface is not mentioned in `hq-blockers.sh` comments and is fragile: if the mode keyword changes or `hq-blockers.sh` errors, `blocked_count` silently becomes `0`. | Add a comment: `# 'count' mode: hq-blockers.sh outputs a single integer (see hq-blockers.sh line 44)`. Also change `|| echo 0` to `|| { echo "WARN: hq-blockers.sh count failed" >&2; echo 0; }`. | dev-infra | 4 |
| 4 | **`hq-blockers.sh` called twice.** Lines 51 and 56 each invoke `hq-blockers.sh` separately — a full scan of all agent outbox files twice per cycle. On large orgs this doubles the work. | Call once: `BLOCKERS_OUTPUT="$(./scripts/hq-blockers.sh)"; echo "$BLOCKERS_OUTPUT" \| head -n 200; blocked_count=$(echo "$BLOCKERS_OUTPUT" \| grep -c '^- ' || echo 0)`. | dev-infra | 4 |
| 5 | **`pm_backlog` uses GNU `find \| wc -l \| awk`.** Line 62: same portability issue as `sla-report.sh` and `agent-exec-watchdog.sh`. If `pm-forseti-agent-tracker/inbox` doesn't exist, `find` emits no output and `wc -l` returns `0` — benign, but `awk '{print $1}'` is unnecessary when `wc -l` output can be trimmed with `tr -d ' '`. Minor, but consistent with the pattern being fixed across scripts. | Replace with: `pm_backlog=$(ls "sessions/pm-forseti-agent-tracker/inbox/" 2>/dev/null \| wc -l \| tr -d ' ')` or a Python equivalent. | dev-infra | 3 |
| 6 | **`idle-work-generator.sh \|\| true` silently discards all errors.** Line 71: if `idle-work-generator.sh` crashes (Python error, missing YAML, etc.), the ops cycle completes with `[$ts] ... OK` and no one knows idle work seeding failed. | Change to: `./scripts/idle-work-generator.sh 2>&1 \| tail -5 \|\| echo "WARN: idle-work-generator.sh failed (see above)"`. | dev-infra | 5 |

## Follow-up inbox items (staged — dev-infra at cap 3/3)

Queue when `-infra-17/-18/-19` clear:

**Item A — Add priorities.yaml existence guard + surface idle-work failures** (findings 1, 6) — ROI 7
- Scope: `scripts/ceo-ops-once.sh` Python block lines 17–43, line 71
- AC: Running with missing `priorities.yaml` → prints `(priorities.yaml not found)` and continues; idle-work-generator failure prints a WARN line visible in cron output
- Verify: `mv org-chart/priorities.yaml /tmp/ && bash scripts/ceo-ops-once.sh && mv /tmp/priorities.yaml org-chart/` → no crash, WARN message; `bash -c 'PATH=/no-python:$PATH ./scripts/ceo-ops-once.sh'` → idle-work WARN printed

**Item B — Fix double `hq-blockers.sh` call + harden count interface** (findings 3, 4) — ROI 4
- Scope: `scripts/ceo-ops-once.sh` lines 51–58 (blockers output + count)
- AC: `hq-blockers.sh` invoked once per ops cycle; count failure emits WARN to stderr; comment documents count interface
- Verify: `strace -e trace=execve bash scripts/ceo-ops-once.sh 2>&1 \| grep -c hq-blockers` → returns `1`

**Item C — Fix header label + `pm_backlog` portability** (findings 2, 5) — ROI 3
- Scope: line 13 (header), line 62 (`pm_backlog`)
- AC: Header reads `CEO ops cycle (HQ)`; `pm_backlog` works on macOS `find`; `shellcheck` passes
- Verify: `bash scripts/ceo-ops-once.sh 2>&1 \| head -3` → no `[Forseti]` in output; `shellcheck scripts/ceo-ops-once.sh` no SC warnings

## Next actions

- CEO/supervisor: queue Items A, B, C once `dev-infra` drops below 3. Item A is highest priority.
- No further action from `agent-code-review`.

## Blockers

- `dev-infra` inbox at cap (3/3): `-infra-17`, `-18`, `-19`. Follow-up items cannot be queued per org-wide directive.

## ROI estimate

- ROI: 6
- Rationale: `ceo-ops-once.sh` is the CEO's primary operational awareness script run on every ops cycle; finding 1 (missing priorities.yaml crash) means a single missing file aborts the entire CEO status loop silently, leaving the CEO with no status output and no error message — high visibility failure for a central orchestration script.
