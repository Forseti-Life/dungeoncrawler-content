- Status: done
- Summary: Wrote `features/dc-cr-treasure-by-level/03-test-plan.md` with 13 TCs (TC-TBL-01–13) covering: treasure-by-level table completeness (20 rows, 4-PC baseline with currency/permanent items/consumables columns); currency column composition (coins/gems/art objects/half-price items); party size adjustments for parties larger and smaller than 4; selling rules (standard items at half price; gems/art objects/raw materials at full price); downtime buy/sell restriction (soft-flag with GM override, pending PM decision on hard-block vs soft-flag); new and replacement character starting wealth table (level 1–20). Edge cases: selling during encounter/exploration returns flagged-not-blocked; party size < 4 gets proportional reduction without hard block. Failure modes: standard item sold at full price is rejected with corrected half-price value; mid-campaign replacement character at level 5 uses level-5 row not level 1. Three PM notes raised: treasure table values, per-PC adjustment values, and starting wealth values all need BA extraction from CRB tables before Dev can implement; sell-phase enforcement ambiguity (soft-flag vs hard-block) needs PM decision. Feature marked `ready`, PM notified. Committed at `6ed536f87`.

## Next actions
- Process `20260407-153728-testgen-dc-cr-xp-award-system`
- Continue: dc-cr-magic-ch11, dc-cr-decipher-identify-learn, dc-cr-dwarf-ancestry, dc-cr-economy, dc-cr-human-ancestry, dc-cr-rune-system, dc-cr-snares
- APG/GMG batch (16 items) follows

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 7
- Rationale: Treasure-by-level is a core GM tool used in every session for loot distribution; this plan surfaces three separate data gaps requiring BA extraction and a product intent ambiguity, all of which would otherwise stall Dev mid-implementation.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-153728-testgen-dc-cr-treasure-by-level
- Generated: 2026-04-07T17:49:06+00:00
