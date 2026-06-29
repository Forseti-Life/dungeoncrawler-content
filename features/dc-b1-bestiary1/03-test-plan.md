# Test Plan: dc-b1-bestiary1

## Coverage summary
- AC items: 13 (8 happy path, 2 edge cases, 2 failure modes + security)
- Test cases: 10 (TC-BST-01–10)
- Suites: unit (content type schema), playwright (encounter filtering, access control)
- Security: CSRF on mutation routes; GM-only access required

---

## TC-BST-01 — Creature content type exists with required fields
- Description: `creature` content type has all required stat block fields
- Suite: unit/content-type
- Expected: creature node schema includes level, rarity, traits, perception, languages, skills, senses, ac, saves, hp, immunities, weaknesses, resistances, speeds, attacks, abilities fields
- AC: Creature Content Type-1, Creature Content Type-2

## TC-BST-02 — Attack array encodes required fields
- Description: Each attack record has name, traits, damage_dice, damage_type, reach
- Suite: unit/content-type
- Expected: sample creature attack = {name: "Claw", traits: [agile], damage_dice: "1d6", damage_type: "slashing", reach: 5}
- AC: Creature Content Type-3

## TC-BST-03 — Ability array encodes required fields
- Description: Each ability record has name, action_cost, trigger, frequency, traits, save_dc, effect
- Suite: unit/content-type
- Expected: sample ability validates against schema with all required fields; trigger and frequency nullable
- AC: Creature Content Type-4

## TC-BST-04 — Encounter filter by level range
- Description: Encounter tooling filters creatures by level range (inclusive)
- Suite: playwright/encounter
- Expected: filter(level_min=1, level_max=3) returns creatures at levels 1, 2, and 3; level 0 and 4 excluded
- AC: Encounter Filtering-1, Failure Modes-1

## TC-BST-05 — Encounter filter by trait
- Description: Encounter tooling filters creatures by trait tag
- Suite: playwright/encounter
- Expected: filter(trait="undead") returns only creatures with undead trait
- AC: Encounter Filtering-1

## TC-BST-06 — Encounter filter by tactical role
- Description: Encounter tooling filters creatures by tactical role enum
- Suite: playwright/encounter
- Expected: filter(role="brute") returns only creatures with tactical_role = brute
- AC: Encounter Filtering-2

## TC-BST-07 — Player receives 403 on mutation route
- Description: Player-role user cannot access creature mutation routes
- Suite: playwright/access-control
- Expected: POST /admin/dungeoncrawler/creature returns 403 when authenticated as player role
- AC: GM vs. Player Access-2, Failure Modes-2

## TC-BST-08 — GM can access mutation route with _campaign_gm_access
- Description: GM-role user can access creature mutation routes
- Suite: playwright/access-control
- Expected: POST /admin/dungeoncrawler/creature returns 200/201 when authenticated as GM with _campaign_gm_access: TRUE
- AC: GM vs. Player Access-2

## TC-BST-09 — CSRF required on mutation routes
- Description: POST/PATCH to creature routes without CSRF header returns 403
- Suite: playwright/security
- Expected: POST /admin/dungeoncrawler/creature without _csrf_request_header_mode token returns 403
- AC: Security — CSRF

## TC-BST-10 — Multi-attack and multi-ability arrays support arbitrary length
- Description: A creature can have multiple attacks and abilities without schema error
- Suite: unit/content-type
- Expected: saving a creature with 5 attacks and 7 abilities succeeds; all records preserved
- AC: Edge Case-1
