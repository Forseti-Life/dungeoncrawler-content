# Release Process Review — 2026-04-07

**Status:** completed  
**CEO seat:** ceo-copilot-2

## Summary

Conducted end-to-end review of the release process flow. Found and fixed a critical bug in `scripts/release-cycle-start.sh` that caused an infinite release-cycle advance loop.

---

## Bug Found: Infinite Release Cycle Advance Loop

**Symptom:** The orchestrator logged `RELEASE-CYCLE: advance dungeoncrawler current=20260407-dungeoncrawler-release-b` on every tick (~400 attempts from 01:16 to 17:53). The `tmp/release-cycle-active/dungeoncrawler.release_id` state file was never updated beyond `20260406-dungeoncrawler-release-b`.

**Root cause (two defects in `release-cycle-start.sh`):**

1. **State files written AFTER early exit.** The `GAP-AGE-PREFLIGHT-01` guard (line 138) did `exit 0` when no features were activated for the new release ID — before the state file writes on lines 178–180. The orchestrator got rc=0 (advance "succeeded") but the file was unchanged.

2. **`set -euo pipefail` killed the script before reaching the exit.** The `_pf_feat_count=$(grep … | xargs grep … | wc -l)` pipeline exited with code 123 when xargs found no matching features. With `pipefail`, even inside `$()`, the script aborted at the variable assignment.

**Fix (committed in auto-checkpoint `30221866d`):**
- Moved `mkdir -p tmp/release-cycle-active && printf …` state-file writes to BEFORE the preflight check.
- Changed `if [feat_count=0] → exit 0` into `if/else/fi` block so only the QA dispatch is skipped, not the rest of the script.
- Added `|| echo 0` to the `_pf_feat_count` pipeline to prevent pipefail exit on no-match.

**Verified:** After fix, `release-cycle-start.sh dungeoncrawler 20260407-dungeoncrawler-release-b 20260407-dungeoncrawler-release-c` exits 0 and writes the state file correctly.

---

## Current Release Cycle State (post-fix)

| Team | Current Release | Next Release | Started |
|------|----------------|--------------|---------|
| dungeoncrawler | `20260407-dungeoncrawler-release-b` | `20260407-dungeoncrawler-release-c` | 2026-04-07T17:53 |
| forseti | `20260407-forseti-release-b` | `20260407-forseti-release-b` (next TBD) | 2026-04-07T17:50 |

**No signoff files exist for the new release IDs** — orchestrator will correctly enter "active" mode on next tick. Loop resolved.

---

## Process Health Assessment

### What's working:
- Dual-release model (current + next) is structurally correct.
- Signoff detection and state-file advance logic in orchestrator is sound.
- `release-signoff-status.sh` correctly identifies coordinated signoff readiness.
- QA testgen pipeline is active (34 items in qa-dungeoncrawler inbox being processed).

### Issues identified and resolved:
- **RESOLVED:** Infinite advance loop bug (see above).

### Issues identified, not yet resolved:
1. **Stagnation signal active:** `NO_RELEASE_PROGRESS: no release signoff in 24h+ (threshold 2h)`. This is expected — the new `20260407-dungeoncrawler-release-b` cycle just started. No corrective action needed; will clear when PM activates features and the cycle progresses.

2. **INBOX_AGING: oldest unresolved inbox item is 949m old.** The qa-dungeoncrawler inbox has 34 testgen items. These were dispatched during today's grooming session. QA is actively processing them (exec: yes). Auto-remediate is handling stalls. No escalation needed.

3. **Second orchestrator instance at `/home/ubuntu/copilot-sessions-hq/`.** Ubuntu user runs a second orchestrator loop from a different HQ directory with its own stale state (`dungeoncrawler.release_id = 20260402-dungeoncrawler-release-c`). This instance manages a separate environment. Confirm with Board whether this is intentional or a leftover instance that should be stopped.

4. **KB lesson needed:** The `release-cycle-start.sh` bug should be documented in `knowledgebase/lessons/` to prevent regression.

---

## Next Steps

- [ ] Monitor orchestrator log over next 2–3 ticks to confirm "active" status (no more "advance" on dc cycle).
- [ ] Dispatch KB lesson for the early-exit/state-file ordering pattern.
- [ ] Board decision: review the second orchestrator at `/home/ubuntu/copilot-sessions-hq/`.
- [ ] QA testgen backlog (34 items): monitor completion rate; if stalls continue, apply PM intervention per Gate 2 policy.
