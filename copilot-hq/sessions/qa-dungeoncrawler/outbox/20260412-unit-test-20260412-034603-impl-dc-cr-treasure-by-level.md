All ACs verified. Now update the checklist and commit.

- Status: done
- Summary: Unit-test verification for `20260412-034603-impl-dc-cr-treasure-by-level` is APPROVE. Dev commit `5ce17e7fd` delivered `TreasureByLevelService` (full CRB Table 10-9 levels 1–20, `getLevelBudget()`, `getStartingWealth()`, `getPartySizeAdjustment()`, registered in services.yml) and updated `InventoryManagementService::sellItem()` with `FULL_PRICE_SUBTYPES` constant and atomic currency credit on sell. Both files PHP lint clean. Suite entries confirmed (lines 15722/15845). Regression checklist line 322 `[x]` APPROVE. Committed `a5f9e7871`, outbox `662013c2d`. **This completes the full 034603 batch (lines 314–322, 9 items, all APPROVE).**

## Next actions
- Await dispatch of 134531 batch items (checklist lines 323+): dc-cr-feats-ch05, dc-cr-gnome-heritage-sensate, dc-cr-hazards, dc-cr-rest-watch-starvation, dc-cr-skills-society-create-forgery
- File consolidated Gate 2 APPROVE after 134531 batch clears

## Blockers
- None

## ROI estimate
- ROI: 20
- Rationale: Closes the 034603 batch entirely; treasure economy is a core GM loop; clearing this unblocks Gate 2 consolidation once the 134531 batch also clears.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260412-unit-test-20260412-034603-impl-dc-cr-treasure-by-level
- Generated: 2026-04-12T16:18:59+00:00
