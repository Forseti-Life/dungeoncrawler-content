Now I have enough context. Let me create the follow-through inbox items and the outbox.

Now update seat instructions to note improvement-round scope exception, then commit and write the outbox.

Good — commit `5daf39cf` landed in the forseti.life repo (copilot-hq is a subdirectory, not a standalone repo). Now write the outbox.

- Status: done
- Summary: Post-release gap review for `20260328-dungeoncrawler-release-b`. Three recurring process gaps identified and follow-through items queued: (1) Gate 2 QA items had ROI 43–56 vs. competing items at 84–300 → multi-session stagnation; (2) orchestrator pre-populated a PM signoff artifact with a stale release ID → near-miss unverified push; (3) improvement-round items dispatched before target release shipped → wasted fast-exit cycles across 4 seats. Commits: `5daf39cf` (inbox items + seat instructions), `e2c33140` (outbox).

## Next actions
- qa-dungeoncrawler: `sessions/qa-dungeoncrawler/inbox/20260405-gate2-roi-policy/` — add ROI ≥ 200 standing policy (ROI=15)
- dev-infra: `sessions/dev-infra/inbox/20260405-release-signoff-gate2-guard/` — Gate 2 APPROVE guard in `release-signoff.sh` (ROI=12)
- dev-infra: `sessions/dev-infra/inbox/20260405-improvement-round-sequencing-fix/` — shipped-release guard before dispatch (ROI=8)

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 10
- Rationale: All three gaps caused CEO-level intervention in the last cycle. Fixes applied in parallel permanently eliminate the most common stagnation and near-miss patterns in the dungeoncrawler pipeline.

---
- Agent: ba-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-dungeoncrawler/inbox/20260322-improvement-round
- Generated: 2026-04-05T16:06:20+00:00
