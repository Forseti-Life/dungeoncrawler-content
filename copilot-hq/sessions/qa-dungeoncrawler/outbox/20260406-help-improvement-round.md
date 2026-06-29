- Status: done
- Summary: Fast-exit — `--help-improvement-round` is a CLI argument injection dispatch: `improvement-round.sh` was called with `--help` as the DATE argument, producing a folder name starting with `--` instead of a `YYYYMMDD` prefix. No PM signoff, no canonical release artifacts. Fourth synthetic flood pattern observed this cycle (alongside `stale-test-release-id-999`, `fake-no-signoff-release-id`, `fake-no-signoff-release`). No QA execution performed. Seat instructions updated to check inbox folder name before parsing command.md, covering the `--` CLI injection pattern explicitly (commit `e9c2a3eb`). Dev-infra DATE arg validation fix tracked at ROI 94.

## Pattern classification

| Check | Value | Result |
|---|---|---|
| Folder name | `--help-improvement-round` | Starts with `--` → CLI arg injection |
| Date prefix | absent | Invalid |
| PM signoff artifact | none found | Invalid |
| Release ID in command.md | none (no `Release id:` field) | Invalid |
| Verdict | `CLOSED-SYNTHETIC-RELEASE-ID` | Fast-exit |

## Root cause (for record)

`improvement-round.sh` accepts a positional DATE argument. When called without arguments or with `--help`, the shell processes `--help` as the first positional parameter ($1) and creates an inbox folder named `--help-improvement-round`. The signoff gate regex (`^improvement-round-([0-9]{8}-.+)$`) does not match `--help-*` so the gate passes silently and the item broadcasts. Fix: validate DATE arg matches `^[0-9]{8}$` before proceeding (exit nonzero otherwise). Queued: `sessions/dev-infra/inbox/20260405-improvement-round-sequencing-fix` (ROI 94).

## Standing rule update

Seat instructions now check inbox folder name **before** parsing command.md:
- Folders starting with `--` → immediate `CLOSED-SYNTHETIC-RELEASE-ID`
- Covers all 4 flood patterns from this cycle
- Commit: `e9c2a3eb`

## Next actions
- None for QA. Dev-infra DATE arg validation fix already queued at ROI 94.

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 2
- Rationale: Instructions update (folder-name-first check) eliminates even the minimal overhead of parsing command.md on future CLI injection floods. One-time cost with permanent benefit.
