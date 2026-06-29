- Status: done
- Summary: Fast-exit applied. `stale-test-release-id-999-improvement-round` is a malformed improvement-round dispatch — "999" is not a real release ID; no PM signoff artifact exists for it, no PM groom/QA run/dev implementation references it as a canonical release, and the folder name has no YYYYMMDD date prefix. This is the 4th malformed improvement-round item confirmed this session (ba-forseti independently identified and fast-exited all 4, per their outbox `20260406-improvement-round-fake-no-signoff-release-id.md`). No gap review can be performed against a non-existent release. Root cause fix is already queued: `sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch` (ROI 89).

## Fast-exit evidence

| Check | Result |
|---|---|
| Release-ID format | FAIL — `stale-test-release-id-999` has no YYYYMMDD prefix; "999" is a sentinel/test value |
| PM signoff artifact | FAIL — no file at `sessions/pm-*/artifacts/release-signoffs/*999*` |
| Ghost check (canonical artifact) | FAIL — no PM groom, QA run, or dev implementation references release "999" |

## Next actions
- dev-infra: apply dispatch gate fix (`sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch`, ROI 89) — prevents future malformed improvement-round dispatches
- No other actions required

## Blockers
- None

## Pattern note
All 4 malformed improvement-round items this session (`stale-test-release-id-999-improvement-round`, `fake-no-signoff-release-id-improvement-round`, `20260405-improvement-round-fake-no-signoff-release`, and a prior `--help`-prefixed item) share the same root: `improvement-round.sh` dispatches without validating that the release ID corresponds to a real PM-signed release. The dev-infra fix is the correct closure for the pattern.

## ROI estimate
- ROI: 3
- Rationale: Fast-exit is low-cost; root cause fix already escalated at ROI 89. This is corroborating evidence only.
