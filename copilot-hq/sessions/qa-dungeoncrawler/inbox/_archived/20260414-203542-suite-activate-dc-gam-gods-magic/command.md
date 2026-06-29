# Suite Activation: dc-gam-gods-magic

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T20:35:42+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-gam-gods-magic"`**  
   This links the test to the living requirements doc at `features/dc-gam-gods-magic/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-gam-gods-magic-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-gam-gods-magic",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-gam-gods-magic"`**  
   Example:
   ```json
   {
     "id": "dc-gam-gods-magic-<route-slug>",
     "feature_id": "dc-gam-gods-magic",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-gam-gods-magic",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria: dc-gam-gods-magic

## Gap analysis reference
- DB sections: gam/s01–s06 (Baseline Requirements + Integration Notes, 36 REQs)
- Depends on: dc-cr-spellcasting ✓, dc-cr-class-cleric ✓, dc-cr-class-champion ✓

---

## Happy Path

### Deity Content Type
- [ ] `[NEW]` A `deity` content type exists in `dungeoncrawler_content` with all required fields.
- [ ] `[NEW]` Required fields: `name` (string), `alignment` (enum), `edicts[]` (text array), `anathema[]` (text array), `domains[]` (primary + alternate domain refs), `divine_font` (enum: heal/harm/both), `divine_skill` (skill ref), `favored_weapon` (equipment FK), `cleric_spells{}` (level 1–9 → spell mapping), `divine_ability[]` (enum: STR/DEX/CON/INT/WIS/CHA — two options per deity).

### Character Integration — Cleric
- [ ] `[NEW]` Cleric character sheet holds a deity FK; selected deity's domains, divine font, favored weapon, and cleric spells are automatically applied at character creation/level-up.
- [ ] `[NEW]` Divine font (heal/harm) determines which spell a Cleric can cast with their divine font slots.
- [ ] `[NEW]` Favored weapon grants trained proficiency to the Cleric at level 1 (if not already proficient).

### Character Integration — Champion
- [ ] `[NEW]` Champion character sheet holds a deity FK; selected deity's alignment and edicts/anathema are enforced as in-character constraints.
- [ ] `[NEW]` Champion domain selections at level-up are restricted to the deity's permitted domain list.

### Domain Feats
- [ ] `[NEW]` Domain feats in the feat catalog reference a domain tag; they are available to characters whose deity has that domain (primary or alternate).

### Channel Smite
- [ ] `[NEW]` Channel Smite is a Cleric action that expends a divine font spell slot to add the font's damage to a Strike.
- [ ] `[NEW]` Channel Smite damage type matches the divine font type: heal font → positive energy; harm font → negative energy.

### Holy Symbol
- [ ] `[NEW]` Holy Symbol exists as an equipment item with a `deity_affiliation` FK field.
- [ ] `[NEW]` A Cleric or Champion whose deity matches the symbol's affiliation gains the Implement of the Faith passive.

### Data Import
- [ ] `[NEW]` Deity catalog loaded via `drush` import once schema is defined.
- [ ] `[NEW]` Domain FK validated against allowed domain catalog entries at import time.

### Access Control
- [ ] `[NEW]` Deity catalog is read-only public data — accessible without authentication.
- [ ] `[NEW]` Character deity selection routes require character ownership (`_character_access: TRUE`).

---

## Edge Cases
- [ ] `[NEW]` Deity with `divine_font: both` allows the Cleric to choose either heal or harm at character creation (choice is fixed thereafter).
- [ ] `[NEW]` A Champion must select a deity with alignment compatible with their own alignment (NG/LG/CG for holy Champions, LE/NE/CE for unholy — enforced at character creation).

## Failure Modes
- [ ] `[TEST-ONLY]` Favored weapon proficiency is granted at level 1 only if the character is not already trained or better in that weapon.
- [ ] `[TEST-ONLY]` Domain FK validation rejects domains not on the deity's permitted domain list at feat selection.

## Security acceptance criteria
- Authentication: deity catalog read-only public; character deity selection requires `_character_access: TRUE`
- CSRF: POST/PATCH character deity-selection routes require `_csrf_request_header_mode: TRUE`
- Input validation: deity FK validated against allowed catalog; domain selections validated against deity's permitted domains
- PII/logging: no PII logged; character id + deity id + domain selection only in logs
- Agent: qa-dungeoncrawler
- Status: pending
