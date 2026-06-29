# Verification Report: dc-cr-heritage-system (2026-04-06 targeted regression)

- Feature: dc-cr-heritage-system
- Inbox item: 20260406-unit-test-20260406-052100-impl-dc-cr-heritage-system
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260406-052100-impl-dc-cr-heritage-system.md
- Verified: 2026-04-06
- Result: **APPROVE**

## Context

Dev outbox confirms: feature was already fully implemented and QA-approved before this dispatch cycle (prior APPROVE at regression checklist line 70, commit 287d1c745). This targeted regression re-confirms all AC items remain active in production.

## Checks Performed

All verification via drush php:eval from /var/www/html/dungeoncrawler.

### 1. HERITAGES constant counts

| Ancestry | Count | AC Expected |
|---|---|---|
| Dwarf | 4 | 4 OK |
| Elf | 4 | 4 OK |
| Gnome | 4 | 4 OK |
| Goblin | 4 | 4 OK |
| Halfling | 4 | 4 OK |
| Human | 1 | 1 OK |
| (8 extended ancestries) | 4 each | beyond AC scope |

Total heritages: 53 (21 for 6 base ancestries confirmed).

### 2. Cross-ancestry validation (server-side)

CharacterManager::isValidHeritageForAncestry() confirmed:
- isValidHeritageForAncestry(Dwarf, ancient-blooded) => true (valid)
- isValidHeritageForAncestry(Dwarf, ancient-elf) => false (cross-ancestry blocked)
- isValidHeritageForAncestry(Human, versatile) => true (Human only heritage id)
- isValidHeritageForAncestry(Human, ancient-blooded) => false (cross-ancestry blocked)

Controller path (CharacterCreationStepController.php line 708):
- resolveAncestryCanonicalName() called first, returns capitalized Dwarf before validation
- Mismatch message: The selected heritage (X) is not valid for the Dwarf ancestry.
- Cross-ancestry API bypass correctly rejected

### 3. Heritage required enforcement

Form path (CharacterCreationStepForm.php):
- heritage #required TRUE: 6 match confirmations
- Error message Heritage selection is required. at line 1344: FOUND
- AJAX wrapper heritage-path-wrapper: FOUND
- updateHeritageOptions callback: FOUND
- clearStaleOptionInput on ancestry change (heritage reset): FOUND

Controller/API path advisory (pre-existing, same as acknowledged in prior APPROVE line 70):
- validateStepRequirements case 2 does NOT enforce heritage as required.
- Empty heritage passes the API path; only the form path enforces required.
- Non-blocking for form-path users; same gap category as background-system API advisory.

### 4. Access control

- Route /characters/create/step/1 (anon, HTTP probe): 301 redirect (correct)
- Permission create dungeoncrawler characters: MISSING from anonymous role (correct)
- authenticated role has permission: YES

### 5. Site audit

Audit 20260406-170141: 0 failures, 0 permission violations, 0 config drift.

## Verdict

**APPROVE** — All AC items for dc-cr-heritage-system confirmed active in production. Heritage dropdown counts match (4/4/4/4/4/1 for 6 base ancestries), cross-ancestry API validation works correctly, form required enforcement with AJAX reset confirmed. Pre-existing advisory (API path no-required enforcement) previously acknowledged; non-blocking.
