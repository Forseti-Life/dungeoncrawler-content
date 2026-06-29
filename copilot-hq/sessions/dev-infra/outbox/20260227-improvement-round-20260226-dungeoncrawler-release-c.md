Now I understand the remaining issues. The key fix this cycle is: **bare-grep in dispatch scripts under `set -euo pipefail`** — if grep finds no match (field absent from command file), the pipeline returns non-zero, set -e exits the script before the empty-check, silently dropping the command. Also, `dispatch-pm-request.sh` creates PM inbox items without `roi.txt`. Let me fix both:

Now fix bare-grep in `update-feature-dashboard.sh`:

Now fix nullglob in the dispatch scripts (add `shopt -u nullglob` after array, before using it):

The edit removed the wrong block. Let me restore the proper structure:

The file is missing the `if empty + for loop` block. Restore it:

Now fix `ceo-dispatch-next.sh` and `inbox-dispatch-next.sh` nullglob:

Good, `ceo-dispatch-next.sh` needs the empty check re-added after the nullglob:

Missing the sort line again. Let me restore:

Now fix `inbox-dispatch-next.sh` and `inbox-process.sh` nullglob:

Now fix `update-feature-dashboard.sh` nullglob (inside a subshell `{}`):

The `shopt -u nullglob` inside the for loop body (before loop iterations) is wrong — it would unset nullglob during each iteration, but the `for f in features/*/feature.md` glob expansion already happened. The correct pattern is save/restore before/after the for loop. Let me fix properly:

Now add `roi.txt` to `dispatch-pm-request.sh` (which creates all PM inbox items):

Now verify all modified scripts:

The 4 remaining inbox-mkdir hits on dispatch scripts are false positives — those scripts create `inbox/processed` (archive folder, not item folder) and copy existing `command.md`; actual inbox items are created by `dispatch-pm-request.sh`. Fix the linter heuristic to only flag `cat > ... command.md` (heredoc item creation), not `cp ... command.md` (copy):

The linter count is wrong (word-split hits fire in a while-pipe subshell, so `issues` doesn't increment in parent). Fix by counting actual LINT lines via temp file:

13 issues accurately counted (9 word-split + 4 nullglob), exit 1. Now commit and also refresh seat instructions:

Now refresh seat instructions to add linter and verification commands:

Now write the outbox:

---

- Status: done
- Summary: Improvement round complete. Two commits applied this cycle. Commit `6d0a7b5` fixes 4 distinct bug classes across 7 scripts: (1) bare-grep under `set -euo pipefail` in all 4 dispatch scripts and `update-feature-dashboard.sh` — without `|| true`, a missing field in a command file causes silent script exit before the empty-value guard, silently dropping commands from the dispatch queue; (2) `shopt -u nullglob` restore added to all 5 scripts; (3) `dispatch-pm-request.sh` now writes `roi.txt` (value 3) to every PM inbox item it creates, fixing the root cause of all dispatched PM work arriving at ROI=1; (4) `lint-scripts.sh` issue counter fixed to use a temp file (word-split hits in while-pipe subshells now counted accurately — was 4, now 13), and inbox-mkdir heuristic tightened to eliminate 4 false positives on dispatch archive scripts. Commit `bf4de91` refreshes seat instructions to add `scripts/**` to owned scope and a `## How to verify` section.

## Next actions
- Fix word-split `for agent in $(configured_agent_ids)` in 9 scripts (replace with `while IFS= read -r agent` pattern); priority: `agent-exec-once.sh` (critical exec path).
- Fix nullglob-without-restore in 4 remaining scripts: `hq-blockers.sh`, `hq-status.sh`, `consolidate-legacy-sessions.sh`, `broadcast-role-self-audit.sh`.
- Target: `bash scripts/lint-scripts.sh` exits 0 within 2 more improvement cycles.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 7
- Rationale: Bare-grep fix eliminates a real silent-exit bug in the critical command dispatch path — commands with missing fields were silently dropped. The `dispatch-pm-request.sh` roi.txt fix ensures all dispatched PM work requests get correct queue priority. Combined, these prevent silent work loss and queue mis-ordering in every release cycle.

---
- Agent: dev-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/dev-infra/inbox/20260227-improvement-round-20260226-dungeoncrawler-release-c
- Generated: 2026-02-27T08:30:19-05:00
