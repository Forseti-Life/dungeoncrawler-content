# Lesson Learned: ba-infra activation — what a well-formed infra requirements artifact looks like

- Date: 2026-02-28
- Agent(s): ba-infra
- Website: infrastructure
- Module(s): org-chart/agents/instructions/ba-infra.instructions.md, requirements artifacts

## What happened

ba-infra has never been formally activated (no `features/infra-*` spec has been created and routed to it
as of 2026-02-28). As a result, there is no reference example of what a completed infra requirements
artifact should contain. If ba-infra is activated in a future cycle, the agent would need to derive the
format from the general BA role instructions, which may not fit infra-specific work well.

## Root cause

Infrastructure requirements differ from website feature requirements:
- No users/personas — the "users" are agents and operators running scripts.
- No UI flows — flows are shell command sequences or agent inbox/outbox sequences.
- Acceptance criteria are structural/behavioral (file exists, script exits 0, agent produces correct output)
  rather than visual or UX-based.

The general BA role instructions (`org-chart/roles/business-analyst.instructions.md`) define the standard
requirements artifact format, but do not account for these differences.

## Impact

- Low (preventive): no active ba-infra work exists, so no blocked cycle has occurred.
- Medium (latent): if ba-infra is activated without a reference format, the first artifact will require
  iteration and may not meet pm-infra or dev-infra's expectations.

## Detection / Signals

A requirements artifact produced by ba-infra for an infra change should answer:
1. **What behavior is changing?** (script, runbook, agent scope, QA suite — one or more)
2. **What is the current state?** (command output, file listing, or flow description)
3. **What is the target state?** (specific, runnable definition — e.g., "script exits 0 with X output")
4. **Who/what invokes this?** (agent, executor, cron, manual operator — not a human user)
5. **What does done look like?** (at least one verifiable check, e.g., `bash -n scripts/foo.sh` passes)
6. **What are the failure modes?** (script missing, wrong permissions, dependency absent)
7. **What is the rollback?** (revert commit, restore prior file, no rollback needed — must be explicit)

## Fix applied (if any)

None required for current cycle; this is a reference document for future activation.

## Prevention (process + code)

When ba-infra is activated for an infra change, the requirements artifact must include:

### Infra requirements artifact template (minimal)

```
## Change summary
- What: <script/runbook/agent scope/QA suite being changed>
- Trigger: <what activates this behavior — agent inbox item, cron, operator command>

## Current state
- <Describe what exists today, with a verifiable command to observe it>

## Target state
- <Describe what must be true after the change, with a verifiable command to confirm it>

## Acceptance criteria (must be testable)
- AC1: <command/check that proves done> → expected result
- AC2: (add as needed)

## Failure modes
- FM1: <what breaks if prerequisite is missing> → expected error/signal
- FM2: (add as needed)

## Rollback
- <How to undo this change if needed, or "N/A — no state change persisted">

## Non-goals
- <What this change explicitly does NOT cover>

## Open questions
- <Any unresolved ambiguities — recommend a default answer for each>
```

This template is intentionally leaner than the website-feature BA format (no personas, no UI flows,
no permissions matrix). It maps directly to dev-infra's implementation needs and qa-infra's verification gate.

## References

- BA role instructions: `org-chart/roles/business-analyst.instructions.md`
- BA seat instructions: `org-chart/agents/instructions/ba-infra.instructions.md`
- Activation threshold: `knowledgebase/lessons/20260228-ba-infra-activation-threshold-rationale.md`
- Idle cycle pattern: `knowledgebase/lessons/20260227-ba-infra-idle-cycle-pattern.md`
