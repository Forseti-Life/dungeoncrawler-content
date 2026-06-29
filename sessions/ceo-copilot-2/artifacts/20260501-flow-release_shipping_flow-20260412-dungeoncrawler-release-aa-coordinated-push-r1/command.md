- Flow id: release_shipping_flow
- Flow run id: 20260412-dungeoncrawler-release-aa
- Flow node: Coordinated Push
- Flow owner seat: ceo-copilot-2
- Flow previous node: PM Signoff Readiness Check
- Flow source outbox: sessions/pm-dungeoncrawler/outbox/20260501-flow-release_shipping_flow-20260412-dungeoncrawler-release-aa-pm-signoff-readiness-check-r3.md
- Product team id: dungeoncrawler
- Product team label: Dungeoncrawler
- Flow incoming conditions: Ready for signoff and push
- Flow direct route available: yes

# Flow handoff: release_shipping_flow / Coordinated Push

This inbox item was routed automatically from `PM Signoff Readiness Check` after `pm-dungeoncrawler` completed the previous step.

## Required action
1. Execute the responsibilities of `Coordinated Push` as the owning seat `ceo-copilot-2`.
2. Review the source outbox: `sessions/pm-dungeoncrawler/outbox/20260501-flow-release_shipping_flow-20260412-dungeoncrawler-release-aa-pm-signoff-readiness-check-r3.md` for the completed upstream context.
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
