# Acceptance Criteria: dc-cr-economy

## Gap analysis reference
- DB sections: core/ch06 — Currency and Economy (6), Services and Economy (5), Animals and Mounts (5)
  Note: These sections were already covered in dc-cr-equipment-ch06 and flipped to in_progress.
  This feature focuses on the economy/services/animals subsystem as a distinct service layer.
- Depends on: dc-cr-equipment-system ✓, dc-cr-character-creation

---

## Happy Path

### Currency
- [ ] `[EXTEND]` Currency system supports cp/sp/gp/pp with standard exchange rates (10cp=1sp, 10sp=1gp, 10gp=1pp).
- [ ] `[EXTEND]` Items with Price "—" cannot be purchased; Price 0 items are free.
- [ ] `[EXTEND]` Character starting wealth = 15 gp (150 sp) at level 1.
- [ ] `[EXTEND]` Bulk of coins: 1,000 = 1 Bulk (rounded down) tracked in inventory.

### Services
- [ ] `[EXTEND]` Hireling rate table implemented: unskilled = +0 all skills; skilled = +4 specialty, +0 otherwise.
- [ ] `[EXTEND]` Hireling rates double when adventuring into danger.
- [ ] `[EXTEND]` Spellcasting services: uncommon; cost = table price + material component cost; surcharges for uncommon/long-cast spells.
- [ ] `[EXTEND]` Subsist action can fulfill subsistence standard (replaces coin cost).

### Animals and Mounts
- [ ] `[EXTEND]` Animals have purchase and rental prices per Table 6-17.
- [ ] `[EXTEND]` Non-combat-trained animals panic in combat (frightened 4 + fleeing).
- [ ] `[EXTEND]` Combat-trained animals do not panic.
- [ ] `[EXTEND]` Barding tracked as armor: Strength modifier-based; no runes; Price/Bulk scale by size.

---

## Edge Cases
- [ ] `[EXTEND]` Selling during encounter or exploration: flagged (downtime-only purchase/sell).
- [ ] `[EXTEND]` Coin Bulk calculation: floor(coins / 1000), not ceiling.

## Failure Modes
- [ ] `[TEST-ONLY]` Item with Price "—" presented for purchase: blocked.
- [ ] `[TEST-ONLY]` Services at uncommon spell level: surcharge applied (not blocked).

## Security acceptance criteria
- Security AC exemption: game-mechanic economy and services logic; no new routes or user-facing input beyond existing character creation and downtime handlers
