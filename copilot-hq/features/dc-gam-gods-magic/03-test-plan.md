# Test Plan: dc-gam-gods-magic

## Coverage summary
- AC items: 17 (12 happy path, 2 edge cases, 2 failure modes + security)
- Test cases: 12 (TC-GAM-01–12)
- Suites: unit (content type schema), playwright (character creation, encounter, access control)
- Security: CSRF on character deity-selection routes; character ownership required

---

## TC-GAM-01 — Deity content type exists with required fields
- Description: `deity` content type has all required stat block fields
- Suite: unit/content-type
- Expected: deity node schema includes name, alignment, edicts, anathema, domains, divine_font, divine_skill, favored_weapon, cleric_spells, divine_ability fields
- AC: Deity Content Type-1, Deity Content Type-2

## TC-GAM-02 — Cleric deity FK applies divine font
- Description: Cleric with selected deity has correct divine font (heal/harm/both) on character record
- Suite: playwright/character-creation
- Expected: character.divine_font = deity.divine_font; font type controls which spell fills divine font slots
- AC: Cleric Integration-2

## TC-GAM-03 — Cleric deity FK grants favored weapon proficiency
- Description: Cleric gains trained proficiency in deity's favored weapon at level 1
- Suite: playwright/character-creation
- Expected: character.weapon_proficiencies[deity.favored_weapon] ≥ trained; does not downgrade existing proficiency
- AC: Cleric Integration-3, Failure Modes-1

## TC-GAM-04 — Champion alignment constraint
- Description: Champion cannot select deity with incompatible alignment
- Suite: playwright/character-creation
- Expected: alignment-incompatible deities are disabled/unavailable in Champion deity selection UI
- AC: Champion Integration-1, Edge Case-2

## TC-GAM-05 — Champion domain restricted to deity's list
- Description: Champion domain feats restricted to deity's permitted domain list
- Suite: playwright/character-creation
- Expected: domain_feat_pool[champion] = intersection of all domains with deity.domains
- AC: Champion Integration-2, Failure Modes-2

## TC-GAM-06 — Channel Smite action available to Cleric
- Description: Channel Smite action appears in Cleric action list; expends a divine font slot
- Suite: playwright/encounter
- Expected: channel_smite available to Cleric; on use, divine_font_slot_count -= 1; strike damage += font_damage
- AC: Channel Smite-1

## TC-GAM-07 — Channel Smite damage type matches font
- Description: Heal font → positive energy damage; harm font → negative energy damage
- Suite: playwright/encounter
- Expected: heal-font Cleric channel smite damage = positive; harm-font Cleric = negative
- AC: Channel Smite-2

## TC-GAM-08 — Holy Symbol deity affiliation FK
- Description: Holy Symbol equipment has deity_affiliation FK
- Suite: unit/content-type
- Expected: holy_symbol.deity_affiliation is a valid deity FK; nullable for non-affiliated symbols
- AC: Holy Symbol-1

## TC-GAM-09 — Deity catalog is public read-only
- Description: Unauthenticated request to deity list route returns data
- Suite: playwright/access-control
- Expected: GET /dungeoncrawler/deities returns 200 with deity list for anonymous user
- AC: Access Control-1

## TC-GAM-10 — Character deity selection requires ownership
- Description: POST deity selection for a character requires _character_access: TRUE
- Suite: playwright/access-control
- Expected: POST /dungeoncrawler/character/{id}/deity without _character_access returns 403
- AC: Access Control-2

## TC-GAM-11 — CSRF required on deity-selection route
- Description: POST without CSRF header token returns 403
- Suite: playwright/security
- Expected: POST /dungeoncrawler/character/{id}/deity without _csrf_request_header_mode token returns 403
- AC: Security — CSRF

## TC-GAM-12 — Divine-font-both: Cleric chooses at creation
- Description: Deity with divine_font = both allows Cleric to choose heal or harm at character creation; choice is fixed
- Suite: playwright/character-creation
- Expected: character.divine_font_choice required when deity.divine_font = both; cannot be changed after creation
- AC: Edge Case-1
