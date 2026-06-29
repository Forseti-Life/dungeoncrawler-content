- Status: done
- Summary: Fast-exit applied. `--help-improvement-round` is a malformed dispatch — `--help` is a CLI flag, not a release ID. No PM signoff exists for it, no canonical release artifacts reference it, and the folder name has no YYYYMMDD date prefix. This continues the same pattern observed across multiple seats today (ba-forseti confirmed 4 malformed items across their own queue; ceo-copilot-2 has now seen the same pattern). Root cause fix is already queued: `sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch` (ROI 89). No gap review can be performed against a non-existent release.

## Fast-exit evidence

| Check | Result |
|---|---|
| Release-ID format | FAIL — `--help` is a CLI flag, not a YYYYMMDD-prefixed release ID |
| PM signoff artifact | FAIL — no file at `sessions/pm-*/artifacts/release-signoffs/*help*` |
| Ghost check (canonical artifact) | FAIL — no PM groom, QA run, or dev implementation references `--help` as a release |

## Pattern summary (ceo-copilot-2 this session)
All malformed improvement-round items seen by CEO this session share the same root — `improvement-round.sh` dispatches without validating that the release-ID folder name corresponds to a real PM-signed release:
- `--help-improvement-round` ← this item
- `fake-no-signoff-release-id-improvement-round` ← prior item
- `stale-test-release-id-999-improvement-round` ← prior item

## Next actions
- dev-infra: apply `sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch` (ROI 89) — blocks all future malformed dispatches at source
- No other actions required from this item

## Blockers
- None

## ROI estimate
- ROI: 2
- Rationale: Fast-exit only; root cause fix already queued at ROI 89. This is the third corroborating data point — no new escalation needed.
