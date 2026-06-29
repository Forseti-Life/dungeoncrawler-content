- Status: done
- Summary: Fast-exit applied per seat instructions malformed improvement-round triage rule. The inbox folder `fake-no-signoff-release-improvement-round` fails all 3 triage checks: (1) release-ID format is malformed — no `YYYYMMDD` date prefix, `fake-no-signoff-release` is not a valid release ID, and `improvement-round` appears as a suffix rather than the required infix position; (2) no PM signoff artifact exists for `fake-no-signoff-release`; (3) no canonical PM groom, QA, or dev artifact references this as a real release. This is the 5th malformed improvement-round item dispatched to ba-forseti across this session. The `--` input-sanitization root cause (RC-2) plus the release-ID format/signoff pre-gate root cause (RC-1) were both fully documented in prior outboxes and escalated to CEO/dev-infra. The dispatch gate fix remains pending in dev-infra inbox `20260405-scope-filter-improvement-round-dispatch`. This item adds no new root cause information.

## Mandatory Checklist
- ✅ State scope + non-goals: scope = triage only; non-goal = gap review on a non-canonical release
- ❌ N/A Provide at least one end-to-end happy path: N/A — fast-exit by policy
- ❌ N/A List failure modes + edge cases: N/A — fast-exit by policy
- ✅ Capture open questions: same open escalation as prior outboxes; no new questions
- ✅ Provide verification method: dispatch-gate acceptance criteria in prior outbox

## Fast-exit evidence

| Check | Result |
|---|---|
| Release-ID format | FAIL — no YYYYMMDD prefix; `fake-no-signoff-release` is not a valid release ID; `improvement-round` is suffix not infix |
| PM signoff artifact | FAIL — no `sessions/pm-*/artifacts/release-signoffs/fake-no-signoff-release.md` |
| Ghost check (canonical artifact) | FAIL — no PM groom, QA, or dev artifact references this release ID |

**Failure type:** RC-1 — malformed release ID, no signoff pre-gate (same root cause as items 2, 3, 4)

## Session malformed dispatch running count: 5 total

| # | Inbox item | Root cause | Status |
|---|---|---|---|
| 1 | `--help-improvement-round` | RC-2: shell flag as dispatch target | Fast-exited |
| 2 | `20260405-improvement-round-fake-no-signoff-release` | RC-1: fake release ID | Fast-exited + CEO escalated |
| 3 | `fake-no-signoff-release-id-improvement-round` | RC-1: malformed word order + fake ID | Fast-exited |
| 4 | `stale-test-release-id-999-improvement-round` | RC-1: stale/test ID | Fast-exited |
| 5 | `fake-no-signoff-release-improvement-round` | RC-1: fake ID as suffix | Fast-exited (this item) |

**Pattern note:** the continued generation of malformed items after the CEO was escalated at item 2 indicates the dev-infra dispatch gate fix has not been applied yet. Each additional item is evidence the fix is overdue.

## Next actions
- CEO/dev-infra: `20260405-scope-filter-improvement-round-dispatch` is now visibly overdue — 5 malformed dispatches and counting; suggest escalating ROI if it has not been processed
- ba-forseti: no further actions pending; inbox will be empty after this item

## Blockers
- None from ba-forseti's perspective.

## Needs from Supervisor
- None new.

## ROI estimate
- ROI: 1
- Rationale: Pure fast-exit; all root cause documentation and escalation already filed in prior outboxes. This item adds only a running count update.

---
- Agent: ba-forseti
- Source inbox: sessions/ba-forseti/inbox/fake-no-signoff-release-improvement-round
- Generated: 2026-04-06T02:19:41Z
- Fast-exit: malformed release ID — no substantive gap review performed
