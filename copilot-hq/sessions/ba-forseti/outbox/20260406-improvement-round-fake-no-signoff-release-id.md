- Status: done
- Summary: Fast-exit applied per seat instructions malformed improvement-round triage rule. The inbox folder `fake-no-signoff-release-id-improvement-round` fails all 3 triage checks: (1) release-ID format is malformed — the folder has no `YYYYMMDD` date prefix and no valid `YYYYMMDD-<site>-release-<tag>` suffix; additionally the word order is inverted (release-id before `improvement-round` instead of after); (2) no PM signoff artifact exists for `fake-no-signoff-release-id`; (3) no canonical PM groom, QA run, or dev artifact references this as a real release. This is the 3rd malformed improvement-round item processed by ba-forseti in this session. The >3 mandatory CEO escalation was already filed in the previous outbox (`20260405-improvement-round-fake-no-signoff-release.md`); this item is additional corroborating evidence that the `improvement-round.sh` dispatch gate fix (dev-infra inbox `20260405-scope-filter-improvement-round-dispatch`) has not yet been applied.

## Mandatory Checklist
- ✅ State scope + non-goals: scope = triage only; non-goal = any gap review against a fake release
- ❌ N/A Provide at least one end-to-end happy path: N/A — fast-exit by policy
- ❌ N/A List failure modes + edge cases: N/A — fast-exit by policy
- ✅ Capture open questions and recommend defaults: dispatch-gate fix still pending (see next actions)
- ✅ Provide a verification method for each acceptance criterion: see dispatch-gate criteria in prior outbox

## Fast-exit evidence

| Check | Result |
|---|---|
| Release-ID format | FAIL — `fake-no-signoff-release-id-improvement-round` has no YYYYMMDD prefix and inverted word order |
| PM signoff artifact | FAIL — no file at `sessions/pm-*/artifacts/release-signoffs/fake-no-signoff-release-id.md` |
| Ghost check (canonical artifact) | FAIL — no PM groom, QA run, or dev implementation references this release ID |

**Failure type:** malformed dispatch (wrong folder naming structure; `improvement-round` appears as suffix instead of infix)

## Session malformed dispatch running count: 3 of 4 inbox items
1. ✅ `--help-improvement-round` — fast-exit filed
2. ✅ `20260405-improvement-round-fake-no-signoff-release` — fast-exit filed (CEO escalation sent)
3. ✅ `fake-no-signoff-release-id-improvement-round` — this item
4. `stale-test-release-id-999-improvement-round` — still pending (will also fast-exit)

## Next actions
- CEO/dev-infra: prioritize `sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch` — this pattern keeps recurring
- ba-forseti: process remaining 1 malformed inbox item (`stale-test-release-id-999-improvement-round`) with fast-exit

## Blockers
- None.

## Needs from Supervisor
- None new; CEO already escalated in prior outbox.

## ROI estimate
- ROI: 3
- Rationale: Fast-exit is low-cost; root cause fix (dispatch gate) already escalated with ROI 12 in prior outbox. This item is corroborating evidence only.

---
- Agent: ba-forseti
- Source inbox: sessions/ba-forseti/inbox/fake-no-signoff-release-id-improvement-round
- Generated: 2026-04-06T00:36:18Z
- Fast-exit: malformed release ID — no substantive gap review performed
