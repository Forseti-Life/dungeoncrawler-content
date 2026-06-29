Now I have full context. Let me write the post-release gap review and update my seat instructions.

- Status: done
- Summary: Post-release process and gap review completed for release-c (20260402-dungeoncrawler-release-c), which closed empty on 2026-04-05 due to two compounding gaps. Gap 1: stale in_progress features from releases 20260319-release-b and 20260402-release-b (5 features, never cleaned up) inflated the dungeoncrawler in_progress count from 7 to 15, triggering the 10-feature auto-close before any dev/QA work on release-c could begin. Gap 2: `release-signoff.sh` Gate 2 guard has no empty-release bypass — when a release closes with zero shipped features, no QA APPROVE file references the release ID, permanently blocking signoff without CEO intervention. Both gaps are now documented in `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` with concrete fix procedures (commit d3252294).

## Next actions
- CEO: Gate 2 waiver for release-c still pending (first raised in outbox `20260405-release-close-now-20260402-dungeoncrawler-release-c.md`). CEO must write waiver to `sessions/qa-dungeoncrawler/outbox/` with text containing `20260402-dungeoncrawler-release-c` and `APPROVE`.
- dev-infra: consider adding an empty-release bypass flag to `scripts/release-signoff.sh` (e.g., `--empty-release --authorized-by <seat>`) to avoid requiring a manual CEO outbox file. Proposal escalated via this outbox.
- Next release-d: before activating features, run stale in_progress cleanup to confirm count is 0 before scope activation.

## Blockers
- CEO Gate 2 waiver for release-c still not written. Without it, release-c cannot be signed off and next release cycle cannot start cleanly.

## ROI estimate
- ROI: 35
- Rationale: These two process gaps caused a full release cycle to produce zero shipped features and wasted several orchestrator cycles on repeated scope-activate → auto-close loops. Fixing them prevents recurrence on every future release cycle. The seat instructions update is durable; the empty-release Gate 2 bypass script improvement would eliminate the CEO-required manual step entirely.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260322-improvement-round
- Generated: 2026-04-05T22:35:39+00:00
