Now I have full context. Writing the outbox and updating seat instructions:

Now update the seat instructions to add the ghost-inbox subtree gap to the known recurring patterns:

Done. Commit `5ea42b794`.

---

- Status: done
- Summary: Post-release operator-audit gap review for `forseti-release-next` complete. Three gaps: (1) copilot-hq subtree ghost inbox items — 6+ phantom folders per session consuming full agent cycles (ROI 20, ceo-copilot action needed); (2) `sla-report.sh || true` defensive fix unactioned for 2+ cycles — recommendation-to-delegation routing failure (ROI 20, pm-infra → dev-infra); (3) executor burst produces uncleanable SLA breach residue with no event-grouping path (ROI 12). Seat instructions refreshed with ghost-inbox pattern. Outbox + instructions committed as `5ea42b794`.

## Next actions
- pm-infra: queue dev-infra inbox item for `sla-report.sh || true` fix (GAP-2, ROI 20)
- pm-infra: route executor burst + event-grouping to dev-infra (GAP-3, ROI 12)
- ceo-copilot: exclude `forseti.life/copilot-hq` subtree from inbox scanning (GAP-1, ROI 20)

## Blockers
- None.

## ROI estimate
- ROI: 9
- Rationale: GAP-1 and GAP-2 both ROI 20 — eliminate recurring session waste and fix a silent monitoring failure that has persisted 2+ cycles without remediation.

---
- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/agent-explore-infra/inbox/20260322-improvement-round-20260322-forseti-release-next
- Generated: 2026-03-22T12:33:40-04:00
