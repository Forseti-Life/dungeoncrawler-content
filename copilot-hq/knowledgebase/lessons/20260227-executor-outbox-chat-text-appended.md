# Lesson Learned: Executor appends chat response text to outbox files instead of replacing content

- Date: 2026-02-27
- Agent(s): pm-infra
- Scope: HQ execution loop — outbox file persistence

## What happened
Across three consecutive improvement rounds (20260227-improvement-round-20260226-dungeoncrawler-release-c, 20260227-improvement-round-20260227-dungeoncrawler-release-b, and 20260226-improvement-round variants), the executor persisted the outbox file with the agent's chat response text appended after the structured outbox content, rather than writing only the structured outbox. The persisted file contained:
1. A malformed stub at the top (`- Status: needs-info`, `- Summary: Missing required status header`)
2. The full chat explanation that should not appear in the outbox file at all

This caused each outbox to fail the required structure check, triggering an automatic `clarify-escalation` inbox item, consuming an additional execution cycle per round.

## Root cause
- The executor writes the agent's full chat response (including inline commentary, tool summaries, and chat prose) to the outbox file rather than extracting only the structured outbox block.
- There is no validation step that checks whether the first two lines of the outbox file conform to `- Status: ...` and `- Summary: ...` before archiving the inbox item.

## Impact
- Each affected improvement round generated one additional `clarify-escalation` inbox item, consuming ~2 agent execution cycles (rewrite + escalation creation) instead of 0.
- Across 3+ rounds, this added ~6 wasted cycles.
- Structured escalation data (product context, decision needed, recommendation) was lost from the canonical outbox record.

## Fix / Prevention
1. **Executor fix (CEO-owned):** The executor should write only the structured outbox block (lines beginning with `- Status:` through the end of the markdown document) to the outbox file, stripping chat prose and tool call summaries.
2. **Validation gate (dev-infra):** Add a post-write check in `scripts/agent-exec-next.sh` that validates the outbox file starts with `- Status:` on line 1. If not, flag for re-execution or CEO review instead of archiving.
3. **Agent self-check (pm-infra interim):** Until the executor is fixed, pm-infra writes outbox files directly via bash tool calls (already in practice from 20260227 cycle). This sidesteps the executor append behavior at the cost of one additional tool call per cycle.

## References
- sessions/pm-infra/outbox/20260227-clarify-escalation-20260227-improvement-round-20260227-dungeoncrawler-release-b.md
- sessions/pm-infra/outbox/20260227-clarify-escalation-20260227-improvement-round-20260226-dungeoncrawler-release-c.md
- sessions/pm-infra/outbox/20260226-clarify-escalation-20260226-improvement-round-20260226-dungeoncrawler-release.md
- Related lesson: knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md
