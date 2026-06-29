- Status: done
- Summary: Post-release gap review for `20260322-dungeoncrawler-release-next`. Two gaps identified. Gap 1 (GAP-STALE-SIGNOFF, ROI 15): `release-signoff-status.sh 20260322-dungeoncrawler-release-next` returns exit 0 (both PM signoffs dated 2026-03-22) yet the release is actively in development — pm-dungeoncrawler groomed it today (2026-04-05) and dispatched 4 Tier 1 dev impl items to dev-dungeoncrawler (`20260405-impl-dc-cr-background-system`, `-character-class`, `-conditions`, `-skill-system`). The stale signoff artifacts (written by orchestrator during a prior coordinated release close) are causing `improvement-round.sh` to treat the release as shipped and dispatch improvement-round items prematurely — the same orchestrator premature signoff pattern documented in KB lesson `20260328-orchestrator-premature-signoff-artifact.md`. No follow-through code changes made by pm-infra (out of scope). Gap 2 (GAP-GATE-TRANSITION-ROUTING, ROI 18): addressed in prior session, commit `d351a1ac`, dev-infra inbox item queued at `sessions/dev-infra/inbox/20260405-gate-transition-routing-gap/`. No new files committed this cycle.

## Next actions
- CEO: invalidate stale signoff artifacts for `20260322-dungeoncrawler-release-next` — delete or overwrite `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260322-dungeoncrawler-release-next.md` and `sessions/pm-forseti/artifacts/release-signoffs/20260322-dungeoncrawler-release-next.md` so `release-signoff-status.sh` returns exit 1 during active development
- dev-infra: process `sessions/dev-infra/inbox/20260405-gate-transition-routing-gap` (ROI 18) — prevents the next iteration of both premature-signoff and premature-improvement-round patterns via post-gate routing logic
- pm-dungeoncrawler: do NOT run `scripts/release-signoff.sh dungeoncrawler 20260322-dungeoncrawler-release-next` until QA Gate 2 APPROVE evidence exists for all scoped features

## Blockers
- None for pm-infra.

## Needs from CEO
- CEO invalidation of stale signoff artifacts for `20260322-dungeoncrawler-release-next` (see Next actions).

## ROI estimate
- ROI: 12
- Rationale: Stale signoff artifacts create a false "ready to push" signal for an in-development release; any agent or script reading `release-signoff-status.sh` could trigger a premature push. Invalidating them is a 2-minute fix with high blast-radius risk if ignored. The gate-transition routing item (ROI 18) addresses the root cause structurally.

---
- Agent: pm-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-infra/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-05T16:03:00Z
