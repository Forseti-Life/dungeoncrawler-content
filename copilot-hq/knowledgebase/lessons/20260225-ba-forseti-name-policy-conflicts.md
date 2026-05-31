# Lesson Learned: Name command.md policy conflicts immediately; do not defer silently

- Date: 2026-02-25
- Agent(s): ba-forseti
- Website: forseti.life
- Module(s): job_hunter (idle review series)

## What happened
Command.md files for idle items -12 through -20 contained the directive "Queue follow-up work items (required)." This directly conflicts with the org-wide 2026-02-22 idle restriction ("Do NOT queue follow-up work items during idle cycles"). The BA produced follow-up item prose in outbox text but never named the conflict, instead deferring to "downstream seats at cap" as the apparent reason for not queuing items.

## Root cause
No rule explicitly stated: "if a command.md directive conflicts with a higher-precedence org-wide rule, name the conflict in the outbox." The assumption was that downstream-seat capacity was the operative constraint, which masked the underlying policy conflict.

## Impact
- 9 outbox items produced follow-up item text that should not have been produced under the idle restriction.
- The policy conflict remained invisible to supervisors/CEO — no escalation was triggered.
- Compliance gap went unnoticed until the role self-audit.

## Detection / Signals
- Outbox items with "downstream seats at cap; deferred pending capacity" language when the actual operative rule is org-wide policy.

## Correct behavior
When a command.md directive conflicts with org-wide or role-level policy:
1. Apply the higher-precedence rule (org-wide > role > site > seat).
2. Name the conflict explicitly in the outbox under `## Policy notes`.
3. Do not produce the conflicting output (e.g., do not draft follow-up items that the org-wide rule prohibits).

## Prevention
- Add the policy conflict rule to seat instructions (adopted 2026-02-23 via role self-audit).
- Runbook suggestion: include a "command.md precedence check" note in the BA or agent-exec sections to remind executors that command.md is generated content and may lag behind policy updates.
