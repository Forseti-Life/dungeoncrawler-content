- Flow id: feature_request_intake
- Flow run id: suggestion-forseti-nid-2
- Flow node: PM Scope Decision
- Flow owner seat: pm-forseti
- Flow previous node: BA Requirements Review
- Flow source outbox: sessions/ba-forseti/outbox/20260428-flow-feature_request_intake-suggestion-forseti-nid-2-ba-requirements-review-r2-retry.md
- Flow owner binding: product_team.pm_agent
- Product team id: forseti
- Product team label: Forseti
- Flow incoming conditions: Requirements ready
- Available flow outcomes: Approved for delivery | Changes requested | Parked in backlog

# Flow handoff: feature_request_intake / PM Scope Decision

This inbox item was routed automatically from `BA Requirements Review` after `ba-forseti` completed the previous step.

## Required action
1. Execute the responsibilities of `PM Scope Decision` as the owning seat `pm-forseti`.
2. Review the source outbox: `sessions/ba-forseti/outbox/20260428-flow-feature_request_intake-suggestion-forseti-nid-2-ba-requirements-review-r2-retry.md` for the completed upstream context.
3. If this node has branching outcomes, include one or more `- Flow outcome:` lines in your outbox using the exact allowed values listed above.
4. If this node has only one direct next step, no Flow outcome line is required.
5. If product-team selection is required for this node, include `- Product team id: <team-id>` using one of the listed product-team IDs.
- Agent: pm-forseti
- Status: pending
