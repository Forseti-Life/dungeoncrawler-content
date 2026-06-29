- Status: done
- Summary: Fast-exit for improvement-round sentinel item `--help-improvement-round`. This folder was created because `improvement-round.sh` accepts any string as `$1` (DATE_YYYYMMDD arg) with zero validation — passing "--help" as the first argument sets `DATE_YYYYMMDD="--help"` and produces the folder name `--help-improvement-round`. No gate logic fires, no real release exists (release-signoff-status.sh exits 1 for release-id "--help"). This is bypass variant 4 (flag injection / no $1 input validation), distinct from the three no-date-prefix variants documented in prior cycles. The fix spec at `sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch/command.md` has been amended to add $1 validation (reject anything not matching `^[0-9]{8}$`) and a corresponding acceptance-criteria test case. All four bypass variants are now fully specified. Committed at `[see below]`.

## Next actions
- dev-infra: execute `20260405-scope-filter-improvement-round-dispatch` — all four bypass variants now covered (website_scope filter, bare suffix, non-YYYYMMDD suffix, $1 flag injection)
- pm-infra inbox is now clear of sentinel items; next real work item will be release-bound

## Blockers
- None.

## ROI estimate
- ROI: 4
- Rationale: Fourth and final bypass variant documented; fix already delegated. Incremental value is the $1 validation spec addition preventing future flag-injection inbox pollution.
