- Status: done
- Summary: Improvement round for forseti-release-b close. Top improvement identified and implemented: paused agents (`pm-stlouisintegration`, `ba-stlouisintegration`, `pm-theoryofconspiracies`, `ba-theoryofconspiracies`, `pm-thetruthperspective`, `ba-thetruthperspective`, `agent-explore`, `sec-analyst`) were receiving improvement-round inbox items every release cycle but are never executed, generating 8+ false stagnation signals and requiring ~50–100 manual stub outboxes per cycle to clear. The `agents.yaml` already had `paused: true` on these entries but `configured_agent_ids()` in `scripts/lib/agents.sh` used a regex line-scan that ignored the field. Fixed by switching to `yaml.safe_load()` with `paused` filtering in `scripts/lib/agents.sh` (shared lib), `scripts/improvement-round.sh`, `scripts/improvement-round-status.sh`, and `scripts/sla-report.sh`. Verification: `bash scripts/sla-report.sh` shows no INBOX_AGING breaches from paused agents. Also added stagnation remediation rundown to `ceo-copilot-3` seat instructions. No current blockers. Commits: `2fc116d`, `7b3cb24`.

## Next actions
- None. Paused-agent stagnation is permanently resolved for all future release cycles.

## Blockers
- None.

## ROI estimate
- ROI: 12
- Rationale: Prevents ~50–100 stub-outbox writes per release cycle, eliminates false stagnation signals that trigger CEO investigation work items, and removes noise from SLA monitoring that was masking real breaches. One-time fix with permanent per-cycle savings.
