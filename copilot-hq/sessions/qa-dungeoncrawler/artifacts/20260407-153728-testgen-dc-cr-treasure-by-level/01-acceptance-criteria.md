# Acceptance Criteria: dc-cr-treasure-by-level

## Gap analysis reference
- DB sections: core/ch10/Treasure (6 reqs)
- Depends on: dc-cr-economy, dc-cr-xp-award-system

---

## Happy Path
- [ ] `[NEW]` Treasure per level (4-PC baseline) table implemented (currency + permanent items + consumables per level).
- [ ] `[NEW]` Currency column represents coins, gems, art objects, and lower-level items sold at half price.
- [ ] `[NEW]` For each PC beyond 4: additional currency per level added per Party Size adjustment rules.
- [ ] `[NEW]` Selling items: standard = half Price; gems, art objects, raw materials = full Price.
- [ ] `[NEW]` Characters can buy/sell items typically only during downtime.
- [ ] `[NEW]` New/replacement character starting wealth by level table implemented.

## Edge Cases
- [ ] `[NEW]` Selling during encounter or exploration (not downtime): flagged or blocked per GM.
- [ ] `[NEW]` Party size < 4: proportional currency reduction (not explicitly blocking).

## Failure Modes
- [ ] `[TEST-ONLY]` Selling a standard item at full price: blocked or flagged (only half price allowed).
- [ ] `[TEST-ONLY]` Starting wealth for mid-campaign character (level 5): uses level 5 row, not level 1.

## Security acceptance criteria
- Security AC exemption: game-mechanic economy logic; no new routes or user-facing input beyond existing character creation and downtime handlers
