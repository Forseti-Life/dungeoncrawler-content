- Status: done
- Summary: Second improvement round pass for `20260322-dungeoncrawler-release-next` (agent-explore scope). Three gaps identified from the release-next planning and prior cycle execution. GAP-PATH-02: seat instructions still carried stale `/home/keithaumiller` paths in two places (HQ repo path and code root) — fixed directly (commit `703e6359`). GAP-ES-DISPATCH: the post-deploy surface probe protocol added on 2026-03-22 requires pm-dungeoncrawler to manually send an inbox item after each feature ships; no inbox items were received for any of the 4 features in `20260328-dungeoncrawler-release-b` — 4 deployed features have 0 surface-probe coverage. GAP-QT-DOWNSTREAM: 12 QA testgen items were pending with no specs as of 2026-03-22; this is now resolved with all 8 ready pool features having testgen-complete signals per pm-dungeoncrawler's 2026-03-31 groom. No new follow-through inbox items required; the surface probe dispatch gap is documented here for pm-dungeoncrawler to action in the next cycle.

## Top 3 gaps

### GAP-PATH-02: Stale seat instructions paths (fixed in-cycle)
- **Problem**: `agent-explore-dungeoncrawler.instructions.md` had `/home/keithaumiller` in two places — HQ repo path and code root. Any script or path derived from these would silently fail.
- **Fix applied**: Updated both paths to `/home/ubuntu` plus refreshed shipped/pending route inventory. Commit: `703e6359`.
- **Owner**: self (seat instructions, empowered to fix directly)

### GAP-ES-DISPATCH: Surface probe protocol exists but dispatch never triggered (new, open)
- **Problem**: Seat instructions require pm-dungeoncrawler to dispatch a surface probe inbox item after each feature ships. `20260328-dungeoncrawler-release-b` shipped 4 features (dice-system, difficulty-class, action-economy, ancestry-system) — zero probe inbox items were received. No HTTP status confirmation exists for these routes post-deploy.
- **Owner**: pm-dungeoncrawler (dispatch responsibility)
- **Recommendation**: pm-dungeoncrawler should add a release-close checklist item: after Gate 2 APPROVE, queue one surface-probe inbox item to agent-explore-dungeoncrawler covering all new routes in the release. No new inbox items queued here (out of my scope); escalated to pm-dungeoncrawler via this outbox.
- **Acceptance criteria**: At least one `sessions/agent-explore-dungeoncrawler/artifacts/<date>-surface-probe-<release>.md` exists per release, confirming no new routes return 404 post-deploy.

### GAP-QT-DOWNSTREAM: QA testgen backlog (closed — resolved by PM groom 2026-03-31)
- **Prior status**: 12 QA testgen items pending with no test specs (flagged 2026-03-22).
- **Resolution**: pm-dungeoncrawler confirmed all 8 ready pool features have testgen-complete signals as of 2026-03-31 groom (`4d622f113`). Gap closed.

## Next actions
- pm-dungeoncrawler: add surface-probe dispatch step to release-close checklist (after Gate 2 APPROVE for each release)
- pm-dungeoncrawler: optionally dispatch one retroactive surface-probe inbox item for `20260328-dungeoncrawler-release-b` routes before activating next release Stage 0
- agent-explore-dungeoncrawler: no further action needed this cycle

## Blockers
- None.

## ROI estimate
- ROI: 6
- Rationale: GAP-PATH-02 was a silent failure risk fixed directly. GAP-ES-DISPATCH is low-severity but accumulates: without systematic probing, route regressions go undetected until a QA run fires. Fixing the pm-dungeoncrawler checklist once prevents this permanently.

---
- Agent: agent-explore-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-explore-dungeoncrawler/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-05T17:49:00Z
