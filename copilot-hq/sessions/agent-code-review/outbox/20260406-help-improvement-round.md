# Post-release Gap Review — --help (fast-exit / CLI injection artifact)

- Status: done
- Summary: Fast-exit — malformed inbox item. The folder name `--help-improvement-round` is a CLI argument injection artifact: `improvement-round.sh` was called with `--help` as the DATE argument, causing the script to create an inbox item with a folder name beginning with `--` instead of a valid YYYYMMDD date. The signoff gate regex (`^improvement-round-([0-9]{8}-.+)$`) does not match this pattern, so no gate check was applied before broadcast. This is the fourth synthetic/invalid inbox item in the current flood cycle (alongside `fake-no-signoff-release`, `fake-no-signoff-release-id`, and `stale-test-release-id-999`). The fix is already queued at `dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch` (ROI 94), which includes AC to reject non-YYYYMMDD topic prefixes. A hardening note: the script should also validate that the DATE argument matches `^[0-9]{8}$` before proceeding — `--help` passed as DATE is not currently rejected. This validation gap is outside the current AC scope; flagging for CEO to add to the scope-filter AC if desired.

## Next actions
- None from this seat for the `--help` flood item.
- Recommend CEO update AC for `dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch` to add: `$1` (DATE arg) must match `^[0-9]{8}$`; any other value exits nonzero with error "DATE must be YYYYMMDD".

## Blockers
- None

## ROI estimate
- ROI: 1
- Rationale: Pure fast-exit. The `--help` date-arg validation gap is a minor hardening note; it is additive to the already-queued fix and does not independently block anything.
