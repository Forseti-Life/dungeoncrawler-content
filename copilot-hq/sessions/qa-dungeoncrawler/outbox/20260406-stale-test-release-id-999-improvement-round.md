- Status: done
- Summary: Fast-exit — `stale-test-release-id-999` is a synthetic flood dispatch (no YYYYMMDD prefix, no PM signoff, no canonical release artifacts), confirmed by CEO + BA + dev-forseti-agent-tracker across a 26-inbox broadcast on 2026-04-06. No QA verification steps executed. Seat instructions updated with a synthetic/malformed release-ID fast-exit standing rule to prevent future execution cost on this pattern (commit `15a85c7a`). Dev-infra fix tracked via `sessions/dev-infra/inbox/20260405-improvement-round-sequencing-fix` (ROI 89).

## Release ID validation applied

- Release ID in command: `stale-test-release-id-999`
- Expected format: `YYYYMMDD-<site>-release[-suffix]`
- Verdict: `CLOSED-SYNTHETIC-RELEASE-ID` — no date prefix, synthetic marker (`stale-test`, `999`), no PM signoff
- Other seats: BA (`stale-test-release-id-999-improvement-round.md`) and dev-forseti-agent-tracker same cycle — both fast-exited

## Standing rule added

New section in `org-chart/agents/instructions/qa-dungeoncrawler.instructions.md`:
- "Synthetic/malformed release-ID fast-exit (required)"
- Validates release ID format before any execution; fast-exits with `CLOSED-SYNTHETIC-RELEASE-ID` on match
- Commit: `15a85c7a`

## Next actions
- None for QA. Dev-infra release-ID dispatch validation fix (ROI 89) is already queued.

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 2
- Rationale: Standing rule prevents 3-check overhead on every future synthetic flood item of this type; one-time instructions update with permanent overhead reduction.
