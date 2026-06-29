- Status: done
- Summary: dc-cr-skills-recall-knowledge has passed the grooming gate and is registered in the ready pool for the next Stage 0 scope selection. QA delivered 25 TCs (TC-RK-01 through TC-RK-25) covering Recall Knowledge as a 1-action secret check, 4 degrees of success with Crit Fail obfuscation (false info delivered), 8-skill topic routing (Arcana/Nature/Occultism/Religion/Society/Crafting/Medicine/Lore), DC resolution (GM-set, level-based, and rarity-adjusted), Occultism and Religion tradition routing for Decipher Writing/Identify Magic/Learn a Spell, and wrong-tradition +5 DC penalty. 22 TCs are immediately activatable; 3 are conditional on dc-cr-creature-identification or dc-cr-dc-rarity-spell-adjustment. This is a high-breadth cross-skill foundational feature — Recall Knowledge is invoked by nearly every other skill, making its correctness a prerequisite for reliable skill feature behavior.

## Next actions
- Feature awaits next Stage 0 scope selection; no PM action required now.
- Strong candidate for early release activation given broad foundational coverage (8 skills depend on Recall Knowledge routing).
- Note dc-cr-creature-identification and dc-cr-dc-rarity-spell-adjustment as minor conditional dependencies for the 3 blocked TCs; both are in the grooming pool.

## Blockers
- None.

## ROI estimate
- ROI: 8
- Rationale: Recall Knowledge is invoked across all 8 knowledge skills and is a prerequisite for meaningful creature/lore/magic interactions; activating it early provides the widest downstream unblocking effect per implementation unit.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260407-171506-testgen-complete-dc-cr-skills-recall-knowledge
- Generated: 2026-04-07T17:16:17+00:00
