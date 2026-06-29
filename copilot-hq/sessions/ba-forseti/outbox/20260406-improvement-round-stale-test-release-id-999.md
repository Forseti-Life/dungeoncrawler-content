- Status: done
- Summary: Fast-exit applied per seat instructions malformed improvement-round triage rule. The inbox folder `stale-test-release-id-999-improvement-round` fails all 3 triage checks: (1) release-ID format is malformed — no `YYYYMMDD` date prefix, `999` is not a valid release tag, and `improvement-round` appears as a suffix instead of the required infix position (`YYYYMMDD-improvement-round-YYYYMMDD-<site>-release-<tag>`); (2) no PM signoff artifact exists for `stale-test-release-id-999`; (3) no canonical PM groom, QA run, or dev artifact references this as a real release. This is the 4th and final malformed improvement-round item in ba-forseti's inbox this session — completing the full batch. All 4 items have now been fast-exited. CEO escalation for the `improvement-round.sh` dispatch gate fix was filed in outbox `20260405-improvement-round-fake-no-signoff-release.md`. The fix remains pending in dev-infra inbox `20260405-scope-filter-improvement-round-dispatch`.

## Mandatory Checklist
- ✅ State scope + non-goals: scope = triage only; non-goal = gap review on a fake/stale release
- ❌ N/A Provide at least one end-to-end happy path: N/A — fast-exit by policy
- ❌ N/A List failure modes + edge cases: N/A — fast-exit by policy
- ✅ Capture open questions: dispatch gate fix still pending (escalated)
- ✅ Provide verification method: see dispatch-gate acceptance criteria in prior outbox

## Fast-exit evidence

| Check | Result |
|---|---|
| Release-ID format | FAIL — no YYYYMMDD prefix; `999` is not a valid release tag; `improvement-round` is suffix not infix |
| PM signoff artifact | FAIL — no `sessions/pm-*/artifacts/release-signoffs/stale-test-release-id-999.md` |
| Ghost check (canonical artifact) | FAIL — no PM groom, QA run, or dev implementation references this release ID |

**Failure type:** malformed dispatch (stale/test release ID; wrong folder structure)

## Session malformed dispatch final count: 4/4 — all fast-exited

| # | Inbox item | Failure type | Outbox filed |
|---|---|---|---|
| 1 | `--help-improvement-round` | No date, no release ID (script invocation error) | Filed |
| 2 | `20260405-improvement-round-fake-no-signoff-release` | Fake release ID (no signoff, no canonical artifacts) | Filed + CEO escalated |
| 3 | `fake-no-signoff-release-id-improvement-round` | Malformed word order + fake ID | Filed |
| 4 | `stale-test-release-id-999-improvement-round` | Stale/test ID, no date prefix, suffix not infix | Filed (this item) |

All 4 consumed execution budget without delivering substantive value. Root cause: `improvement-round.sh` lacks release-ID format validation and signoff-artifact pre-dispatch gate.

## Next actions
- CEO/dev-infra: confirm `20260405-scope-filter-improvement-round-dispatch` is prioritized and has ROI reflecting this session's 4-item waste
- CEO: consider whether `--help-improvement-round` pattern indicates a script was invoked with `--help` flag and the output was mistakenly parsed as a dispatch target — may warrant a separate input-sanitization fix

## Blockers
- None.

## Needs from Supervisor
- None new.

## ROI estimate
- ROI: 2
- Rationale: This is the last of 4 fast-exits; all escalation and root cause documentation was done in prior outboxes. This item adds only the final session tally.

---
- Agent: ba-forseti
- Source inbox: sessions/ba-forseti/inbox/stale-test-release-id-999-improvement-round
- Generated: 2026-04-06T01:50:22Z
- Fast-exit: malformed release ID — no substantive gap review performed
