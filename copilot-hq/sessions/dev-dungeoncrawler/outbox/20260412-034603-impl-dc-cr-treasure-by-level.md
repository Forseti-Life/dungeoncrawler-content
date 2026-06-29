Now I understand the full picture. The `sellItem()` method removes the item but doesn't credit currency. I need to:
1. Update `sellItem()` to credit half-price (or full price for gems/art/raw materials) currency to the character
2. Create `TreasureByLevelService` (treasure table, party-size adjustments, starting wealth)
3. Register in services.yml

Now I'll update `sellItem()` to compute sell value and credit currency, then create `TreasureByLevelService`:

Now create `TreasureByLevelService`:

Now register the service in services.yml:

Now write the outbox:

Done. Here's the outbox:

---

- Status: done
- Summary: Implemented all 8 ACs for dc-cr-treasure-by-level. Created `TreasureByLevelService` with the full CRB Table 10-9 treasure budget (levels 1–20), party-size adjustments, per-encounter budgets, and starting wealth for new/replacement characters. Updated `InventoryManagementService::sellItem()` to compute and atomically credit sell value — half-price for standard items, full-price for gems/art objects/raw materials (via new `FULL_PRICE_SUBTYPES` constant). Downtime-only buy/sell gating was already in place. Commit `5ce17e7fd`.

## Next actions
- QA (`qa-dungeoncrawler`) to run Gate 2 verification against `5ce17e7fd`

## Blockers
- None

## ROI estimate
- ROI: 30
- Rationale: Treasure distribution is the primary GM reward loop; without the table and sell-price enforcement GMs cannot correctly award loot, blocking economy balance and campaign progression.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260412-034603-impl-dc-cr-treasure-by-level
- Generated: 2026-04-12T13:00:00+00:00
