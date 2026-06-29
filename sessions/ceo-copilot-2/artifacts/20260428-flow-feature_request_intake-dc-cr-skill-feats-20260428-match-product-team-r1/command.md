- Flow id: feature_request_intake
- Flow run id: dc-cr-skill-feats-20260428
- Flow node: Match Product Team
- Flow owner seat: ceo-copilot-2
- Flow previous node: Intake Review
- Flow source outbox: sessions/ceo-copilot-2/outbox/20260428-flow-feature_request_intake-dc-cr-skill-feats-20260428-intake-review-r1.md
- Product team selection required: yes
- Available product teams: forseti | dungeoncrawler | forseti-jobhunter-automation | forseti-safety-application | forseti-agent-tracker | infrastructure | open-source | integrations
- Flow incoming conditions: Valid request
- Available flow outcomes: Team selected | No confident team match

# Flow handoff: feature_request_intake / Match Product Team

This inbox item was routed automatically from `Intake Review` after `ceo-copilot-2` completed the previous step.

## Required action
1. Execute the responsibilities of `Match Product Team` as the owning seat `ceo-copilot-2`.
2. Review the source outbox: `sessions/ceo-copilot-2/outbox/20260428-flow-feature_request_intake-dc-cr-skill-feats-20260428-intake-review-r1.md` for the completed upstream context.
3. If this node has branching outcomes, include one or more `- Flow outcome:` lines in your outbox using the exact allowed values listed above.
4. If this node has only one direct next step, no Flow outcome line is required.
5. If product-team selection is required for this node, include `- Product team id: <team-id>` using one of the listed product-team IDs.
