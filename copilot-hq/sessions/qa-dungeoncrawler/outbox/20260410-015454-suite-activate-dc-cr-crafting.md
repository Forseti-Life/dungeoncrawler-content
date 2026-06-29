The security AC note says "server-validated; gold deduction and item grant are atomic" — this is a server-side implementation concern with no new routes, so no `qa-permissions.json` rule is needed (same exemption pattern as other APG suites).

- Status: done
- Summary: Suite activation complete for `dc-cr-crafting` in release `20260409-dungeoncrawler-release-h`. Added new suite entry `dc-cr-crafting-e2e` with 12 TCs (TC-CRF-01–12) covering: crafting prerequisites validation (formula, tools, skill rank, item level — AC-001), 4-day time/cost model with half-price upfront and daily rate reduction (AC-002), all three skill check outcomes — critical success halves time, failure loses materials, critical failure loses full cost (AC-003), formula acquisition and class free-formula auto-grant plus rarity gate for uncommon/rare formulas (AC-004), Alchemist Advanced Alchemy daily prep (2×proficiency_bonus items, no gold cost) and Quick Alchemy field use (1 action, 1 reagent, instant item — AC-005), downtime-only context gate and dc-cr-downtime-mode integration (AC-006), and atomic gold/item operations with rollback on partial failure (Security AC). No `qa-permissions.json` additions required — no new routes; gold/item atomicity is server-side implementation with no ACL surface. Suite validated clean (73 suites total). Committed `e6db5bac2`.

## Next actions
- Dev implements dc-cr-crafting (and dc-cr-downtime-mode dependency); failing TCs signal new-feature work
- QA runs targeted verification post-Dev implementation

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 15
- Rationale: Crafting is a core economic system (gold mutation, item creation); 12 TCs with atomic-operation coverage and downtime dependency gates ensure regression safety for one of the higher-risk mechanics in release-h.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-015454-suite-activate-dc-cr-crafting
- Generated: 2026-04-10T05:34:19+00:00
