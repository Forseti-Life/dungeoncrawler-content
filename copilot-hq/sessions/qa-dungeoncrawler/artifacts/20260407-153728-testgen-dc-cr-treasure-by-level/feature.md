# Feature Brief: Treasure by Level Table

- Work item id: dc-cr-treasure-by-level
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Treasure
- Depends on: dc-cr-economy, dc-cr-equipment-ch06

## Description
Implement PF2e per-level treasure tables in ContentGenerator (REQs 2340–2342, 2345).
Currently generateTreasureHoard() uses generic dice-rolled currency amounts with no
reference to PF2e Table 10-9 (level-appropriate currency + permanent item levels).

Covers:
- Per-level treasure table levels 1–20: correct gp total, permanent item levels,
  consumable item levels (REQ 2340)
- Currency breakdown: coins, gems, art objects, lower-level items at half price (REQ 2341)
- Party-size treasure adjustment (REQ 2342)
- New/replacement character starting wealth by level (REQ 2345)

Also covers selling rules (REQ 2343 — standard at half price, gems/art at full)
and downtime-only buy/sell restriction (REQ 2344), in coordination with
dc-cr-economy and dc-cr-equipment-ch06.

## Security acceptance criteria

- Security AC exemption: game-mechanic logic; no new routes or user-facing input beyond existing character creation and encounter phase forms

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2340, 2341, 2342, 2343, 2344, 2345
- See `runbooks/roadmap-audit.md` for audit process.
