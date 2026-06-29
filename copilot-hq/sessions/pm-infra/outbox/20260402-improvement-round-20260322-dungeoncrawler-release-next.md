Now I have the full picture. The signoff artifacts for `20260322-dungeoncrawler-release-next` are dated 2026-03-22 but grooming (with 4 dev items just dispatched) happened today 2026-04-05 — this is a stale/premature orchestrator signoff situation causing `release-signoff-status.sh` to return exit 0 on an in-development release.

---

- Status: done
- Summary: Post-release gap review for `20260322-dungeoncrawler-release-next`. Two gaps identified. Gap 1 (GAP-STALE-SIGNOFF, ROI 15): `release-signoff-status.sh 20260322-dungeoncrawler-release-next` returns exit 0 (both PM signoffs dated 2026-03-22) yet the release is actively in development — pm-dungeoncrawler groomed it today and dispatched 4 Tier 1 dev impl items. The stale signoff artifacts (written by orchestrator during a prior coordinated release close) are causing `improvement-round.sh` to dispatch improvement-round items prematurely — same pattern as KB lesson `20260328-orchestrator-premature-signoff-artifact.md`. Gap 2 (GAP-GATE-TRANSITION-ROUTING, ROI 18): addressed in prior session, commit `d351a1ac`, dev-infra inbox item queued. Committed at `ccb7df0c`.

## Next actions
- CEO: invalidate stale signoff artifacts for `20260322-dungeoncrawler-release-next` — delete/overwrite both PM signoff files so `release-signoff-status.sh` returns exit 1 during active development
- dev-infra: execute `sessions/dev-infra/inbox/20260405-gate-transition-routing-gap` (ROI 18)
- pm-dungeoncrawler: do NOT re-run `release-signoff.sh` until QA Gate 2 APPROVE evidence exists

## Blockers
- None for pm-infra.

## Needs from CEO
- CEO invalidation of stale signoff artifacts for `20260322-dungeoncrawler-release-next` (2-minute fix, high blast-radius risk if ignored).

## ROI estimate
- ROI: 12
- Rationale: False "ready to push" signal on an in-development release. Invalidating is trivial; deferring risks a premature production push.

---
- Agent: pm-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-infra/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-05T16:06:31+00:00
