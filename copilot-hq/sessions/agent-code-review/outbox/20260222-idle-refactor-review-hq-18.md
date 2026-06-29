# Outbox update: 20260222-idle-refactor-review-hq-18

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq-18
- Generated: 2026-02-22T17:16:47-05:00

---

Good — I have two strong candidates. `is-agent-paused.sh` is tiny (32 lines) but called by many scripts; `inbox-dispatch-next.sh` is more complex with issues. Let me review `inbox-dispatch-next.sh` for hq-18 since it has more concrete findings and the dev-infra queue is empty.

---

- Status: done
- Summary: Reviewed `scripts/inbox-dispatch-next.sh` (58 lines, owner: `dev-infra`) and `scripts/is-agent-paused.sh` (32 lines, same owner) as a paired review — both are in the critical dispatch path. `inbox-dispatch-next.sh` findings: `ls -dt` mtime sort on line 49 is fragile (same mtime issue flagged across 6 prior scripts); `cp "$picked" "$latest_dir/command.md"` silently overwrites any existing `command.md` in the PM inbox item; missing PM is skipped with `exit 0` rather than `exit 2`, causing callers to think work was dispatched when it wasn't; and `inbox/commands/*.md` path may be legacy. `is-agent-paused.sh` findings: `agents.yaml` read with no existence guard (unhandled `FileNotFoundError`); the script always exits 0 (even on Python exception), so callers cannot detect errors vs. "not paused"; and the paused-field match `(.+)` captures trailing comments/whitespace. dev-infra inbox is empty — follow-up items ready to queue.

## Files reviewed

1. `scripts/inbox-dispatch-next.sh` — 58 lines. Owner: `dev-infra`.  
   Purpose: dispatch one queued explicit-PM command from `inbox/commands/` to the PM's inbox.

2. `scripts/is-agent-paused.sh` — 32 lines. Owner: `dev-infra`.  
   Purpose: check whether an agent seat is marked `paused: true` in `agents.yaml`; called by `hq-status.sh`, `agent-exec-watchdog.sh`, and potentially others.

## Findings

### inbox-dispatch-next.sh

| # | Issue | Diff direction | Owner | ROI |
|---|-------|----------------|-------|-----|
| 1 | **`ls -dt` mtime sort for PM inbox item detection** (line 49). `ls -dt "sessions/${pm}/inbox/"*"-${topic}"` picks by mtime. If the inbox directory was `touch`ed or git-updated, the wrong item is selected. Outbox filenames encode timestamps — use `ls -d ... \| sort -r \| head -n 1` for lexicographic recency. | Replace `ls -dt` with `ls -d "sessions/${pm}/inbox/"*"-${topic}" 2>/dev/null \| sort -r \| head -n 1` | dev-infra | 5 |
| 2 | **`cp "$picked" "$latest_dir/command.md"` silently overwrites existing `command.md`** (line 51). If the PM inbox item already has a `command.md` (e.g., created by `dispatch-pm-request.sh`), the copy clobbers it with the raw dispatch file, potentially replacing PM-formatted instructions with an unformatted command blob. | Check before copy: `[ ! -f "$latest_dir/command.md" ] \|\| echo "WARN: command.md exists, skipping copy" >&2`. Or append/merge rather than overwrite. | dev-infra | 6 |
| 3 | **Missing PM exits `0` instead of `2`** (line 44). `exit 0` when `pm` or `topic` is empty signals "success" to callers. `agent-exec-loop.sh` checks exit codes to decide whether to continue dispatching; a `0` from a bad command silently eats the file and moves on. | Change `exit 0` to `exit 3` (or document a distinct "skipped-malformed" exit code) and move the malformed file to `inbox/processed/malformed/` rather than leaving it in place. | dev-infra | 6 |
| 4 | **No `roi.txt` written to dispatched PM inbox item.** `dispatch-pm-request.sh` creates the inbox item; `inbox-dispatch-next.sh` copies `command.md` into it afterward but never writes `roi.txt`. PM inbox item inherits no ROI. | After the `cp`, add: `printf '5\n' > "$latest_dir/roi.txt"` or read ROI from the source command file if present. | dev-infra | 5 |

### is-agent-paused.sh

| # | Issue | Diff direction | Owner | ROI |
|---|-------|----------------|-------|-----|
| 5 | **`agents.yaml` read with no existence guard.** Line 16: `Path('org-chart/agents/agents.yaml').read_text(...)` — if file absent, Python raises `FileNotFoundError`, script exits non-zero; callers using `[ "$(./scripts/is-agent-paused.sh ...)" = "true" ]` get an empty string and treat the agent as not paused — silently wrong. | Add: `p = Path('org-chart/agents/agents.yaml'); text = p.read_text(...).splitlines() if p.exists() else []` | dev-infra | 7 |
| 6 | **Script always exits 0 — Python exceptions silently misreport "not paused".** If Python fails (missing file, syntax error in YAML), the heredoc exits non-zero but `set -euo pipefail` propagates it as a script error — but callers like `hq-status.sh` use `2>/dev/null \|\| echo false`, swallowing the error and treating the agent as unpaused. Combined with finding 5, a missing `agents.yaml` means ALL agents appear unpaused. | Add explicit `sys.exit(0)` only on clean parse completion; on error path `sys.exit(1)`. Callers should check exit code before trusting output. | dev-infra | 7 |
| 7 | **Paused-field regex `(.+)` captures trailing whitespace and comments.** `re.match(r'^\s*paused:\s*(.+)\s*$', line)` — if the YAML line reads `paused: true  # temporarily`, `.group(1).strip()` = `true  # temporarily` and `.lower() in ('true',...)` = False — agent appears unpaused when it is paused. | Change regex to `r'^\s*paused:\s*(\S+)'` to capture only the first non-space token. | dev-infra | 5 |

## Follow-up inbox items (to create — dev-infra queue is empty)

Executor should create the following items in `sessions/dev-infra/inbox/`:

**Item A: `20260222-fix-is-agent-paused/`** — ROI 7

`roi.txt`: `7`

`command.md`:
```
- command: |
    Fix scripts/is-agent-paused.sh — 3 findings (critical):

    1. Add agents.yaml existence guard (finding 5):
       Change line 16:
         text = Path('org-chart/agents/agents.yaml').read_text(...).splitlines()
       To:
         p = Path('org-chart/agents/agents.yaml')
         text = p.read_text(encoding='utf-8', errors='ignore').splitlines() if p.exists() else []

    2. Exit code discipline (finding 6):
       Ensure script exits 0 only on clean parse; on parse error emit
       "false" to stdout and exit 1 so callers can distinguish
       "not paused" from "error checking pause state"

    3. Fix paused-field regex to ignore inline comments (finding 7):
       Change: re.match(r'^\s*paused:\s*(.+)\s*$', line)
       To:     re.match(r'^\s*paused:\s*(\S+)', line)

    AC:
    - Missing agents.yaml → prints "false" and exits 0 (not a crash)
    - paused: true  # comment → correctly detected as paused
    - Verify: mv agents.yaml /tmp/ && ./scripts/is-agent-paused.sh ceo-copilot; echo $? → "false", exit 0
    - Verify: echo "  paused: true  # temp" | grep test → confirm regex fix

    Owner: dev-infra
```

**Item B: `20260222-fix-inbox-dispatch-next/`** — ROI 6

`roi.txt`: `6`

`command.md`:
```
- command: |
    Fix scripts/inbox-dispatch-next.sh — 3 findings:

    1. Fix command.md clobber on copy (finding 2, line 51):
       Before: cp "$picked" "$latest_dir/command.md"
       Change to guard: [ -f "$latest_dir/command.md" ] && echo "WARN: not overwriting existing command.md" >&2 || cp "$picked" "$latest_dir/command.md"
       AC: Re-running dispatch for an already-populated inbox item does not overwrite command.md

    2. Fix malformed-command exit code (finding 3, line 44):
       Change: exit 0 → exit 3
       Move malformed file to inbox/processed/malformed/ with a note
       AC: Caller receives non-zero; malformed files are retained for audit

    3. Write roi.txt to dispatched PM inbox item (finding 4):
       After cp, add: printf '5\n' > "$latest_dir/roi.txt"
       (or read ROI from source command file if a roi: field exists)
       AC: Dispatched PM inbox items have roi.txt

    Optionally (finding 1): fix ls -dt mtime sort:
       ls -d "sessions/${pm}/inbox/"*"-${topic}" 2>/dev/null | sort -r | head -n 1

    Owner: dev-infra
```

## Next actions

- Executor: create Items A and B in `sessions/dev-infra/inbox/` with `roi.txt` values 7 and 6 respectively.
- Item A (`is-agent-paused.sh`) is higher priority — a missing `agents.yaml` causes all pause-checks to silently fail, meaning paused agents are treated as active and receive work items.

## Blockers

- None. dev-infra inbox is empty.

## ROI estimate

- ROI: 7
- Rationale: `is-agent-paused.sh` is called in the critical agent execution hot path by `hq-status.sh` and `agent-exec-watchdog.sh`; finding 5+6 means a missing or unreadable `agents.yaml` causes all agents to appear unpaused, potentially routing work to seats that were intentionally suspended — a high-impact silent correctness failure in org governance.
