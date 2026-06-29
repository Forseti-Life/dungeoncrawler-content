# Suite Activation: dc-cr-languages

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T14:55:27+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-languages"`**  
   This links the test to the living requirements doc at `features/dc-cr-languages/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-languages-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-languages",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-languages"`**  
   Example:
   ```json
   {
     "id": "dc-cr-languages-<route-slug>",
     "feature_id": "dc-cr-languages",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-languages",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-languages — Languages System

- Feature: Languages System
- Work item id: dc-cr-languages
- QA owner: qa-dungeoncrawler
- Written: 2026-04-07
- Status: GROOMING (next-release; do NOT add to suite.json until Stage 0 activation)

Policy note:
- This plan is NEXT-RELEASE grooming only. Activate at Stage 0 when feature enters release scope.
- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json` until then.
- Canonical test SoT will be: `qa-suites/products/dungeoncrawler/suite.json` (dungeoncrawler suite)

## Scope

In scope:
- Language catalog entity/taxonomy (CRUD, minimum 10 languages)
- `languages[]` field on character entity
- Ancestry default-language assignment at character creation
- INT-modifier bonus language slot allocation at creation
- `GET /languages` catalog endpoint (anonymous)
- `GET /characters/{id}` — `languages[]` in response
- Character creation API `languages` request body validation
- Deduplication, unknown-ID rejection, cap enforcement

Out of scope:
- Feats that grant additional languages (future)
- Mid-game language acquisition (future)
- Social skill gating on language knowledge (future)

## Roles / Permissions

| Role | Expected behavior |
|---|---|
| Anonymous | `GET /languages` → HTTP 200 with full catalog |
| Authenticated (owner) | `GET /characters/{id}` → includes `languages[]`; creation API accepts `languages` body |
| Authenticated (non-owner) | Character languages readable; not writable |
| Admin | Language taxonomy admin UI accessible (add/edit/delete) |

## Test Matrix

### Suite assignment

| Suite | Runner |
|---|---|
| `role-url-audit` | `scripts/site-audit-run.sh` with `ALLOW_PROD_QA=1` |
| Playwright (E2E) | `npx playwright test` from dungeoncrawler test root |
| API integration | `drush scr` or PHP CLI against dungeoncrawler API |

---

## Test Cases

### TC-LANG-001 — Language catalog: minimum 10 entries exist
- Suite: `role-url-audit` + API integration
- Steps: `GET /languages` as anonymous
- Expected: HTTP 200; response body contains at minimum: Common, Elvish, Dwarvish, Gnomish, Halfling, Orcish, Sylvan, Undercommon, Draconic, Jotun
- Roles covered: anonymous
- AC ref: Happy Path #1

### TC-LANG-002 — Language catalog entry fields complete
- Suite: API integration
- Steps: `GET /languages`; inspect each entry
- Expected: each entry has `name`, `typical_speakers` (description), `script`
- Roles covered: anonymous
- AC ref: Happy Path #1

### TC-LANG-003 — GET /languages is publicly accessible
- Suite: `role-url-audit`
- Steps: HTTP GET `/languages` with no auth
- Expected: HTTP 200 (not 403/redirect)
- Roles covered: anonymous
- AC ref: Permissions #1

### TC-LANG-004 — Character entity has languages[] field
- Suite: API integration
- Steps: `GET /characters/{id}` for a created character
- Expected: response body contains `languages` key with array value
- Roles covered: authenticated owner
- AC ref: Happy Path #2, #5

### TC-LANG-005 — Ancestry auto-assigns default languages at creation
- Suite: API integration / Playwright
- Steps: Create character with Elf ancestry; `GET /characters/{id}`
- Expected: `languages` contains at minimum Common and Elvish
- Roles covered: authenticated owner
- AC ref: Happy Path #3
- Note to PM: requires ancestry default_languages field to be populated in ancestry data for all core ancestries, not just Elf. Confirm scope.

### TC-LANG-006 — INT modifier ≥ 1 grants bonus language slots
- Suite: API integration
- Steps: Create character with INT 14 (mod +2); supply 2 bonus languages in creation request; `GET /characters/{id}`
- Expected: character has ancestry defaults + 2 bonus languages
- Roles covered: authenticated owner
- AC ref: Happy Path #4

### TC-LANG-007 — INT modifier 0 or negative: no bonus slots
- Suite: API integration
- Steps: Create character with INT 10 (mod 0); submit 1 bonus language in request body
- Expected: bonus language rejected or ignored; character has ancestry defaults only
- Roles covered: authenticated owner
- AC ref: Edge Case #1

### TC-LANG-008 — INT modifier ≥ 4: cap enforced at INT mod value
- Suite: API integration
- Steps: Create character with INT 18 (mod +4); submit 5 bonus languages
- Expected: HTTP 400 or only 4 accepted; character holds 4 bonus languages maximum
- Roles covered: authenticated owner
- AC ref: Edge Case #2

### TC-LANG-009 — Duplicate language submission: deduplicated silently
- Suite: API integration
- Steps: Create Elf character (auto-gets Elvish); submit Elvish again in bonus selections
- Expected: `languages[]` contains Elvish once; no error
- Roles covered: authenticated owner
- AC ref: Edge Case #3

### TC-LANG-010 — Unknown language ID: HTTP 400
- Suite: API integration
- Steps: POST character creation with `languages: ["nonexistent_lang_xyz"]`
- Expected: HTTP 400 with message `"unknown language id: nonexistent_lang_xyz"` (or equivalent field-level error)
- Roles covered: authenticated owner
- AC ref: Happy Path #6, Edge Case #4, Failure Modes #2

### TC-LANG-011 — No ancestry assigned: languages defaults to empty array
- Suite: API integration
- Steps: Create character with no ancestry specified; `GET /characters/{id}`
- Expected: `languages: []`; no error
- Roles covered: authenticated owner
- AC ref: Edge Case #5

### TC-LANG-012 — Patching other fields does not clear languages[]
- Suite: API integration
- Steps: Create character with languages; PATCH a non-language field (e.g., name); `GET /characters/{id}`
- Expected: `languages[]` unchanged
- Roles covered: authenticated owner
- AC ref: Data Integrity #1

### TC-LANG-013 — Admin can manage language taxonomy via Drupal UI
- Suite: `role-url-audit` (admin role)
- Steps: GET `/admin/structure/taxonomy/manage/language` (or equivalent path) as admin
- Expected: HTTP 200
- Roles covered: admin
- AC ref: Permissions #3
- Note to PM: exact admin path depends on implementation (taxonomy vs. config entity). Confirm at Stage 0.

### TC-LANG-014 — Authenticated non-owner cannot write character languages
- Suite: Playwright or API integration
- Steps: Attempt to PATCH `languages[]` on another player's character as authenticated non-owner
- Expected: HTTP 403
- Roles covered: authenticated (non-owner)
- AC ref: Permissions #2

---

## AC Items That Cannot Be Expressed as Automation

| AC item | Reason | Note to PM |
|---|---|---|
| "Error messages are clear and actionable" (Failure Modes #1) | Subjective UX criterion | Covered by TC-LANG-010 for the specific known error; general clarity requires manual review |
| "Rollback path: field can be removed without data loss" (Data Integrity #2) | Schema migration test; destructive | Must be verified as a one-time manual DBA test; not automatable in CI without a separate migration test harness |
| Admin UI add/edit/delete language entries (Permissions #3) | Requires admin session + form interaction | TC-LANG-013 covers route access; full CRUD needs Playwright admin session if auth cookies are available |

---

## Pass/Fail Criteria

- APPROVE if: TC-LANG-001 through TC-LANG-014 all PASS (with documented exceptions for manual-only tests above)
- BLOCK if: any of TC-LANG-001–012 fail (catalog missing, field absent, INT cap not enforced, unknown ID not rejected, patch clears languages)

## KB reference

- KB search: none found for language system in `knowledgebase/lessons/`
- Pattern note: INT-modifier language slot allocation follows same slot-gating pattern as ancestry feat slots — verify consistency with ancestry system implementation when wiring character creation.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-languages

- Feature: Languages System
- Work item id: dc-cr-languages
- PM owner: pm-dungeoncrawler
- Written: 2026-04-07

## Gap analysis reference

- `[NEW]` — language taxonomy content type (or config entity) does not exist yet.
- `[NEW]` — `languages[]` field on character entity does not exist yet.
- `[NEW]` — ancestry default-language assignment at character creation does not exist yet.
- `[NEW]` — INT-modifier bonus language slots at character creation do not exist yet.
- `[EXTEND]` — character creation flow needs language selection step.

## Happy Path

- [ ] `[NEW]` A `language` taxonomy (or config entity) exists with at minimum: Common, Elvish, Dwarvish, Gnomish, Halfling, Orcish, Sylvan, Undercommon, Draconic, Jotun. Each entry has: language name, typical speakers (description), and script name.
- [ ] `[NEW]` Character entity has a `languages[]` multi-value field storing language identifiers.
- [ ] `[NEW]` At character creation, the ancestry auto-assigns its default language(s) (e.g., Elvish characters start with Common + Elvish). Default languages are defined in ancestry data.
- [ ] `[NEW]` Characters with INT modifier ≥ 1 receive INT-modifier bonus free language selection slots at creation. Player selects from available languages (excluding already-granted defaults).
- [ ] `[NEW]` `GET /characters/{id}` includes `languages: [...]` in the response body.
- [ ] `[EXTEND]` Character creation API accepts `languages: [...]` in the request body; validates against known language IDs; rejects unknown IDs with HTTP 400.
- [ ] `[NEW]` `GET /languages` returns the full language catalog (anonymous, HTTP 200).

## Edge Cases

- [ ] `[NEW]` INT modifier 0 or negative: no bonus language slots are added; character receives only ancestry defaults.
- [ ] `[NEW]` INT modifier ≥ 4: up to 4 bonus languages can be selected; cap is enforced at the INT modifier value, not a fixed number.
- [ ] `[NEW]` Duplicate language submission (ancestry default + player selection of same language): deduplicated silently; character holds each language once.
- [ ] `[NEW]` Language submitted at creation that is not in the catalog: HTTP 400 with `"unknown language id: <id>"` message.
- [ ] `[NEW]` Character with no ancestry assigned yet: `languages` field defaults to empty array; no error.

## Failure Modes

- [ ] `[TEST-ONLY]` Error messages are clear and actionable.
- [ ] `[NEW]` Invalid language ID rejected with HTTP 400 and explicit field-level message.

## Permissions / Access Control

- [ ] Anonymous user behavior: `GET /languages` catalog is public (HTTP 200).
- [ ] Authenticated user behavior: character language field is readable/writable by character owner.
- [ ] Admin behavior: language taxonomy manageable via Drupal admin UI (add/edit/delete entries).

## Data Integrity

- [ ] No data loss on character update: patching other character fields does not clear `languages[]`.
- [ ] Rollback path: language field can be removed from character entity via schema update without data loss on other fields.

## Knowledgebase check

- KB search: none found (no prior language system lessons in `knowledgebase/lessons/`).
- Note: elf-ancestry and elf-heritage-cavern are blocked on this feature being shipped. Expedite.
