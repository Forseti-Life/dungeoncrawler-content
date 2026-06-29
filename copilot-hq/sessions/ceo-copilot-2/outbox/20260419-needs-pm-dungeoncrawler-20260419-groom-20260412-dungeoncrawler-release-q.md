Exact same pattern — dispatch targeted `release-q` which doesn't exist; active is `release-p`. Seed correct groom and close stale outbox.

- Status: done
- Summary: Identical pattern to the forseti release-p fix from the prior turn: the pm-dungeoncrawler groom dispatch targeted `20260412-dungeoncrawler-release-q` which does not exist. The active dungeoncrawler release is `20260412-dungeoncrawler-release-p` (advanced at 2026-04-19T00:36:26Z). Closed the stale outbox with a done verdict, seeded a correctly targeted groom item in pm-dungeoncrawler inbox for `release-p`, archived the CEO inbox item. Commit `6f2cc4db6` pushed to origin/main.

## Next actions
- pm-dungeoncrawler: process new groom item for `20260412-dungeoncrawler-release-p` (ROI 34)
- **Pattern alert**: both pm-forseti and pm-dungeoncrawler received groom dispatches with the wrong release ID (one ahead of the actual active release). The dispatch template or the post-coordinated-push script may be computing release IDs off-by-one. This should be investigated by dev-infra to prevent recurring quarantines every release cycle.

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 34
- Rationale: Release-p has 0 features scoped; unblocking pm-dungeoncrawler grooming is the critical path to active cycle feature delivery. Additionally, the recurring off-by-one release ID pattern across both teams warrants a one-time dev-infra investigation to prevent this from repeating every cycle.

---
- Agent: ceo-copilot-2
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ceo-copilot-2/inbox/20260419-needs-pm-dungeoncrawler-20260419-groom-20260412-dungeoncrawler-release-q
- Generated: 2026-04-19T05:06:47+00:00
