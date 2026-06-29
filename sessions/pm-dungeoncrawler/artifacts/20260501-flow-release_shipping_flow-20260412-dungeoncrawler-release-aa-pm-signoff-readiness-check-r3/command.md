- Flow id: release_shipping_flow
- Flow run id: 20260412-dungeoncrawler-release-aa
- Flow node: PM Signoff Readiness Check
- Flow owner seat: pm-dungeoncrawler
- Flow previous node: Release QA Verification
- Flow source outbox: sessions/qa-dungeoncrawler/outbox/20260501-flow-release_shipping_flow-20260412-dungeoncrawler-release-aa-release-qa-verification-r1.md
- Flow owner binding: product_team.pm_agent
- Product team id: dungeoncrawler
- Product team label: Dungeoncrawler
- Flow incoming conditions: APPROVE
- Available flow outcomes: Gate 1b incomplete | Gate 2 incomplete | Ready for signoff and push

# Flow handoff: release_shipping_flow / PM Signoff Readiness Check

This inbox item was routed automatically from `Release QA Verification` after `qa-dungeoncrawler` completed the previous step.

## Required action
1. Execute the responsibilities of `PM Signoff Readiness Check` as the owning seat `pm-dungeoncrawler`.
2. Review the source outbox: `sessions/qa-dungeoncrawler/outbox/20260501-flow-release_shipping_flow-20260412-dungeoncrawler-release-aa-release-qa-verification-r1.md` for the completed upstream context.
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

## Node-specific guidance
- Use the release gate artifacts as the source of truth for this decision: confirm Gate 1b via the release code-review outbox and Gate 2 via the QA verification outbox before choosing a flow outcome.
- If Gate 1b still has unresolved MEDIUM+ findings, finish with `- Status: done` and `- Flow outcome: Gate 1b incomplete`; do not claim release readiness.
- If Gate 2 lacks a current APPROVE outbox for this release id, finish with `- Status: done` and `- Flow outcome: Gate 2 incomplete`.
- Only choose `- Flow outcome: Ready for signoff and push` after `bash scripts/release-signoff.sh <team> <release-id>` succeeds and your summary cites the exact PM signoff artifact path.
