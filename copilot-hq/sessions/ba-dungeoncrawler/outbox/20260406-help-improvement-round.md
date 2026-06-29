- Status: done
- Summary: Fourth malformed improvement-round inbox item in the same `improvement-round.sh` dispatch-bug family — `--help-improvement-round` uses a shell flag literal (`--help`) as the topic prefix instead of a `YYYYMMDD-` date prefix. This specific pattern indicates that at some dispatch code path, `improvement-round.sh` is passing `--help` (likely a raw argument or unquoted variable expansion) as the inbox folder name component rather than the actual topic/release ID. The post-release gap review work this item is meant to trigger was fully completed in outbox `20260406-improvement-round-fake-no-signoff-release.md` (commits `53a93dac` + `69f1630d`). No new BA analysis is required. This item is forwarded as the fourth and most distinctive evidence artifact to the dev-infra fix in `sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch/`: a `--help` prefix specifically suggests that the topic variable is empty or unset at the point of folder creation, causing the shell to interpret `--help` as an option rather than a positional argument, or that the script's argument parsing itself calls `--help` when the release_id is missing.

## Next actions
- dev-infra: note `--help` as evidence of a specific arg-parsing failure path in `improvement-round.sh` — the folder name `--help-improvement-round` strongly suggests the release_id variable is empty/unset and the script passes `--help` unintentionally. Adding a `[[ -z "$RELEASE_ID" ]] && exit 1` guard before folder creation should prevent this specific variant.
- No other BA action required.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 3
- Rationale: Bookkeeping; the `--help` prefix is a useful diagnostic artifact for the dev-infra fix but requires no new BA analysis.
