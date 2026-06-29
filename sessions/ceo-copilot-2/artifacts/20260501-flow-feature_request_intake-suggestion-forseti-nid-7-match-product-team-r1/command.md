- Flow id: feature_request_intake
- Flow run id: suggestion-forseti-nid-7
- Flow node: Match Product Team
- Flow owner seat: ceo-copilot-2
- Flow previous node: Intake Review
- Flow source outbox: sessions/ceo-copilot-2/outbox/20260501-flow-feature_request_intake-suggestion-forseti-nid-7-intake-review-r3.md
- Product team selection required: yes
- Available product teams: forseti | dungeoncrawler | forseti-jobhunter-automation | forseti-safety-application | forseti-agent-tracker | infrastructure | open-source | integrations
- Flow incoming conditions: Valid request
- Available flow outcomes: Team selected | No confident team match

# Flow handoff: feature_request_intake / Match Product Team

This inbox item was routed automatically from `Intake Review` after `ceo-copilot-2` completed the previous step.

## Required action
1. Execute the responsibilities of `Match Product Team` as the owning seat `ceo-copilot-2`.
2. Review the source outbox: `sessions/ceo-copilot-2/outbox/20260501-flow-feature_request_intake-suggestion-forseti-nid-7-intake-review-r3.md` for the completed upstream context.
3. If this node has branching outcomes, include one or more `- Flow outcome:` lines in your outbox using the exact allowed values listed above.
4. If this node has only one direct next step, no Flow outcome line is required.
5. If the work is complete but needs a graph-defined branch (for example scope rebaseline, QA failure, or requested changes), keep `- Status: done` and use the matching `- Flow outcome:` line instead of escalating through a legacy `needs-*` artifact.
6. If product-team selection is required for this node, include `- Product team id: <team-id>` using one of the listed product-team IDs.

## Accepted status values
- The only accepted `- Status:` values are: `done | in_progress | blocked | needs-info`
- Use `- Status: done` when this node is complete, even if you also need a graph branch via `- Flow outcome:`.
- Use `- Status: in_progress` only when you are actively continuing the same inbox item and it should remain queued.
- Use `- Status: blocked` or `- Status: needs-info` only when you truly cannot proceed and must escalate.

## Required outbox template
```md
- Status: done | in_progress | blocked | needs-info
- Summary: <one paragraph>

## Next actions
- <next action>

## Blockers
- <explicit blocker or `None`>

## Needs from Supervisor
- <specific need, or `None` when status is done/in_progress>
```
