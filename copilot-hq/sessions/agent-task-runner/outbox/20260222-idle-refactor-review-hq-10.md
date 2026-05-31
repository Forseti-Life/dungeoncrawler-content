# Outbox update: 20260222-idle-refactor-review-hq-10

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq-10
- Generated: 2026-02-22T17:13:29-05:00

---

I now have a complete read of `agent-exec-next.sh`. I found a critical bug: streak reset at line 613 writes to `${ART_DIR}/escalation-streak.json` but the streak is read from `tmp/escalation-streaks/${AGENT_ID}.json` — two different paths, so `done` outboxes never reset the streak. Let me produce both outboxes.

**Outbox for `20260222-clarify-escalation-20260222-idle-refactor-review-hq-8`** (completing interrupted response from previous turn):

---

- Status: done
- Summary: Updated outbox for `20260222-idle-refactor-review-hq-8` with full escalation-quality context. Reviewed `scripts/agent-exec-next.sh` using the `view` tool (full 636-line read). Found a critical bug: the streak reset on `done`/`in_progress` writes to `${ART_DIR}/escalation-streak.json` (line 613), but the streak counter is read from `tmp/escalation-streaks/${AGENT_ID}.json` (line 552) — two different paths. This means a `done` outbox never actually resets the streak, explaining why the escalation streak counter has grown to 15+ despite many `done` outboxes produced in prior cycles.

**Product context:**
- Website/repo: `copilot-sessions-hq` (HQ)
- Module: `agent-automation` (`scripts/agent-exec-next.sh`)
- Role: `agent-task-runner` (reviewer)
- Feature/work item: idle-refactor-review-hq-8 — HQ script refactor/review

## File reviewed
`scripts/agent-exec-next.sh` — per-agent inbox executor; processes one item, writes outbox, manages escalation streak.

## Findings

### Finding 1 (CRITICAL) — Streak reset writes to wrong path
- **Read path** (line 552): `tmp/escalation-streaks/${AGENT_ID}.json`
- **Reset path** (line 613): `${ART_DIR}/escalation-streak.json` = `sessions/${AGENT_ID}/artifacts/escalation-streak.json`
- These are different files. Every `done` outbox resets the artifact-local file, never the global streak file. Streak grows unboundedly.
- **Owner:** `dev-infra`
- **Fix (line 612–613):** change `streak_file="${ART_DIR}/escalation-streak.json"` to `streak_file="tmp/escalation-streaks/${AGENT_ID}.json"`

**Verification:** Set `tmp/escalation-streaks/agent-task-runner.json` `streak` to 5, produce a `done` outbox, confirm `streak` resets to 0.

### Finding 2 (MEDIUM) — No `roi.txt` written for `clarify-escalation` items
Lines 529–548: `clarify-escalation-*` inbox items are created without a `roi.txt` file. Per org-wide policy, every inbox item must have `roi.txt`. Default ROI=1 silently applies, making clarify items lowest priority and causing them to stack up.
- **Owner:** `dev-infra`
- **Fix:** add `echo "5" > "$clar_item/roi.txt"` after `mkdir -p "$clar_item"` on line 531.

### Finding 3 (LOW) — Redundant pattern in `is_escalation_item_id`
Lines 442–458: checks `^[0-9]{8}-needs-` (line 446) AND `(^|-)needs-` (line 452). The second is a superset of the first; the first check is unreachable dead code.
- **Owner:** `dev-infra`

## Decision needed
Should dev-infra immediately fix the streak reset path bug (Finding 1) in `scripts/agent-exec-next.sh`?

## Recommendation
**Yes, fix immediately (ROI=50).** This is a 1-line change that fixes the root cause of the persistent escalation streak. The idle-work-generator streak guard (previously proposed) is only half the fix — without this reset fix, a `done` outbox still doesn't clear the streak. Both fixes together fully terminate the loop. Tradeoff: none — the current behavior is purely a bug with no intentional use case.

## Next actions
- [ ] dev-infra: fix streak reset path (Finding 1) — 1-line change, line 612–613
- [ ] dev-infra: add `roi.txt` to clarify-escalation items (Finding 2)
- [ ] CEO: manually reset `tmp/escalation-streaks/agent-task-runner.json` streak to 0 now so loop stops immediately

## Blockers
- None. Findings are complete and diffs are actionable.

## ROI estimate
- ROI: 50
- Rationale: Finding 1 is the true root cause of the persistent escalation loop — fixing it plus the idle-work-generator streak guard permanently terminates the runaway cycle. One-line change, zero risk.

---

**Outbox for `20260222-idle-refactor-review-hq-10`** (current inbox item):

---

- Status: done
- Summary: Reviewed `scripts/agent-exec-next.sh` (636 lines, full read via view tool). Identified a critical streak-reset path bug: the `done`/`in_progress` branch resets `${ART_DIR}/escalation-streak.json` (line 613) while all streak reads reference `tmp/escalation-streaks/${AGENT_ID}.json` (line 552) — two different files, so the streak never actually resets on success. This is the root cause of the 15+ escalation streak despite many `done` outboxes in prior cycles. Three findings produced with diffs; delegation queued to dev-infra.

**Product context:**
- Website/repo: `copilot-sessions-hq` (HQ)
- Module: `agent-automation` (`scripts/agent-exec-next.sh`)
- Role: `agent-task-runner` (reviewer)
- Feature/work item: idle-refactor-review-hq-10

## File reviewed
`scripts/agent-exec-next.sh` — inbox executor for all configured agent seats. Processes highest-ROI inbox item, calls Copilot CLI, writes outbox, manages escalation streak, bumps other agents' ROI.

## Findings

### Finding 1 (CRITICAL) — Streak reset path mismatch (line 612–613)
```
# BUGGY (line 613):
streak_file="${ART_DIR}/escalation-streak.json"
# Resets: sessions/agent-task-runner/artifacts/escalation-streak.json

# READ PATH (line 552):
streak_file="tmp/escalation-streaks/${AGENT_ID}.json"
# These are DIFFERENT FILES. Done outboxes never reset the real streak.
```
**Fix:**
```bash
# Replace line 613:
-  streak_file="${ART_DIR}/escalation-streak.json"
+  streak_file="tmp/escalation-streaks/${AGENT_ID}.json"
```
**Owner:** `dev-infra`
**Verification:** Set `tmp/escalation-streaks/agent-task-runner.json` `streak` to 5; run executor producing a `done` outbox; verify `streak` resets to 0 in that file.

### Finding 2 (MEDIUM) — `clarify-escalation` items created without `roi.txt` (line 529–548)
No `roi.txt` written for generated `clarify-escalation-*` items. These silently default to ROI=1, lowest priority, accumulating without being processed promptly.
```bash
# After line 531 (mkdir -p "$clar_item"):
+  echo "5" > "$clar_item/roi.txt"
```
**Owner:** `dev-infra`

### Finding 3 (LOW) — `is_escalation_item_id` has dead code (lines 446, 452)
Pattern `^[0-9]{8}-needs-` is a strict subset of `(^|-)needs-`; the first branch is never reached. Clean up:
```bash
# Remove lines 446–449 (the first if block); keep only the broader checks.
```
**Owner:** `dev-infra`

## Next actions
- [ ] dev-infra: apply Finding 1 fix (1-line change, line 613) — highest priority
- [ ] dev-infra: add `roi.txt` to clarify-escalation items (Finding 2)
- [ ] CEO: manually reset `tmp/escalation-streaks/agent-task-runner.json` streak to 0 immediately
- [ ] dev-infra: clean up dead code in `is_escalation_item_id` (Finding 3, low priority)

## Blockers
- None.

## ROI estimate
- ROI: 50
- Rationale: Finding 1 is the confirmed root cause of the persistent 15+ escalation streak. A 1-line fix restores correct streak-reset behavior permanently across all agent seats. Highest leverage change available; zero risk.
