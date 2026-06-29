- Status: done
- Summary: Wrote `features/dc-cr-skills-stealth-hide-sneak/03-test-plan.md` with 20 TCs (TC-STL-01 through TC-STL-20) covering: Conceal an Object (1-action Manipulation, item gate, Crit Success/Success Seek-DC tiers); Hide (cover/concealment gate, Stealth vs each observer's Perception DC, all-or-nothing multi-observer rule — one beat = remains Observed, Observed→Hidden transition, Hidden-breaking action list); Sneak (Hidden gate, half-Speed rounded to 5-ft intervals, speed rounding is not a block, per-observer Stealth check at end of move, Success = remain Hidden / Failure = Observed, open-destination block); Avoid Notice (exploration mode, Unnoticed start, first-failed-Seek → Observed transition); and ACL regression. Key flag: **11/20 TCs are conditional on dc-cr-conditions** — this is the highest conditional ratio in the CR skills batch, making Stealth the primary consumer of the conditions module. Feature marked `ready`, PM notified. Committed at `b31e24eab`.

## Next actions
- Continue CR skills batch: check inbox for remaining `testgen-dc-cr-skills-*` items
- **Escalation to PM:** Stealth's 11/20 conditional TCs make dc-cr-conditions a critical gating dependency. If Stealth is selected for a release before dc-cr-conditions lands, only 9 TCs activate — recommend PM flag dc-cr-conditions as a co-dependency in Stage 0 planning.

## Blockers
- None

## ROI estimate
- ROI: 9
- Rationale: Stealth is a cross-class core mechanic (Rogue, Ranger, Investigator, and any character using the Hidden condition) and directly feeds the conditions module which is already in Release B scope; unblocking these 11 TCs requires dc-cr-conditions to ship, so this grooming output also flags a dependency chain that PM needs to sequence correctly.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-152523-testgen-dc-cr-skills-stealth-hide-sneak
- Generated: 2026-04-07T16:57:51+00:00
