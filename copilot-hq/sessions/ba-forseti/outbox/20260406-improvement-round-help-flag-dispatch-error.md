- Status: done
- Summary: Fast-exit applied per seat instructions malformed improvement-round triage rule. The inbox folder `--help-improvement-round` fails all 3 triage checks: (1) release-ID format is malformed — the folder begins with `--help`, which is a shell flag, contains no `YYYYMMDD` date prefix, and has no valid `YYYYMMDD-<site>-release-<tag>` segment; this pattern strongly indicates `improvement-round.sh --help` was invoked to print usage text and the script's output (or invocation string) was mistakenly parsed as an inbox folder name; (2) no PM signoff artifact exists for `--help`; (3) no canonical PM groom, QA run, or dev artifact references this as a real release. This item represents a distinct failure mode from the previous 3 malformed dispatches: rather than a fake/test release ID, this appears to be a script invocation error where a flag string was treated as a dispatch target. All 4 malformed inbox items for this session are now fully cleared.

## Mandatory Checklist
- ✅ State scope + non-goals: scope = triage only; non-goal = any gap review on a non-release
- ❌ N/A Provide at least one end-to-end happy path: N/A — fast-exit by policy
- ❌ N/A List failure modes + edge cases: N/A — fast-exit by policy
- ✅ Capture open questions: two distinct root causes now documented; both need dev-infra fixes
- ✅ Provide verification method: see acceptance criteria below

## Fast-exit evidence

| Check | Result |
|---|---|
| Release-ID format | FAIL — starts with `--help`; shell flag string, not a release ID |
| PM signoff artifact | FAIL — no `sessions/pm-*/artifacts/release-signoffs/--help.md` |
| Ghost check (canonical artifact) | FAIL — no PM groom, QA, or dev artifact references this |

**Failure type:** script invocation error — `--help` flag string treated as dispatch target

## Two distinct root causes now identified across 4 malformed dispatches

| Root cause | Inbox items affected | Fix owner |
|---|---|---|
| RC-1: No release-ID format validation or signoff pre-gate in `improvement-round.sh` | `20260405-improvement-round-fake-no-signoff-release`, `fake-no-signoff-release-id-improvement-round`, `stale-test-release-id-999-improvement-round` | dev-infra (`20260405-scope-filter-improvement-round-dispatch`) |
| RC-2: Script flag strings (`--help`) passed as dispatch target — no input sanitization for leading `--` | `--help-improvement-round` | dev-infra (new or same item — should add `--` prefix guard to RC-1 fix) |

**Recommendation for dev-infra:** the RC-1 fix (`20260405-scope-filter-improvement-round-dispatch`) should be extended to also reject any release ID argument that begins with `--` or contains only non-alphanumeric characters. This closes both root causes in a single pass.

## Session summary — all 4 malformed items cleared

| # | Inbox item | Root cause | Outbox filed |
|---|---|---|---|
| 1 | `--help-improvement-round` | RC-2: shell flag as dispatch target | This outbox |
| 2 | `20260405-improvement-round-fake-no-signoff-release` | RC-1: fake release ID, no signoff gate | Filed; CEO escalated |
| 3 | `fake-no-signoff-release-id-improvement-round` | RC-1: malformed word order + fake ID | Filed |
| 4 | `stale-test-release-id-999-improvement-round` | RC-1: stale/test ID, wrong structure | Filed |

## Next actions
- dev-infra: extend `20260405-scope-filter-improvement-round-dispatch` scope to include RC-2 (`--` prefix guard) — single-pass fix for both root causes
- CEO: confirm RC-2 is incorporated into the existing dev-infra inbox item (or create a follow-on if the item is already in-flight)

## Blockers
- None.

## Needs from Supervisor
- None.

## ROI estimate
- ROI: 2
- Rationale: Final item; all substantive escalation already filed. RC-2 addendum recommendation has low additional cost for dev-infra since it is a 1-line guard alongside the RC-1 fix.

---
- Agent: ba-forseti
- Source inbox: sessions/ba-forseti/inbox/--help-improvement-round
- Generated: 2026-04-06T02:09:42Z
- Fast-exit: malformed release ID (shell flag string) — no substantive gap review performed
