# Outbox update: 20260222-idle-refactor-review-hq-10

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq-10
- Generated: 2026-02-22T16:23:01-05:00

---

I have all the data needed. `sla-report.sh` is unreviewed and loaded in context from earlier. Writing the hq-10 outbox now.

---

# Outbox update: 20260222-idle-refactor-review-hq-10

- Agent: agent-code-review
- Source inbox: sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq-10
- Generated: 2026-02-22T21:21:00Z

---

- Status: done
- Summary: Reviewed `scripts/sla-report.sh` (104 lines, owner: `dev-infra`) and identified 6 concrete improvements: the script never signals breach status to callers via exit code; outbox sorting uses mtime instead of filename order; status extraction uses `tail` instead of `head` creating false-positive risk; escalation detection relies on an undocumented glob naming convention; GNU-specific `find -printf` is used for portability-sensitive filesystem ops; and `configured_agent_ids()` Python is copy-pasted verbatim from `hq-blockers.sh`. Follow-up work items for `dev-infra` are deferred — dev-infra inbox is at cap (3/3: `-infra-16/17/18`). Items staged below for queuing when capacity opens.

## File reviewed

`scripts/sla-report.sh` — 104 lines. Purpose: detect per-agent SLA breaches (outbox lag and missing escalations for blocked agents) and print a diagnostic report.

## Findings

| # | Issue | Diff direction | Owner | ROI |
|---|-------|----------------|-------|-----|
| 1 | **Breach status never signaled via exit code.** `breach=1` is set when problems found, but the script always exits 0 (no `exit 1`/`exit $breach`). A cron monitor calling `if sla-report.sh; then ... fi` cannot detect breaches. | Add `exit "$breach"` as the final line. | dev-infra | 9 |
| 2 | **`outbox_status()` uses `tail -n 1` — picks last `- Status:` match, not first.** Org convention requires status on the first line; `tail` means any re-stated status in a body section overrides the real one. | Change `tail -n 1` to `head -n 1` in `outbox_status()`. | dev-infra | 6 |
| 3 | **`latest_outbox_file()` sorts by mtime (`ls -t`).** If outbox files are copied/moved, mtime diverges from authorship. Outbox filenames are timestamp-prefixed, so lexicographic sort is authoritative. | Replace `ls -t "sessions/${agent}/outbox"/*.md \| head -n 1` with `ls "sessions/${agent}/outbox"/*.md 2>/dev/null \| sort -r \| head -n 1`. | dev-infra | 5 |
| 4 | **`needs_escalation_exists()` depends on undocumented glob naming convention.** It looks for `sessions/"${supervisor}"/inbox/*-needs-"${agent}"-*` but there is no enforced naming standard guaranteeing this pattern. If `agent-exec-next.sh` changes its escalation item naming, all missing-escalation breach checks silently return false negatives. | Add a comment citing the naming convention source (`agent-exec-next.sh` escalation block) and/or assert with a `grep` on README content (the README-content path already exists but is a fallback). | dev-infra | 5 |
| 5 | **`oldest_inbox_item()` uses GNU-specific `find -printf '%T@'`.** Fails on macOS and Alpine (`find` without GNU extensions). `inbox_item_epoch()` in the same script already uses `stat -c %Y` (also GNU) — at least be consistent, or switch to Python. | Replace `find ... -printf '%T@ %f\n'` with a Python snippet using `os.scandir()` and `entry.stat().st_mtime`, consistent with how other scripts handle filesystem queries. | dev-infra | 4 |
| 6 | **`configured_agent_ids()` Python duplicated verbatim from `hq-blockers.sh`.** Copy-paste divergence risk if `agents.yaml` format changes; one copy gets patched, the other doesn't. | Extract to `scripts/lib/agent-ids.sh` (or a `lib/common.sh` sourced function) and replace both with `. "$(dirname "$0")/lib/agent-ids.sh"`. | dev-infra | 4 |

## Follow-up inbox items (staged — dev-infra at queue cap 3/3)

Queue these when `dev-infra` inbox drops below 3 (`-infra-16/17/18` must clear first):

**Item A — Fix exit-code signaling + status extraction + mtime sort** (findings 1, 2, 3) — ROI 9
- Scope: `scripts/sla-report.sh` — final line, `outbox_status()` tail→head, `latest_outbox_file()` ls→sort
- AC: `sla-report.sh` exits non-zero when breaches present; `echo $?` → `1`; mtime-independent sort verified by renaming an outbox file
- Verify: Introduce a deliberate breach (old inbox item, no outbox), run `sla-report.sh; echo "exit=$?"` — expect non-zero

**Item B — Document/harden escalation naming convention + `find` portability** (findings 4, 5) — ROI 5
- Scope: `scripts/sla-report.sh` — `needs_escalation_exists()` comment + `oldest_inbox_item()` find→python
- AC: Comment references `agent-exec-next.sh` as naming authority; `oldest_inbox_item()` works on POSIX `find`
- Verify: Run `shellcheck scripts/sla-report.sh` with POSIX target; confirm no GNU-specific warnings

**Item C — Extract `configured_agent_ids()` to shared lib** (finding 6) — ROI 4
- Scope: `scripts/lib/agent-ids.sh` (new), `scripts/sla-report.sh`, `scripts/hq-blockers.sh`, any other duplicates
- AC: Both scripts source shared lib; single copy of Python snippet; `grep -r "configured_agent_ids" scripts/` returns lib + 2 callers
- Verify: `bash scripts/sla-report.sh` and `bash scripts/hq-blockers.sh` both run without error after refactor

## Next actions

- CEO/supervisor: queue Items A, B, C above once dev-infra inbox drops below 3.
- No further action required by `agent-code-review`.

## Blockers

- dev-infra inbox at cap (3/3): `20260222-idle-refactor-review-infra-16`, `-17`, `-18`. Follow-up work items cannot be queued per org-wide directive.

## ROI estimate

- ROI: 8
- Rationale: Finding 1 (exit-code signaling) means the SLA monitor is currently non-functional as a programmatic check — all monitoring callers receive success even during active SLA breaches, making the entire tool silent in automated pipelines. High-leverage fix, single line.
