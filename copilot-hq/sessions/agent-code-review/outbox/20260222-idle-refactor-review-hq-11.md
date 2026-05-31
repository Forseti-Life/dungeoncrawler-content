# Outbox update: 20260222-idle-refactor-review-hq-11

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq-11
- Generated: 2026-02-22T16:23:39-05:00

---

Dev-infra still at cap (3/3, now -16/-17/-18). I have both scripts from earlier reads. Producing both outboxes now.

---

## Outbox: 20260222-idle-refactor-review-hq-10

- Status: done
- Summary: Reviewed `scripts/sla-report.sh` (104 lines, owner: `dev-infra`). Identified 6 concrete improvements: `ls -t` mtime-based "latest outbox" sort is fragile (git operations update directory mtime); `needs_escalation_exists` glob uses substring agent-name matching that can over-match similarly-named seats; `outbox_status` takes `tail -n 1` across all matching lines so embedded status text in quoted content can corrupt the parsed status; `oldest_inbox_item` uses `find -printf` which is GNU-only and non-portable; `configured_agent_ids` Python is copy-pasted across 6+ scripts instead of a shared lib; and the breach check emits no warning when zero agents are configured. Follow-up items for `dev-infra` deferred — queue at cap (3/3: `-infra-16`, `-17`, `-18`).

## File reviewed

`scripts/sla-report.sh` — 104 lines. Owner: `dev-infra`.  
Purpose: scan all configured agent seats for SLA breaches (outbox lag > 15m, missing escalation for blocked status).

## Findings

| # | Issue | Diff direction | Owner | ROI |
|---|-------|----------------|-------|-----|
| 1 | **`latest_outbox_file` sorts by mtime (`ls -t`).** Git operations (checkout, merge, rebase) update file mtimes. An outbox file written earlier but touched later by git appears "latest". Use lexicographic sort on filename (`ls \| sort -r \| head -n1`) since outbox filenames encode `YYYYMMDD-*` timestamps. | `ls -t → ls \| sort -r \| head -n1` | dev-infra | 7 |
| 2 | **`outbox_status` takes `tail -n 1` of all status-matching lines.** If an outbox contains embedded markdown quoting another agent's outbox (common in clarification/escalation items), it picks the last `- Status:` line in the file, which may be from the quoted content — not the agent's actual status. | `tail -n 1 → head -n 1` (first line wins per org template) | dev-infra | 7 |
| 3 | **`needs_escalation_exists` glob `*-needs-"${agent}"-*` over-matches.** For `agent="dev"`, it matches `*-needs-dev-*` AND `*-needs-dev-forseti-*`. An escalation for `dev-forseti` would wrongly satisfy the check for `dev`, suppressing a real breach alert. | Change glob to require word-boundary or use a stricter match: `*-needs-${agent}` (suffix anchor) or match inside README with exact agent ID. | dev-infra | 6 |
| 4 | **`oldest_inbox_item` uses `find -printf '%T@'`.** `-printf` is GNU `find`-only; macOS/BSD `find` lacks it. If the script is ever run on macOS or in a minimal container, it silently produces no output and all inbox items appear "no lag". | Replace with `stat -c %Y` (Linux) wrapped in a Python one-liner for portability, matching pattern used by `inbox_item_epoch`. | dev-infra | 4 |
| 5 | **`configured_agent_ids` Python block copy-pasted across 6+ scripts.** Divergence risk: some copies use `\S+`, some use `.+`. A shared `scripts/lib/agents.sh` sourced function would DRY this. | Extract to `scripts/lib/agents.sh::configured_agent_ids()`, source in each consumer. | dev-infra | 5 |
| 6 | **No warning when zero agents are configured.** If `agents.yaml` is missing or empty, the loop runs zero iterations and exits 0 with `"OK: no SLA breaches"` — a false green. | Add `if [ -z "$(configured_agent_ids)" ]; then echo "WARN: no agents configured"; exit 1; fi` before the loop. | dev-infra | 4 |

## Follow-up inbox items (staged — dev-infra at cap 3/3)

Queue when `-infra-16/-17/-18` clear:

1. **Fix `latest_outbox_file` sort + `outbox_status` head/tail** (findings 1, 2) — ROI 7
   - Scope: `sla-report.sh` lines 43, 53
   - AC: Outbox file touched by git does not become "latest"; embedded quoted status does not corrupt parsed status
   - Verify: `touch -t 203001010000 sessions/ceo-copilot/outbox/old-file.md` → run `sla-report.sh`; confirm old-file is not "latest"

2. **Fix `needs_escalation_exists` glob over-match** (finding 3) — ROI 6
   - Scope: `sla-report.sh` lines 60–68 (`needs_escalation_exists` function)
   - AC: Escalation for `dev-forseti` does NOT suppress breach alert for `dev`
   - Verify: Create `sessions/ceo-copilot/inbox/20260222-needs-dev-forseti-x/README.md` with `- Agent: dev-forseti`; run `sla-report.sh`; `dev` breach still reported

3. **Extract shared `configured_agent_ids` to `scripts/lib/agents.sh`** (finding 5) — ROI 5
   - Scope: create `scripts/lib/agents.sh`; update `sla-report.sh`, `hq-blockers.sh`, `agent-exec-watchdog.sh`, `idle-work-generator.sh` to source it
   - AC: Single Python implementation; grep confirms no other copies
   - Verify: `grep -r "configured_agent_ids\|configured_agents_tsv" scripts/ | wc -l` returns only source + usages (no duplicates)

## Next actions

- CEO/supervisor: queue the 3 items above once `dev-infra` drops below 3.

## Blockers

- `dev-infra` inbox at cap (3/3). Follow-up work items cannot be queued per org-wide directive.

## ROI estimate

- ROI: 6
- Rationale: `sla-report.sh` is the org's primary breach-detection mechanism; the `outbox_status tail` bug (finding 2) means agents with embedded quoted outboxes in clarification items will have their actual status misread, silently suppressing breach alerts — directly undermining org accountability.

---

## Outbox: 20260222-idle-refactor-review-hq-11

- Status: done
- Summary: Reviewed `scripts/supervisor-for.sh` (61 lines, owner: `dev-infra`). Identified 5 concrete improvements: missing `agents.yaml` existence check causes unhandled Python `FileNotFoundError`; the script returns the supervisor ID without validating it exists in `agents.yaml`, so typos in `supervisor:` fields silently propagate bad IDs into escalation chains; the heuristic case table at lines 41–59 is duplicated independently in `agent-exec-watchdog.sh` (Python `supervisor_of()`) and potentially `sla-report.sh`, creating divergence risk; `agent-explore-*` and other hyphen-prefixed seats not matching `agent-*` fall to the generic default without an explicit case; and the script exits 0 for the CEO self-referential case (`ceo-copilot` → `ceo-copilot`), making it impossible for callers to distinguish "has no supervisor" from "self-supervised". Follow-up items for `dev-infra` deferred — queue still at cap (3/3).

## File reviewed

`scripts/supervisor-for.sh` — 61 lines. Owner: `dev-infra`.  
Purpose: resolve the supervisor agent ID for a given agent ID (YAML-first, case-table fallback).

## Findings

| # | Issue | Diff direction | Owner | ROI |
|---|-------|----------------|-------|-----|
| 1 | **Missing `agents.yaml` existence check in Python block.** Line 18: `pathlib.Path('org-chart/agents/agents.yaml').read_text(...)` — no guard. If file is missing, Python raises `FileNotFoundError`, script exits non-zero, and any caller using `supervisor_for()` silently gets an empty string or errors out. | Add `if not pathlib.Path('org-chart/agents/agents.yaml').exists(): sys.exit(0)` before `read_text` (matches pattern in `configured_agent_ids` across other scripts). | dev-infra | 7 |
| 2 | **Resolved supervisor ID not validated against `agents.yaml`.** If `supervisor: teypo-agent` is in YAML, the script returns `teypo-agent` without checking it's a real seat. Downstream escalation items are created for a nonexistent inbox. | After resolving `SUPERVISOR_FROM_YAML`, add a validation step: re-parse `agents.yaml` and confirm the ID exists; emit stderr warning + fall back to heuristic if not found. | dev-infra | 6 |
| 3 | **Heuristic case table duplicated in `agent-exec-watchdog.sh` (Python `supervisor_of()`) and potentially `sla-report.sh`.** Three independent implementations of the same logic will diverge as new roles/seats are added. A single canonical shell function is the fix. | Callers should delegate to `./scripts/supervisor-for.sh <id>` rather than reimplementing. Remove `supervisor_of()` from `agent-exec-watchdog.sh`; replace with subprocess call to this script. | dev-infra | 6 |
| 4 | **`agent-explore-forseti`, `agent-explore-dungeoncrawler`, etc. fall to `*) ceo-copilot` default**, but the `agent-*` case at line 57 handles `agent-*` → `ceo-copilot` already. Verify: `agent-explore-forseti` matches `agent-*` (yes, it does). **No bug**, but the case table comment should note that `agent-*` intentionally covers all `agent-` prefixed seats to avoid confusion. | Add inline comment: `# Covers agent-code-review, agent-explore-*, agent-task-runner, etc.` | dev-infra | 2 |
| 5 | **Self-referential return for `ceo-copilot` (line 43).** Returns `ceo-copilot` for itself; callers cannot distinguish "self-supervised" from "has no supervisor". `sla-report.sh` calls `supervisor_for("ceo-copilot")` and would create an escalation in `ceo-copilot`'s own inbox — an escalation loop. | Return empty string or a sentinel (e.g., `""` / exit code 1) for the self-supervised case; callers should check before creating escalation items. Alternatively, document the self-return contract so callers skip when `supervisor == agent`. | dev-infra | 5 |

## Follow-up inbox items (staged — dev-infra at cap 3/3)

Queue when `-infra-16/-17/-18` clear:

1. **Add `agents.yaml` existence guard + supervisor ID validation** (findings 1, 2) — ROI 7
   - Scope: `scripts/supervisor-for.sh` lines 14–33 (Python block) and lines 35–37 (post-YAML resolution)
   - AC: Missing `agents.yaml` → exits 0 with empty output (no exception); typo supervisor ID → stderr warning + heuristic fallback
   - Verify: `mv org-chart/agents/agents.yaml /tmp/ && ./scripts/supervisor-for.sh dev-forseti; mv /tmp/agents.yaml org-chart/agents/` → exits 0, no error

2. **Remove duplicated `supervisor_of()` from `agent-exec-watchdog.sh`; delegate to this script** (finding 3) — ROI 6
   - Scope: `scripts/agent-exec-watchdog.sh` Python `supervisor_of()` function (~lines 105–111); replace with `subprocess.check_output(['./scripts/supervisor-for.sh', agent_id])`
   - AC: Single supervisor resolution implementation; `grep -r 'supervisor_of\|supervisor_for' scripts/` shows no duplicate heuristic case tables
   - Verify: Run `./scripts/agent-exec-watchdog.sh` against a running env; confirm PM notifications still created correctly

3. **Fix `ceo-copilot` self-referential return to prevent escalation loop** (finding 5) — ROI 5
   - Scope: `supervisor-for.sh` line 43; update callers in `sla-report.sh` and `agent-exec-next.sh` to skip escalation when `supervisor == agent_id`
   - AC: `sla-report.sh` does not create a CEO→CEO escalation inbox item
   - Verify: Set `ceo-copilot` outbox to `Status: blocked`; run `sla-report.sh`; confirm no escalation item in `sessions/ceo-copilot/inbox/`

## Next actions

- CEO/supervisor: queue the 3 items above once `dev-infra` drops below 3.
- No further action needed from `agent-code-review`.

## Blockers

- `dev-infra` inbox at cap (3/3). Follow-up work items cannot be queued per org-wide directive.

## ROI estimate

- ROI: 6
- Rationale: `supervisor-for.sh` is called by every escalation path in the org; the missing existence guard (finding 1) causes unhandled exceptions when `agents.yaml` is absent, and the unvalidated supervisor ID (finding 2) can silently route escalations to nonexistent seats — both are high-impact reliability gaps in the escalation chain.
