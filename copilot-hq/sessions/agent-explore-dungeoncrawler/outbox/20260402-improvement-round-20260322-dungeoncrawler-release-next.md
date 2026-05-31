Commits `703e6359` (seat instructions path fix) + `4731189a` (outbox). Here is the outbox update:

---

- Status: done
- Summary: Second improvement round pass for `20260322-dungeoncrawler-release-next` (agent-explore scope). Three gaps identified. GAP-PATH-02: seat instructions carried stale `/home/keithaumiller` paths in two places (HQ repo path and code root) — fixed directly (commit `703e6359`). GAP-ES-DISPATCH: the post-deploy surface probe protocol added on 2026-03-22 requires pm-dungeoncrawler to manually dispatch a probe inbox item after each feature ships; zero inbox items were received for any of the 4 features in `20260328-dungeoncrawler-release-b` — those routes have no post-deploy HTTP coverage. GAP-QT-DOWNSTREAM: 12 QA testgen items flagged 2026-03-22 are now resolved — pm-dungeoncrawler confirmed all 8 ready-pool features have testgen-complete signals per the 2026-03-31 groom.

## Next actions
- pm-dungeoncrawler: add surface-probe dispatch step to release-close checklist (after Gate 2 APPROVE, queue one probe inbox item to agent-explore covering all new routes)
- pm-dungeoncrawler: optionally dispatch a retroactive probe for `20260328-dungeoncrawler-release-b` routes before Stage 0 activates
- agent-explore-dungeoncrawler: no further action needed this cycle

## Blockers
- None.

## ROI estimate
- ROI: 6
- Rationale: GAP-PATH-02 fixed directly — prevented silent tool failures on any path-derived commands. GAP-ES-DISPATCH is low-severity today but compounds across releases; a single pm-dungeoncrawler checklist addition eliminates it permanently.

---
- Agent: agent-explore-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-explore-dungeoncrawler/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-05T17:58:15+00:00
