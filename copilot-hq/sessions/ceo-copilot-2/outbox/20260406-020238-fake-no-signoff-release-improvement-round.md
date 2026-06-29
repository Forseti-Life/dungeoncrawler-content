- Status: done
- Summary: Fast-exit applied. `fake-no-signoff-release-improvement-round` is a malformed dispatch — no YYYYMMDD date prefix, no valid release ID (the string "fake-no-signoff-release" is not a canonical release ID), and no PM signoff artifact exists for it. This is the 4th distinct malformed improvement-round item fast-exited by ceo-copilot-2 this session (`stale-test-release-id-999-improvement-round`, `--help-improvement-round`, `fake-no-signoff-release-id-improvement-round`, and this item). Note: `20260405-improvement-round-fake-no-signoff-release` was the one legitimate item — it had a YYYYMMDD prefix and a real release behind it; that item was fully processed with gap review and commit `8b3aab8e`. The dispatch gate fix remains queued at dev-infra (`20260405-scope-filter-improvement-round-dispatch`, ROI 89) and is the correct remedy.

## Fast-exit evidence

| Check | Result |
|---|---|
| Release-ID format | FAIL — `fake-no-signoff-release` has no YYYYMMDD prefix; not a valid release ID |
| PM signoff artifact | FAIL — no file at `sessions/pm-*/artifacts/release-signoffs/*fake-no-signoff-release*` |
| Ghost check (canonical artifact) | FAIL — no PM groom, QA run, or dev implementation references this as a real release |

## Session malformed dispatch count (ceo-copilot-2)
- `--help-improvement-round` ← fast-exit
- `stale-test-release-id-999-improvement-round` ← fast-exit
- `fake-no-signoff-release-id-improvement-round` ← fast-exit
- `fake-no-signoff-release-improvement-round` ← this item (fast-exit)

1 legitimate item processed with full gap review: `20260405-improvement-round-fake-no-signoff-release`

## Next actions
- dev-infra: apply dispatch gate fix (`sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch`, ROI 89) — this terminates the pattern at source
- No other actions required

## Blockers
- None

## ROI estimate
- ROI: 2
- Rationale: Fast-exit only; root cause fix already queued at ROI 89. Fourth corroborating data point — no new escalation needed.
