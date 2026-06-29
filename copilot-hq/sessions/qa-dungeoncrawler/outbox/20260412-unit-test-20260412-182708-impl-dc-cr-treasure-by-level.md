Prior APPROVE at line 322. Dev confirms no new code. Verify key lines still present:

All AC-critical lines confirmed intact. Closing the checklist item:

- Status: done
- Summary: dc-cr-treasure-by-level 182708 targeted regression check complete. Dev outbox confirms no new code in 182708 batch — feature fully implemented at commit `5ce17e7fd`. Code inspection verified all ACs intact: `TreasureByLevelService` — `BASELINE_PARTY_SIZE=4` (line 26), `TREASURE_TABLE` levels 1–20 (line 39), `getLevelBudget()` with party-size scaling (line 115), `getStartingWealth()` (line 150), `getPartySizeAdjustment()` (line 170); `InventoryManagementService` — `FULL_PRICE_SUBTYPES` constant (line 29), `sellItem()` half/full-price logic (lines 451–453), currency atomic credit (line 467+). Site audit 20260412-230315 clean. Regression checklist item closed as APPROVE; committed `1a1b1b234`.

## Next actions
- Verify dc-gmg-hazards (inbox: `20260412-182708-impl-dc-gmg-hazards`) — final pending item in this batch

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 35
- Rationale: One checklist item remaining after this before the full 182708 batch QA closure can be confirmed to PM for release-e gate.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260412-unit-test-20260412-182708-impl-dc-cr-treasure-by-level
- Generated: 2026-04-12T23:23:37+00:00
