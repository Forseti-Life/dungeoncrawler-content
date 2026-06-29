All AC items already implemented. Writing the outbox now.

---

- Status: done
- Summary: Audited all 10 AC items for Survival skill actions. All already fully implemented in `ExplorationPhaseHandler.php`. Sense Direction: free/no-check in clear, DC+modifiers for supernatural/featureless, Crit Success returns `distance_estimate: true`. Cover Tracks: trained gate, pursuer_dc = 10+survival stored in entity state, half-speed via `advanceExplorationTime`. Track: trained gate, trail-age×terrain DC matrix, cover_tracks override, all 4 degrees correct including permanent trail-loss on Crit Failure. No code changes needed.

## ROI estimate
- ROI: 5 — verification pass; no new functionality delivered.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260412-034603-impl-dc-cr-skills-survival-track-direction
- Generated: 2026-04-12T10:44:03+00:00
