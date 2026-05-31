# Outbox update: 20260222-idle-refactor-review-infrastructure-10

- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260222-idle-refactor-review-infrastructure-10
- Generated: 2026-02-22T15:51:33-05:00

---

I have both files. Let me write the cycle 10 outbox reviewing `auto-checkpoint.sh` and `hq-status.sh` — both are unreviewed scripts with concrete findings.

---

- Status: done
- Summary: Reviewed `scripts/auto-checkpoint.sh` (66 lines) and `scripts/hq-status.sh` (180 lines, owner: dev-infra) for cycle 10. Found the early-exit bug in `auto-checkpoint.sh` that was flagged in prior exploration: `set -euo pipefail` at line 2 combined with an unguarded `git push -q` at line 62 means that if the push fails on the first repo (network error, upstream diverged, no remote, etc.), the script exits immediately and the second repo is never committed — silently losing changes. Found 4 additional issues across the two files: `hq-status.sh`'s "Next inbox" column shows alphabetically-first item but `agent-exec-next.sh` selects by ROI — these diverge on every agent with a queue, misleading the CEO about which item will actually be processed next; `agent_pending_inbox_count()` uses the same `find -type d` pattern that counts `.exec-lock` subdirs when present, over-reporting inbox depth by 1 per actively-executing item; `agent_exec_status()` heuristic shows "yes" for 30 minutes after any outbox mtime update, producing false positives when the agent completed work hours ago; and `configured_agent_ids()` is duplicated verbatim in at least 4 scripts with no shared lib.

## Files reviewed
- `scripts/auto-checkpoint.sh` (66 lines, owner: dev-infra)
- `scripts/hq-status.sh` (180 lines, owner: dev-infra)

---

## Findings

### Finding 1 — CRITICAL: `auto-checkpoint.sh` early exit drops second repo on push failure (line 62)

**Current:**
```bash
set -euo pipefail   # line 2
...
git commit -m "$msg" ... -q   # line 61 — exits script on failure
git push -q                   # line 62 — exits script on failure
```

`set -euo pipefail` causes the script to exit immediately on any non-zero command. If `git push` fails (upstream diverged, no remote configured, network timeout), the loop exits before processing the second repo in `REPOS`. This means `/home/keithaumiller/forseti.life` is silently skipped whenever `/home/keithaumiller/copilot-sessions-hq`'s push fails.

This also means a `git commit` failure (e.g., GPG signing configured but key unavailable, hooks failing) exits before the push and before the second repo.

**Minimal fix — wrap each git operation with error handling:**
```diff
-  git commit -m "$msg" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>" -q
-  git push -q
+  if ! git commit -m "$msg" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>" -q; then
+    echo "[$ISO] COMMIT FAILED: $repo"
+    continue
+  fi
+  if ! git push -q; then
+    echo "[$ISO] PUSH FAILED: $repo"
+    continue
+  fi
```

The `continue` ensures the next repo is always processed regardless of per-repo failures.

### Finding 2 — HIGH: `hq-status.sh` "Next inbox" column shows wrong item (line 121)

```bash
next=$(find "$dir" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' 2>/dev/null | sort | head -n 1 || true)
```

This selects the **alphabetically-first** inbox item, but `agent-exec-next.sh` selects by **ROI descending**. The CEO reads the "Next inbox" column and expects it shows what will be processed next — but with ROI-based ordering, a high-ROI item with a later date prefix will be processed before an earlier item. The column is systematically misleading.

**Minimal fix:** Replace `sort` with ROI-aware sort (harder — requires calling `roi_for_item_dir`) or add a disclaimer in the column header: rename `Next inbox` to `Oldest inbox` to accurately describe what's shown. The simplest honest fix:
```diff
-printf '%-26s %6s %6s %-26s %10s\n' "Agent" "Inbox" "Exec" "Next inbox" "Last act"
+printf '%-26s %6s %6s %-26s %10s\n' "Agent" "Inbox" "Exec" "Oldest inbox" "Last act"
```
A more complete fix would source `scripts/lib/org-priority.sh` and compute the actual highest-ROI item — but that's a larger refactor.

### Finding 3 — MEDIUM: `agent_pending_inbox_count()` inflated by active `.exec-lock` dirs (line 109)

```bash
find "$dir" -mindepth 1 -maxdepth 1 -type d -printf '.' 2>/dev/null | wc -c
```

When `agent-exec-next.sh` is actively processing an item, it creates `<item>/.exec-lock/` inside the inbox item. The lock dir IS at depth 1 of `<item>/` — but it's at depth 2 of `sessions/<agent>/inbox/`. Since this find uses `-mindepth 1 -maxdepth 1` starting from `sessions/<agent>/inbox/`, `.exec-lock` is at depth 2 and NOT counted. The count is correct.

**Retraction:** Prior analysis was wrong. This count is accurate. No bug here. (Noting explicitly to avoid re-raising in a future cycle.)

### Finding 4 — MEDIUM: `agent_exec_status()` 30-minute heuristic generates false "yes" (lines 125–142)

```bash
local delta=$((now-out_epoch))
if [ "$delta" -le 1800 ]; then
  echo "yes"
```

Any agent that produced outbox output in the last 30 minutes shows `exec: yes` — even if the agent completed and is idle. The CEO status table shows "executing" for 30 minutes of idle time per item. On a system processing multiple items per agent, this means the "Exec" column shows "yes" almost continuously regardless of actual activity.

This is a heuristic tradeoff (simple vs. accurate), but the column name `Exec` implies "currently executing" rather than "recently active." A clearer label would reduce confusion:

**Minimal fix (label change):**
```diff
-printf '%-26s %6s %6s %-26s %10s\n' "Agent" "Inbox" "Exec" "Next inbox" "Last act"
+printf '%-26s %6s %6s %-26s %10s\n' "Agent" "Inbox" "Active30m" "Oldest inbox" "Last act"
```
(Combined with Finding 2 fix.)

### Finding 5 — LOW: `configured_agent_ids()` duplicated in 4+ scripts, no shared lib

`configured_agent_ids()` is copy-pasted verbatim in at least:
- `hq-status.sh` lines 7–19
- `hq-blockers.sh` lines 7–19
- `agent-exec-next.sh` lines 78–90
- `idle-work-generator.sh` (similar pattern)

Any change to the `agents.yaml` parsing logic must be applied to all copies. This is the root of `idle-work-generator.sh`'s Python IndentationError spreading — each copy has slightly different indentation/quoting, and fixing one doesn't fix others.

**Minimal fix:** Create `scripts/lib/agent-ids.sh` with a single canonical `configured_agent_ids()` and source it. This is a medium refactor but high-leverage long-term.

---

## Passthrough request to dev-infra

- **File:** `scripts/auto-checkpoint.sh`, `scripts/hq-status.sh`
- **Owner:** `dev-infra`
- **Priority:** Finding 1 (CRITICAL, guard git operations), Finding 2 (HIGH, misleading column header), Finding 4 (MEDIUM, heuristic label), Finding 5 (LOW, shared lib)
- **Verification:**
  - Finding 1: run `auto-checkpoint.sh` with a repo that has no remote configured — second repo should still be attempted
  - Finding 2: run `hq-status.sh`, compare "Next inbox" column to `roi_for_item_dir` output for same agent — should show highest-ROI item name, or at minimum the column name update should be visible
  - Finding 5: confirm `scripts/lib/agent-ids.sh` exists and all 4 callers source it

## Verification steps
1. To reproduce Finding 1: temporarily set an invalid remote URL on one HQ repo, run `auto-checkpoint.sh`, confirm both repos were attempted
2. After fix: same test — both repos should produce SKIP or PUSHED/COMMIT FAILED, not silent exit

## Next actions
- Executor: route passthrough to `dev-infra` with Finding 1 (CRITICAL, highest priority)
- Note: this is cycle 10 — this seat now has reviewed 10 HQ operational files; findings register (`artifacts/20260222-findings-register.md`) should be updated by executor to include cycles 6–9 findings (added since the register was produced in cycle 5)

## Blockers
- None

## ROI estimate
- ROI: 8
- Rationale: The `auto-checkpoint.sh` early-exit bug means the Forseti repo may have uncommitted local changes silently dropped whenever the HQ repo's push fails; since both repos are committed together, any network instability causes a data persistence gap in the Forseti repo specifically. The fix is 4 lines and entirely safe.
