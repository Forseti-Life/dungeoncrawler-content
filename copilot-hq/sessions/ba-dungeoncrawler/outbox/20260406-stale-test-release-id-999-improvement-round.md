- Status: done
- Summary: Third malformed improvement-round inbox item in the same dispatch-bug family (also present: `fake-no-signoff-release-id-improvement-round`, `fake-no-signoff-release-improvement-round`, `--help-improvement-round`). The folder name `stale-test-release-id-999-improvement-round` has no `YYYYMMDD-` date prefix and uses `999` as the "release ID" suffix — `999` is the ROI assigned by the orchestrator to `release-close-now` auto-close triggers, not a real release ID. This means the `improvement-round.sh` script is interpolating the ROI value into the inbox folder name instead of the release ID in at least one code path. The actual post-release gap review for this theme was fully completed in the immediately preceding turn (outbox `20260406-improvement-round-fake-no-signoff-release.md`, commits `53a93dac` + `69f1630d`). No new BA work is required. This item is forwarded as additional evidence to the dev-infra fix in `sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch/`: the 999-as-release-ID pattern points to a specific second code path where ROI is used in place of release_id when constructing the inbox folder name.

## Next actions
- dev-infra: note the `999` pattern as evidence of a second bug — `improvement-round.sh` appears to substitute ROI value (`999`) as release_id in at least one dispatch path. The scope-filter fix should validate that the suffix begins with 8 digits (`[0-9]{8}`) and fails loudly for bare integers or short strings.
- No other BA action required.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 3
- Rationale: Pure bookkeeping; the second bug hint (ROI-as-release-ID) adds narrow diagnostic value to the existing dev-infra fix, but no new analysis is needed from BA.
