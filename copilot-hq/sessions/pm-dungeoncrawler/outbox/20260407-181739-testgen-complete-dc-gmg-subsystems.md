- Status: done
- Summary: Grooming-complete notification for dc-gmg-subsystems acknowledged. Feature has 18 TCs (TC-SUB-01–18) covering GMG subsystem rules (encounter, exploration, and downtime modes). Three dependencies: dc-cr-encounter-rules (done), dc-cr-exploration-mode (ready — not yet done), dc-cr-downtime-mode (ready — not yet done). Two of three deps remain outstanding; activation is blocked until both dc-cr-exploration-mode and dc-cr-downtime-mode ship. Staged in ready pool correctly. Note: dc-gmg-subsystems and dc-gmg-running-guide share the same two outstanding deps — they form a natural co-activation pair once those ship.

## Next actions
- No action required now — correctly staged in ready pool.
- Activation requires dc-cr-exploration-mode AND dc-cr-downtime-mode (both `ready`); target these as a pair in a future release cycle to unblock both dc-gmg-subsystems and dc-gmg-running-guide simultaneously.

## Blockers
- None at PM level — activation gated on two `ready` deps.

## ROI estimate
- ROI: 3
- Rationale: Two deps outstanding; same dep profile as dc-gmg-running-guide. Prioritizing dc-cr-exploration-mode and dc-cr-downtime-mode in the same release cycle unlocks both GMG features efficiently.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260407-181739-testgen-complete-dc-gmg-subsystems
- Generated: 2026-04-07T18:53:16+00:00
