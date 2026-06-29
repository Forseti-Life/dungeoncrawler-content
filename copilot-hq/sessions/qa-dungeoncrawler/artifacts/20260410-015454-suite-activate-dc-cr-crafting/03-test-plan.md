# Test Plan: dc-cr-crafting

## Coverage summary
- AC items: ~18 (prerequisites, time/cost, skill check outcomes, formula system, alchemist daily crafting, downtime dependency)
- Test cases: 12 (TC-CRF-01–12)
- Suites: playwright (downtime, character creation)
- Security: Crafting mutates gold and inventory — server-validated; gold deduction and item grant are atomic

---

## TC-CRF-01 — Crafting prerequisites: formula, tools, skill rank, item level
- Description: Character must have formula, required tools/workshop, minimum Crafting rank, and character level ≥ item level
- Suite: playwright/downtime
- Expected: missing any prerequisite → rejected with specific feedback naming the missing prereq
- AC: AC-001

## TC-CRF-02 — Crafting time: 4-day commitment, half-price at start
- Description: Crafting begins: character pays half price; 4-day minimum; additional days reduce remaining half via daily rate
- Suite: playwright/downtime
- Expected: day 1: gold -= half_price; remaining_cost = half_price; each additional day reduces remaining_cost by daily_rate; item complete when remaining_cost ≤ 0
- AC: AC-002

## TC-CRF-03 — Crafting check: critical success halves time
- Description: Critical success on initial check finishes item in half standard time
- Suite: playwright/downtime
- Expected: crit success → crafting_days_required = ceil(standard_time / 2)
- AC: AC-003

## TC-CRF-04 — Crafting check: failure loses initial materials (4 days, no item)
- Description: Failed check: 4 days spent, item not completed, initial material cost lost
- Suite: playwright/downtime
- Expected: failure → item not created; gold not refunded; time spent = 4 days
- AC: AC-003

## TC-CRF-05 — Crafting check: critical failure ruins materials (full cost lost)
- Description: Critical failure: all materials ruined; full cost lost
- Suite: playwright/downtime
- Expected: crit failure → item not created; gold -= full_price (not half); materials = destroyed
- AC: AC-003

## TC-CRF-06 — Formula system: formulas added to formula book on acquisition
- Description: Acquiring a formula adds it to character's formula book/list; free formulas from class (Alchemist/Inventor) auto-granted on level-up
- Suite: playwright/character-creation
- Expected: formula acquisition adds to formula_list; class free formulas appear at correct levels
- AC: AC-004

## TC-CRF-07 — Formula rarity gate: uncommon/rare requires appropriate source
- Description: Uncommon/Rare formulas require source permission to learn
- Suite: playwright/character-creation
- Expected: uncommon formula without source → blocked; with source (feat, background, GM grant) → allowed
- AC: AC-004

## TC-CRF-08 — Alchemist: Advanced Alchemy creates free items at daily prep
- Description: Alchemist creates alchemical items equal to 2 × proficiency bonus at daily prep; no gold cost; from formula list only
- Suite: playwright/downtime
- Expected: daily_prep → alchemical_items_created = 2 × proficiency_bonus; gold unchanged; only formula list items available
- AC: AC-005

## TC-CRF-09 — Alchemist: Quick Alchemy uses reagent to create instant item
- Description: Quick Alchemy (1 action, field use): spend 1 reagent → create 1 alchemical item immediately
- Suite: playwright/encounter
- Expected: Quick Alchemy action costs 1 action; reagent_count -= 1; item created instantly; available for immediate use that turn
- AC: AC-005

## TC-CRF-10 — Crafting requires downtime context (not adventuring actions)
- Description: Crafting cannot be initiated outside downtime mode
- Suite: playwright/encounter
- Expected: crafting attempt in encounter or exploration mode → rejected with "requires downtime" feedback
- AC: AC-006

## TC-CRF-11 — Downtime integration: time tracking fires correctly for crafting
- Description: When downtime mode active and crafting selected, downtime time tracking integrates correctly
- Suite: playwright/downtime
- Expected: crafting days deducted from downtime_days_available; time integration with dc-cr-downtime-mode
- AC: AC-006

## TC-CRF-12 — Atomic gold/item operations: no duplication on error
- Description: Gold deduction and item grant are atomic; partial failure does not grant item without deducting gold
- Suite: playwright/downtime
- Expected: if item grant fails mid-operation, gold deduction is rolled back; if gold deduction fails, no item granted; no inconsistent state
- AC: Security AC
