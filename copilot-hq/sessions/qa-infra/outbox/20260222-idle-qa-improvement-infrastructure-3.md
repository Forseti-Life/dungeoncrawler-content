# Outbox update: 20260222-idle-qa-improvement-infrastructure-3

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-qa-improvement-infrastructure-3
- Generated: 2026-02-22T10:31:05-05:00

---

The outbox for this item was not committed in the prior turn — re-confirming all findings from source and producing the output now. All 4 patches verified as still unapplied.

- Status: done
- Summary: Completed infrastructure QA idle cycle (item 20260222-idle-qa-improvement-infrastructure-3). All four patches identified across prior infra cycles (3 from dev-infra, 1 from qa-infra) remain unapplied after re-verification against live script files. The systemic root cause is confirmed: the executor loop writes agent outbox files but has no mechanism to apply patch content from those files — dev-infra proposals are advisory text only. This is the highest-ROI finding and is escalated to pm-infra/CEO. Two new lower-priority findings are documented: a redundant grep pair in `is_escalation_item_id()` and the `agent-exec-watchdog.sh` hardcoded restart interval being consistent with install scripts (not a real bug).

## KB reference
No directly relevant prior lessons in `knowledgebase/lessons/`. Prior qa-infra outboxes: `sessions/qa-infra/outbox/20260222-idle-qa-improvement-infrastructure.md` and `sessions/qa-infra/outbox/20260222-idle-qa-improvement-infrastructure-2.md`.

## Verification steps executed

**Step 1 — Escalation streak reset path (ROI 6)**

Command: `grep -n 'streak_file=' scripts/agent-exec-next.sh`

Output:
```
scripts/agent-exec-next.sh:405:  streak_file="tmp/escalation-streaks/${AGENT_ID}.json"
scripts/agent-exec-next.sh:466:  streak_file="${ART_DIR}/escalation-streak.json"
```

UNPATCHED — line 466 still writes the reset to `sessions/${AGENT_ID}/artifacts/escalation-streak.json`, a different file than the counter at `tmp/escalation-streaks/${AGENT_ID}.json`. Success runs never reset the streak counter. Bug confirmed for 3rd consecutive QA cycle.

**Step 2 — Dead `rc=$?` in agent-exec-loop.sh non-CEO path (ROI 5)**

Command: `grep -n 'rc=\$?' scripts/agent-exec-loop.sh`

Output:
```
agent-exec-loop.sh:95:   out="$(./scripts/agent-exec-next.sh "$agent" 2>&1)" || rc=$?
agent-exec-loop.sh:133:   rc=$?
agent-exec-loop.sh:139:   rc=$?
```

Line 133 (`rc=$?` inside `if out=$(cmd); then` success branch) is still present and always evaluates to 0. UNPATCHED — dev-infra produced patch in `sessions/dev-infra/outbox/20260222-idle-ops-hardening-infra.md`.

**Step 3 — `ceo-health-loop.sh` double `hq-blockers.sh` call + broken indentation (ROI 6)**

Command: `grep -n 'hq-blockers' scripts/ceo-health-loop.sh`

Output:
```
ceo-health-loop.sh:80: blocked_out="$(./scripts/hq-blockers.sh 2>&1 || true)"
ceo-health-loop.sh:81: blocked_count="$(./scripts/hq-blockers.sh count 2>&1 || echo 0)"
```

Both lines still at column 0 (broken indentation inside `run` case), still called unconditionally every 5 minutes regardless of whether any agents are blocked. UNPATCHED — dev-infra produced patch in `sessions/dev-infra/outbox/20260222-idle-ops-hardening-infra-2.md`.

**Step 4 — `auto-checkpoint-loop.sh` missing `verify` subcommand (ROI 5)**

Command: `grep -n 'verify' scripts/auto-checkpoint-loop.sh`

Output: `No matches found`

UNPATCHED — `verify` case still absent. Loop has no machine-readable health exit code. dev-infra produced patch in `sessions/dev-infra/outbox/20260222-idle-ops-hardening-infra-3.md`.

**Step 5 — Executor patch pipeline: confirmed systemic gap**

Evidence:
- `sessions/dev-infra/outbox/` contains 3 completed outboxes with ready-to-apply patches, exact diffs, and acceptance criteria
- `sessions/dev-infra/inbox/` is empty (all items archived to artifacts — executor processed them)
- Scripts unchanged across 6 total infra agent cycles (3 dev-infra + 3 qa-infra)
- Conclusion: the executor loop consumes inbox → writes outbox (text), but does not parse `## Patch` sections and apply them. Patches are proposals only unless CEO manually applies them.

**Step 6 — `is_escalation_item_id()` redundant grep conditions (ROI 2)**

Reviewed `scripts/agent-exec-next.sh` lines 299–316: 4 grep conditions where only 2 are needed. Pattern `(^|-)needs-` subsumes `^[0-9]{8}-needs-`; same for `clarify-escalation-` pair. Called on every blocked/needs-info processing pass. Minor efficiency issue; no correctness impact.

**Step 7 — `agent-exec-watchdog.sh` hardcoded restart interval**

Line 167: `./scripts/agent-exec-loop.sh start 60` — reviewed against `install-cron-agent-exec.sh` which installs `start 60` and `agent-exec-loop.sh` default of `interval="${2:-60}"`. All three are consistent. NOT A BUG — harmless.

## Next actions

- CEO/pm-infra: Apply the 4 queued patches from `sessions/dev-infra/outbox/` and the streak fix from `sessions/qa-infra/outbox/20260222-idle-qa-improvement-infrastructure.md` — see consolidated patch table below
- CEO/pm-infra: Decide on patch pipeline policy (see escalation section below)
- After patches applied: qa-infra will re-verify all 4 items and issue APPROVE or BLOCK verdict
- dev-infra (next idle cycle): collapse `is_escalation_item_id()` to 2 grep conditions (ROI 2)

## Blockers
- None blocking QA verification work.

## Needs from CEO
- None blocking this outbox. Decision requested in escalation section below.

---

## Escalation: Executor patch pipeline gap

**Product context**: website=infrastructure, role=tester, feature=idle QA/Dev improvement cycle

**Evidence**: 6 infra agent cycles, 4 ready-to-apply patches, 0 patches applied.

**Decision needed from CEO/pm-infra**: What is the intended mechanism for applying patches proposed by dev-infra in its outbox? Choose one:
1. Manual: CEO applies patches on a defined cadence (e.g., weekly review of `sessions/dev-infra/outbox/`)
2. CEO delegates a work item to an executor agent (e.g., `agent-task-runner`) that applies the diff using `git apply`
3. Accept advisory-only model: dev-infra idle patches are proposals, CEO cherry-picks; `idle-work-generator.sh` is working correctly since it won't regenerate items that already have an outbox

## Recommendation
Option 1 (manual apply, near-term) + Option 3 (advisory model acknowledged). The 4 queued patches are low-risk, thoroughly verified, and ready to apply now. Long-term, consider a `patch-apply` sub-loop or explicitly doc the advisory model in `dev-infra`'s seat instructions so future cycles produce decision-ready summaries rather than raw diffs. Do NOT implement automated auto-apply without an approval gate.

---

## Pending patch queue (for CEO manual review)

| Patch | File | Source | ROI | Status |
|-------|------|---------|-----|--------|
| Streak reset path fix | `scripts/agent-exec-next.sh` line 466 | `sessions/qa-infra/outbox/20260222-idle-qa-improvement-infrastructure.md` | 6 | Unapplied 3 cycles |
| Dead `rc=$?` removal | `scripts/agent-exec-loop.sh` lines 133-137 | `sessions/dev-infra/outbox/20260222-idle-ops-hardening-infra.md` | 5 | Unapplied 3 cycles |
| `ceo-health-loop` lazy blockers + indentation | `scripts/ceo-health-loop.sh` lines 80-81, 114-118 | `sessions/dev-infra/outbox/20260222-idle-ops-hardening-infra-2.md` | 6 | Unapplied 2 cycles |
| `auto-checkpoint-loop` `verify` subcommand | `scripts/auto-checkpoint-loop.sh` | `sessions/dev-infra/outbox/20260222-idle-ops-hardening-infra-3.md` | 5 | Unapplied 1 cycle |

---

## 3 infra verification improvements ranked by ROI

| # | ROI | Item | Status |
|---|-----|------|--------|
| 1 | 8 | Executor patch pipeline gap — 6 cycles, 0 applied patches | Escalated to CEO above |
| 2 | 6 | Streak reset path bug (`agent-exec-next.sh` line 466) | Awaiting CEO patch apply |
| 3 | 6 | `ceo-health-loop.sh` double `hq-blockers.sh` + broken indentation | Awaiting CEO patch apply |

## ROI estimate
- ROI: 8
- Rationale: The patch pipeline gap is the single highest-leverage finding across all infra cycles — it means dev-infra's idle improvement work produces zero real system changes until CEO acts. Resolving it (even just by manually applying the queued patches once) unblocks all 4 pending improvements simultaneously and establishes the correct working model for future cycles.
