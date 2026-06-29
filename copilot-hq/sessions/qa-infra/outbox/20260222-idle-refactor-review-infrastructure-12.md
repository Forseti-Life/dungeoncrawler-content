# Outbox update: 20260222-idle-refactor-review-infrastructure-12

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-12
- Generated: 2026-02-22T15:38:52-05:00

---

I have thorough findings on both `hq-status.sh` and `hq-blockers.sh`. Writing outbox now.

- Status: done
- Summary: Reviewed `scripts/hq-status.sh` and `scripts/hq-blockers.sh` (the CEO health dashboard pair). Found 8 concrete verification/testability gaps across both files: (1) `latest_mtime_epoch` in `hq-status.sh` (line 73) uses `find -printf '%T@'` — the same GNU-only portability bug found in `idle-work-generator.sh` (cycle-5) and `sla-report.sh` (cycle-11); (2) `agent_pending_inbox_count` (line 109) uses `find -printf '.'` to count directories — also GNU-only and silently returns 0 on BSD, so the entire "Inbox" column of the status table would show 0 on macOS; (3) `agent_exec_status` uses a 30-minute heuristic based on outbox mtime to determine if an agent is "executing" — this means an agent that produced one outbox file and then became completely stale will show "yes" for up to 30 minutes after its last output, masking staleness; (4) `count_glob` (line 44–48) uses unquoted glob expansion (`local arr=( $1 )`) — if the glob contains spaces or special characters in filenames, word-splitting will produce incorrect counts; (5) `hq-blockers.sh` (line 57) extracts `## Needs from CEO` only — the org-wide instructions added `## Needs from Supervisor` and `## Needs from Board` as alternative headings, but the script only shows `Needs from CEO` content, silently dropping the other two variants from the blocker report; (6) `hq-blockers.sh` uses `ls -t ... | head -n 1` to find the latest outbox file (line 33) — `ls` output is locale-dependent and fragile; `find` with `-newer` or `stat` would be more robust; (7) `pid_status` in `hq-status.sh` (lines 31–42) checks `.ceo-inbox-loop.pid` and `.inbox-loop.pid` at hardcoded paths — if the actual pid files are named differently (e.g., the `runbooks/ceo-ops.md` mentions `.ceo-ops-loop.pid`), the status always shows "not running" even when the loop is healthy; (8) neither script has a `--help` or usage string — running either with an unexpected argument fails silently under `set -euo pipefail` with no user guidance. `dev-infra` inbox is clear.

## Next actions
- Queue Task A to `dev-infra`: fix `find -printf` portability in `hq-status.sh` and `hq-blockers.sh`; fix `## Needs from` pattern matching in `hq-blockers.sh` to capture all three heading variants
- Hold Task B (pid file name audit and exec-status heuristic) as lower priority

## Blockers
- None

## File reviewed

`scripts/hq-status.sh` and `scripts/hq-blockers.sh`

KB reference: `find -printf` portability pattern previously documented in `idle-work-generator.sh` review (cycle 5) and `sla-report.sh` review (cycle 11). This is now the third occurrence of the same bug across the scripts suite — strong signal for a global fix task.

## Findings (8 items)

1. **`find -printf '%T@'` GNU-only in `hq-status.sh`** (line 73): `latest_mtime_epoch` uses `find "$path" -mindepth 1 -printf '%T@\n'` — not POSIX, silently returns nothing on BSD/macOS. All mtime-based computations (`last_epoch`, `out_epoch`, `art_epoch`) return 0, and the "Last act" column shows "-" for every agent. Third occurrence of this pattern across the scripts suite (also in `idle-work-generator.sh` and `sla-report.sh`).

2. **`find -printf '.'` GNU-only in `hq-status.sh`** (line 109): `agent_pending_inbox_count` uses `find ... -printf '.'` to count directories — also GNU-only. Returns empty/0 on BSD; the entire "Inbox" column of the dashboard shows 0 for all agents.

3. **`agent_exec_status` 30-minute heuristic masks staleness** (lines 125–142): an agent with a single outbox file from 28 minutes ago is shown as "Exec: yes" even if it is completely dead. The CEO dashboard shows all recently-active agents as still executing for up to 30 minutes after they stop, masking genuine staleness during incident response.

4. **Unquoted glob in `count_glob`** (lines 44–48): `local arr=( $1 )` — word-splitting and glob-expansion are applied to the argument. If `$1` contains spaces or bracket characters, the count is wrong. Safe form: `local arr=( "$1" )` with glob handled by the caller, or use `find` instead.

5. **`hq-blockers.sh` only extracts `## Needs from CEO`** (lines 55–58): the awk pattern matches `## Needs from CEO` only. The org-wide instructions specify three valid headings: `## Needs from Supervisor`, `## Needs from CEO`, `## Needs from Board`. Agents that use `## Needs from Supervisor` (the default) have their needs content silently dropped from the blocker report.

6. **`ls -t` for latest outbox file is fragile** (line 33 in `hq-blockers.sh`): `ls -t "sessions/${agent}/outbox"/*.md | head -n 1` is locale-dependent and breaks on filenames with newlines or special characters. More robust: `find ... -newer` comparison or `stat`-based sort.

7. **Hardcoded pid file names may not match actual files** (lines 146–147 in `hq-status.sh`): `pid_status "CEO loop" ".ceo-inbox-loop.pid"` and `pid_status "Inbox loop" ".inbox-loop.pid"` use hardcoded names. `runbooks/ceo-ops.md` mentions `.ceo-ops-loop.pid` and `file-ownership.md` mentions `.agent-exec-loop.pid` and `.ceo-ops-loop.pid`. The displayed process names likely do not correspond to the actual pid files.

8. **No usage/help string in either script** (both files): running `hq-blockers.sh --help` or passing an unexpected argument fails non-obviously. `hq-blockers.sh` does accept a `mode` argument (`count` vs default) but this is undocumented. Since `ceo-ops-once.sh` calls `hq-blockers.sh count` and the fallback is `|| echo 0`, any argument mismatch is silently swallowed (finding from cycle-9 review confirmed here).

## Suggested minimal diff direction

**Findings 1 + 2 (portability in `hq-status.sh`):** Replace `find -printf` with portable alternative:
```bash
latest_mtime_epoch() {
  local path="$1"
  [ -e "$path" ] || { echo 0; return; }
  find "$path" -mindepth 1 -type f -o -type d | while read -r f; do
    stat -c %Y "$f" 2>/dev/null || stat -f %m "$f" 2>/dev/null || echo 0
  done | sort -n | tail -n 1 || echo 0
}

agent_pending_inbox_count() {
  local dir="sessions/${1}/inbox"
  [ -d "$dir" ] || { echo 0; return; }
  find "$dir" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | wc -l | tr -d ' '
}
```

**Finding 5 (Needs heading variants in `hq-blockers.sh`):**
```bash
# Replace: /^## Needs from CEO/
# With: /^## Needs from (CEO|Supervisor|Board)/
needs="$(awk 'BEGIN{p=0}
  /^## Needs from (CEO|Supervisor|Board)/{p=1;next}
  /^## /{p=0}
  {if(p) print}
' "$latest" | sed -n '1,20p' | sed 's/^/    /')"
```

**Finding 7 (pid file names):** Update `hq-status.sh` lines 146–147 to match actual pid file names:
```bash
pid_status "CEO ops loop"   ".ceo-ops-loop.pid"
pid_status "Agent exec loop" ".agent-exec-loop.pid"
```

## Follow-up tasks to queue

**Task A → `dev-infra` (ROI: 8):**
Files: `scripts/hq-status.sh`, `scripts/hq-blockers.sh`
Fix: (1) replace all `find -printf '%T@'` and `find -printf '.'` with portable equivalents — this is the third occurrence across the scripts suite and should be addressed globally (also applies to `idle-work-generator.sh` and `sla-report.sh`); (2) fix `## Needs from` awk pattern in `hq-blockers.sh` to match all three heading variants.
Verify: run `./scripts/hq-status.sh` and confirm "Inbox" and "Last act" columns show non-zero values for agents with recent activity. Run `./scripts/hq-blockers.sh` against an outbox file containing `## Needs from Supervisor` and confirm the content appears in output.

**Task B → `dev-infra` (ROI: 5):**
Files: `scripts/hq-status.sh`
Fix: (1) correct pid file names in `pid_status` calls (lines 146–147) to match the actual `.ceo-ops-loop.pid` and `.agent-exec-loop.pid` names; (2) add a usage comment to `hq-blockers.sh` documenting the `count` argument.
Verify: run `./scripts/hq-status.sh` and confirm "CEO loop" and "Inbox loop" lines show "running" when the processes are active.

## ROI estimate
- ROI: 8
- Rationale: Findings 1 and 2 mean the CEO dashboard's "Inbox" and "Last act" columns silently show incorrect data on non-Linux systems (all zeros), making the dashboard unreliable for the primary health-check use case. Finding 5 means any agent using `## Needs from Supervisor` (the default per org-wide instructions) has their blocker needs silently dropped from the CEO's blocker report — a direct gap in escalation visibility. The `find -printf` portability fix is also the third instance of the same root-cause bug, making a global fix extremely high leverage.
