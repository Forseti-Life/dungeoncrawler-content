- Status: done
- Summary: Wrote `features/dc-cr-skills-crafting-actions/03-test-plan.md` with 30 TCs (TC-CRF-01 through TC-CRF-30) covering Repair (Trained gate, repair-kit gate, exploration 10 min, HP-by-proficiency-rank formula on Crit Success and Success, 2d6-after-Hardness on Crit Failure, destroyed-blocked, full-HP no-op), Craft (Trained gate, downtime type, formula gate, item-level cap, proficiency level gates at 9+/16+, 50% upfront material gate, 4-day minimum plus pause/resume, 4 degrees including 10% material loss on Crit Failure, consumable batch cap 4, ammo standard batch, feat gates for Alchemical/Magical/Snare Crafting), Identify Alchemy (Trained + tools gates, exploration 10 min, Crit Failure returns plausible false data not an error), and ACL regression. 16 TCs immediately activatable at Stage 0; 14 conditional on `dc-cr-equipment-system` (item HP/Hardness/destroyed-state/formula-catalog/cost-tracking/downtime-state/inventory-tool-check). PM notified. Committed at `483cf12a9`.

## Next actions
- Continue skills grooming batch: check inbox for remaining `testgen-dc-cr-skills-*` items
- Begin APG/GMG batch (16 items dispatched 2026-04-07) when skills items are clear
- PM flags to track: rank-to-integer mapping for Repair HP formula; Repair full-HP kit consumption behavior; material cost tracking type (currency vs items); 10% loss rounding; ammo batch sizes; false-ID result definition for automation

## Blockers
- None

## ROI estimate
- ROI: 10
- Rationale: Crafting is core downtime/exploration content; 16 non-equipment TCs are activatable now, and the remaining 14 equipment-dependent TCs will unblock automatically when dc-cr-equipment-system ships in Release B.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-152523-testgen-dc-cr-skills-crafting-actions
- Generated: 2026-04-07T16:29:51+00:00
