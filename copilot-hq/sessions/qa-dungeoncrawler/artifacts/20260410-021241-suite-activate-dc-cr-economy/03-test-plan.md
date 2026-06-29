# Test Plan: dc-cr-economy

## Coverage summary
- AC items: 14 (10 happy path, 2 edge cases, 2 failure modes)
- Test cases: 14 (TC-ECO-01–14)
- Suites: playwright (character creation, inventory, downtime)
- Security: AC exemption granted (no new routes)

---

## TC-ECO-01 — Currency exchange rates
- Description: cp/sp/gp/pp convert at 10cp=1sp, 10sp=1gp, 10gp=1pp
- Suite: playwright/inventory
- Expected: currency.convert(10, cp) = 1 sp; convert(10, sp) = 1 gp; convert(10, gp) = 1 pp
- AC: Currency-1

## TC-ECO-02 — Price "—" items cannot be purchased
- Description: Items with Price = "—" are blocked from purchase
- Suite: playwright/inventory
- Expected: purchase attempt on Price="—" item returns error = not_for_sale
- AC: Currency-2, Failure-1

## TC-ECO-03 — Price 0 items are free
- Description: Items with Price = 0 can be obtained without gold deduction
- Suite: playwright/inventory
- Expected: purchase of Price=0 item: gold unchanged, item added to inventory
- AC: Currency-2

## TC-ECO-04 — Character starting wealth
- Description: Level 1 characters start with 15 gp (= 150 sp)
- Suite: playwright/character-creation
- Expected: new character at level 1: gold = 15 gp (or equivalent in lower denominations)
- AC: Currency-3

## TC-ECO-05 — Coin Bulk calculation (floor division)
- Description: 1,000 coins = 1 Bulk; uses floor division (not ceiling)
- Suite: playwright/inventory
- Expected: 999 coins = 0 Bulk; 1000 coins = 1 Bulk; 1999 coins = 1 Bulk
- AC: Currency-4, Edge-2

## TC-ECO-06 — Hireling rates: unskilled vs. skilled
- Description: Unskilled hireling has +0 all skills; skilled hireling has +4 in specialty, +0 elsewhere
- Suite: playwright/downtime
- Expected: hireling.unskilled.skill_bonus = 0; hireling.skilled.specialty_bonus = 4; hireling.skilled.other_bonus = 0
- AC: Services-1

## TC-ECO-07 — Hireling rates double in danger
- Description: Hireling rates double when adventuring into dangerous areas
- Suite: playwright/downtime
- Expected: hireling.daily_cost when in_danger = true doubles the base rate
- AC: Services-2

## TC-ECO-08 — Spellcasting services: uncommon + material cost
- Description: Spellcasting services are uncommon; cost = table price + material component cost; surcharge for uncommon/long-cast spells
- Suite: playwright/downtime
- Expected: service.spellcasting.availability = uncommon; total_cost includes materials + surcharge where applicable
- AC: Services-3, Failure-2

## TC-ECO-09 — Subsist action fulfills subsistence
- Description: Subsist action in downtime satisfies subsistence standard (no coin cost)
- Suite: playwright/downtime
- Expected: downtime.subsist rolls Survival/Society; success = subsistence_met = true; no gold deducted
- AC: Services-4

## TC-ECO-10 — Animal purchase and rental prices
- Description: Animals available at prices per Table 6-17; rental price distinct from purchase
- Suite: playwright/inventory
- Expected: animal_catalog entries have price and rental_per_day fields matching table values
- AC: Animals-1

## TC-ECO-11 — Non-combat-trained animal panics in combat
- Description: Non-combat-trained animal becomes frightened 4 + fleeing when combat begins
- Suite: playwright/encounter
- Expected: animal_companion.combat_trained = false → on combat_start: condition.frightened = 4, condition.fleeing = true
- AC: Animals-2

## TC-ECO-12 — Combat-trained animal does not panic
- Description: Combat-trained animal functions normally in combat
- Suite: playwright/encounter
- Expected: animal_companion.combat_trained = true → no frightened or fleeing condition on combat_start
- AC: Animals-3

## TC-ECO-13 — Barding: armor tracking with size scaling
- Description: Barding tracked as armor; Strength-based; no runes; Price/Bulk scale by animal size
- Suite: playwright/inventory
- Expected: barding item has armor_type field; rune_slots = 0; price and bulk scale with mount.size
- AC: Animals-4

## TC-ECO-14 — Selling restricted to downtime
- Description: Purchase/sell transactions blocked during encounter or exploration phase
- Suite: playwright/encounter
- Expected: sell_item during encounter or exploration phase returns error = downtime_only
- AC: Edge-1
