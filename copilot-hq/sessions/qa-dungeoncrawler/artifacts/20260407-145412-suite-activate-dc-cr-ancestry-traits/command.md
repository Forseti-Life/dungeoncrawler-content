# Suite Activation: dc-cr-ancestry-traits

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T14:54:12+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-ancestry-traits"`**  
   This links the test to the living requirements doc at `features/dc-cr-ancestry-traits/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-ancestry-traits-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-ancestry-traits",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-ancestry-traits"`**  
   Example:
   ```json
   {
     "id": "dc-cr-ancestry-traits-<route-slug>",
     "feature_id": "dc-cr-ancestry-traits",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-ancestry-traits",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan — dc-cr-ancestry-traits

- Feature: Ancestry Traits System
- Work item id: dc-cr-ancestry-traits
- Release target: 20260308-dungeoncrawler-release-b (NEXT RELEASE — grooming only)
- QA owner: qa-dungeoncrawler
- AC source: features/dc-cr-ancestry-traits/01-acceptance-criteria.md
- Date: 2026-03-09
- Status: groomed (NOT activated — do not add to suite.json until feature enters Stage 0)

## Knowledgebase check
- No prior lesson for ancestry traits system specifically.
- Prior PM decision (commit `576262c5`): trait matching is case-sensitive with canonical Title Case strings matching `CharacterManager::ANCESTRIES` pattern. This constraint governs TC-007 and TC-008 below.
- Ancestry system (dc-cr-ancestry-system) is complete; implementation notes in `features/dc-cr-ancestry-system/02-implementation-notes.md` confirm ancestry nodes seeded at install with fields including name.

---

## Suite mapping

| Suite ID | Type | Purpose |
|---|---|---|
| `role-url-audit` | audit | Route-based ACL checks for traits query endpoint |
| `dc-cr-ancestry-traits-e2e` | playwright | End-to-end: character creation assigns traits; hasTraits logic; edge cases |

> `dc-cr-ancestry-traits-e2e` is a NEW suite to be added to `qa-suites/products/dungeoncrawler/suite.json` at Stage 0.

---

## Test cases

### Happy Path

#### TC-001: Ancestry catalog defines creature traits
- Suite: `dc-cr-ancestry-traits-e2e` (playwright)
- Description: Verify each canonical ancestry in the catalog has a defined set of creature traits. Spot-check: Dwarf → `["Dwarf", "Humanoid"]`; Human → `["Human", "Humanoid"]`; Elf → `["Elf", "Humanoid"]`.
- Steps: Query the ancestry API or inspect the ancestry data structure; confirm each ancestry has a non-empty `traits` array with at least the species trait + "Humanoid" where applicable.
- Expected: All core ancestries (Dwarf, Elf, Gnome, Goblin, Halfling, Human) have a `traits[]` array with at least one entry. Dwarf → exactly `["Dwarf", "Humanoid"]`.
- Roles covered: administrator (read)
- AC: Happy Path — ancestry catalog defines traits

#### TC-002: Character creation auto-assigns ancestry traits
- Suite: `dc-cr-ancestry-traits-e2e` (playwright)
- Description: Create a Dwarf character via the character creation flow. After creation completes, read back the character entity and confirm `traits` contains `"Dwarf"` and `"Humanoid"` without any additional player action.
- Steps:
  1. POST to character creation step selecting Dwarf ancestry.
  2. Complete character creation to final step.
  3. GET character state (e.g., `/dungeoncrawler/character/{id}/state` or equivalent).
  4. Assert `character.traits` includes `"Dwarf"` and `"Humanoid"`.
- Expected: `traits = ["Dwarf", "Humanoid"]` (or superset) present in character response.
- Roles covered: `dc_playwright_player` (authenticated player creating their own character)
- AC: "When a character selects an ancestry at creation, their ancestry traits are automatically applied"

#### TC-003: Trait persistence across save/reload
- Suite: `dc-cr-ancestry-traits-e2e` (playwright)
- Description: After TC-002, reload the session/character and verify traits are unchanged.
- Steps:
  1. Create Dwarf character (as TC-002).
  2. End session / reload (navigate away and back, or request a fresh character state).
  3. GET character state again.
  4. Assert `traits` still contains `"Dwarf"` and `"Humanoid"`.
- Expected: Traits persist; array not cleared on reload.
- Roles covered: `dc_playwright_player`
- AC: "`traits[]` array persists across save/reload"

#### TC-004: hasTraits returns true for matching trait
- Suite: `dc-cr-ancestry-traits-e2e` (playwright)
- Description: Call `hasTraits(dwarf_character_id, ["Humanoid"])` and verify the response is `true`.
- Steps:
  1. Use a Dwarf character (from TC-002 or fixture).
  2. Call the `hasTraits` function/endpoint with `trait_list = ["Humanoid"]`.
  3. Assert response is `true`.
- Expected: `true`
- Roles covered: `dc_playwright_player`, `dc_playwright_admin`
- AC: "`hasTraits(character_id, trait_list)` returns true if character has all traits"

#### TC-005: hasTraits returns false for non-matching trait
- Suite: `dc-cr-ancestry-traits-e2e` (playwright)
- Description: Call `hasTraits(dwarf_character_id, ["Elf"])` and verify the response is `false`.
- Steps:
  1. Use a Dwarf character.
  2. Call `hasTraits` with `trait_list = ["Elf"]`.
  3. Assert response is `false`.
- Expected: `false`
- Roles covered: `dc_playwright_player`
- AC: "`hasTraits` returns false otherwise"

#### TC-006: Traits are queryable via API
- Suite: `role-url-audit` (add entry for trait query endpoint) + `dc-cr-ancestry-traits-e2e` (playwright for content verification)
- Description: A read-only endpoint or field in the character state response exposes the current traits array.
- Steps:
  1. GET character state for a character with known traits.
  2. Assert the response body includes a `traits` field (or equivalent) with the expected values.
- Expected: HTTP 200; `traits` field present and populated.
- Roles covered: `dc_playwright_player` (own character), `dc_playwright_admin`
- ACL notes:
  - `dc_playwright_player`: `allow` (session participant reads own character state)
  - `dc_playwright_admin`: `allow`
  - `anon`: `deny` (403)
- AC: "API supports querying a character's current traits"

---

### Edge Cases

#### TC-007: Mixed-heritage ancestry receives union of parent traits
- Suite: `dc-cr-ancestry-traits-e2e` (playwright)
- Description: Create a Half-Elf character. Verify traits include the union of both Human and Elf ancestry traits (e.g., `["Human", "Elf", "Humanoid"]` or canonical equivalent, without duplicates).
- Steps:
  1. POST character creation selecting Half-Elf ancestry (if available in catalog; skip and flag to PM if not).
  2. GET character state.
  3. Assert `traits` is the union of Human and Elf traits with no duplicates.
- Expected: `traits` contains `"Human"`, `"Elf"`, `"Humanoid"` (exactly once each).
- Roles covered: `dc_playwright_player`
- AC: "mixed-heritage ancestry receives the union of both parent ancestry traits"
- Note to Dev: If Half-Elf is not in the initial ancestry catalog, test with the first mixed-heritage ancestry that is implemented.

#### TC-008: Duplicate trait assignment is idempotent
- Suite: `dc-cr-ancestry-traits-e2e` (playwright)
- Description: Assign the trait `"Humanoid"` to a character that already has it (simulate double-assignment). Verify traits array contains exactly one `"Humanoid"` entry.
- Steps:
  1. Obtain a character with `"Humanoid"` trait.
  2. Trigger a second assignment of `"Humanoid"` (via test hook, internal API, or re-running creation step if supported).
  3. GET character state.
  4. Assert `character.traits.filter(t => t === "Humanoid").length === 1`.
- Expected: One `"Humanoid"` entry, not two.
- Roles covered: `dc_playwright_admin` (admin-level test helper)
- AC: "Adding a duplicate trait is idempotent"

#### TC-009: Unknown trait string is rejected with error
- Suite: `dc-cr-ancestry-traits-e2e` (playwright)
- Description: POST an unknown/non-catalog trait string to the character. Verify it is rejected with an error message containing "Unknown trait: X".
- Steps:
  1. POST `{ "trait": "NotARealTrait" }` to the trait assignment endpoint (or equivalent internal route).
  2. Assert HTTP 4xx response.
  3. Assert response body contains an error message referencing the unknown trait.
- Expected: HTTP 400 (or 422); response body: `{ "error": "Unknown trait: NotARealTrait" }` (or equivalent structured error).
- Roles covered: `dc_playwright_admin`
- AC: "An unknown/non-catalog trait string in a request is rejected with a clear error"

#### TC-010: Trait comparison is case-sensitive
- Suite: `dc-cr-ancestry-traits-e2e` (playwright)
- Description: Call `hasTraits(dwarf_character_id, ["humanoid"])` (lowercase) and verify the response is `false` (canonical is `"Humanoid"`).
- Steps:
  1. Use a Dwarf character with `"Humanoid"` trait.
  2. Call `hasTraits` with `["humanoid"]` (all lowercase).
  3. Assert response is `false`.
- Expected: `false` — case mismatch is not a match.
- Roles covered: `dc_playwright_player`
- AC: "Trait comparison is case-sensitive; 'humanoid' does not match 'Humanoid'" — PM decision commit `576262c5`

---

### Failure Modes

#### TC-011: Querying traits for a non-existent character returns structured error
- Suite: `dc-cr-ancestry-traits-e2e` (playwright)
- Description: GET character traits for a character ID that does not exist.
- Steps:
  1. GET `/dungeoncrawler/character/99999999/state` (or traits endpoint) with a non-existent ID.
  2. Assert HTTP 404.
  3. Assert response body is a structured JSON error (not an unhandled 500).
- Expected: HTTP 404; structured error body (not 500).
- Roles covered: `dc_playwright_player`, `dc_playwright_admin`
- AC: "Querying traits for a non-existent character returns a structured error"

#### TC-012: Invalid trait is rejected at storage — not silently ignored
- Suite: `dc-cr-ancestry-traits-e2e` (playwright)
- Description: Attempt to assign a well-formed but non-catalog string (e.g., `"Dragon"` if not in catalog) via any writable path. Confirm rejection, not silent success.
- Steps:
  1. POST an invalid trait via any internal writable interface.
  2. Immediately GET character state.
  3. Assert invalid trait is NOT present in `traits[]`.
  4. Assert the write returned a non-200 response or an explicit error payload.
- Expected: Trait not persisted; error returned at write time.
- Roles covered: `dc_playwright_admin`
- AC: "Assigning an invalid trait is rejected at storage, not silently ignored"

---

### Permissions / Access Control

#### TC-013: Trait data readable by session participants
- Suite: `role-url-audit` (add rule entry) + `dc-cr-ancestry-traits-e2e` (playwright assertion)
- Description: Verify that authenticated session participants (player role) can read the traits field of a character in their session.
- Expected HTTP:
  - `dc_playwright_player`: 200 (allow)
  - `dc_playwright_admin`: 200 (allow)
  - `anon`: 403 (deny)
- qa-permissions.json rule: add a `dungeoncrawler-character-traits-read` rule for the character state endpoint at Stage 0
- AC: "Trait data is readable by session participants"

#### TC-014: Clients cannot directly mutate the traits array
- Suite: `dc-cr-ancestry-traits-e2e` (playwright)
- Description: Attempt a direct client-side PATCH/POST to the traits array via the public API (bypassing the character creation flow). Confirm the request is rejected.
- Steps:
  1. Attempt `PATCH /dungeoncrawler/character/{id}` with body `{ "traits": ["Dragon"] }` as `dc_playwright_player`.
  2. Assert HTTP 403 or 405 (not allowed).
  3. GET character state to confirm traits unchanged.
- Expected: 403 or 405; traits not modified.
- Roles covered: `dc_playwright_player` (player should NOT be able to mutate)
- AC: "Trait assignment is server-side only; clients cannot directly mutate the traits array"

---

## AC items that cannot be expressed as automation

| AC item | Reason | Note to PM |
|---|---|---|
| "Trait strings validated against defined constant catalog before storage" | Verification requires white-box access to the constant catalog or a documented API that lists all valid traits. If a `GET /dungeoncrawler/traits` (catalog endpoint) is not implemented, TC-009/TC-012 can only be tested against known-invalid strings. Recommend adding a catalog endpoint. | PM decision needed: is a `GET /dungeoncrawler/traits` catalog endpoint in scope for release-b? If not, TC-009/TC-012 must use hard-coded known-invalid strings. |
| hasTraits function/endpoint contract | The AC does not specify the route or call signature for `hasTraits`. If it is an internal Drupal service (not exposed via HTTP), playwright cannot probe it. Dev must expose it as an API method or test helper. | PM/Dev decision: what is the public test surface for `hasTraits`? |
| Mixed-heritage ancestry catalog availability | TC-007 depends on Half-Elf (or equivalent) being in the initial ancestry catalog. If not implemented, test is skip-able. | Dev to confirm which mixed-heritage ancestry entries are in initial scope. |

---

## Stage 0 activation checklist (for when feature is selected into release)

- [ ] Add `dc-cr-ancestry-traits-e2e` suite entry to `qa-suites/products/dungeoncrawler/suite.json`
- [ ] Add `dungeoncrawler-character-traits-read` rule to `org-chart/sites/dungeoncrawler/qa-permissions.json`
- [ ] Implement/activate playwright test file for TC-001 through TC-014
- [ ] Confirm route for character state / traits query endpoint with Dev (needed for TC-006, TC-011, TC-013)
- [ ] Confirm test surface for `hasTraits` (HTTP endpoint vs. internal — needed for TC-004/TC-005/TC-010)
- [ ] Confirm mixed-heritage ancestry availability (needed for TC-007)
- [ ] Validate suite: `python3 scripts/qa-suite-validate.py`

### Acceptance criteria (reference)

# Acceptance Criteria — dc-cr-ancestry-traits

- Feature: Ancestry Traits System
- Release target: 20260308-dungeoncrawler-release-b
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-03-08

## Scope

Implement ancestry-granted creature traits (descriptors/tags) that attach to a character at character creation and govern how spells, effects, and abilities interact with them. This system is a prerequisite for correct spell and ability targeting logic across the whole game.

## Prerequisites satisfied

- dc-cr-ancestry-system: complete (ancestry selection grants the foundation for trait assignment)

## Knowledgebase check

None found for trait system specifically. Prior PM decision (commit `576262c5`) on ancestry traits case-sensitivity: trait matching is case-sensitive with canonical strings (e.g., `CharacterManager::ANCESTRIES`). Same contract applies to trait constants.

## Happy Path

- [ ] `[NEW]` Each ancestry in the catalog defines a set of creature traits (e.g., Dwarf ancestry → `["Dwarf", "Humanoid"]`; Human ancestry → `["Human", "Humanoid"]`).
- [ ] `[NEW]` When a character selects an ancestry at creation, their ancestry traits are automatically applied to the character entity (no additional player action required).
- [ ] `[NEW]` A character entity has a `traits[]` array that persists across save/reload.
- [ ] `[VERIFY]` Trait strings are canonical (exact-case match) and validated against a defined constant catalog before storage.
- [ ] `[NEW]` A check function `hasTraits(character_id, trait_list)` returns true if the character has all specified traits, false otherwise.
- [ ] `[NEW]` The API supports querying a character's current traits (read-only endpoint or field in character state response).

## Edge Cases

- [ ] `[NEW]` A character with a mixed-heritage ancestry (e.g., Half-Elf) receives the union of both parent ancestry traits.
- [ ] `[NEW]` Adding a duplicate trait (same canonical string already present) is idempotent — no duplication in the traits array.
- [ ] `[NEW]` An unknown/non-catalog trait string in a request is rejected with a clear error ("Unknown trait: X").
- [ ] `[VERIFY]` Trait comparison is case-sensitive; "humanoid" does not match "Humanoid".

## Failure Modes

- [ ] `[NEW]` Querying traits for a non-existent character returns a structured error.
- [ ] `[NEW]` Assigning an invalid trait (not in canonical catalog) is rejected at storage, not silently ignored.

## Permissions / Access Control

- [ ] Trait data is readable by session participants (needed for targeting logic display).
- [ ] Trait assignment is server-side only (character creation flow and heritage system); clients cannot directly mutate the traits array.

### Route permission expectations (required for qa-permissions.json)

| Route | HTTP method | Permission | anon | authenticated | content_editor | administrator | dc_playwright_player | dc_playwright_admin |
|---|---|---|---|---|---|---|---|---|
| `/dungeoncrawler/traits` | `[GET]` | `access dungeoncrawler characters` | deny | allow | allow | allow | allow | allow |
| `/api/character/{id}/traits` | `[GET]` | `_character_access: TRUE` (own-character access check) | deny | allow (own) | allow (own) | allow | allow | allow |
| `/api/character/{id}/traits/check` | `[GET]` | `_character_access: TRUE` (own-character access check) | deny | allow (own) | allow (own) | allow | allow | allow |

Notes:
- `content_editor` inherits the `authenticated` role; since `access dungeoncrawler characters` is granted to `authenticated`, `content_editor` is `allow` on all read-only traits endpoints.
- `_character_access: TRUE` means the access check further restricts by character ownership; non-admin roles can only access their own character's traits.
- No CSRF token required — all traits routes are `[GET]` only.

## Gameplay-rule alignment

- PF2e trait system (Core Rulebook, Chapter 2): traits are descriptors that affect targeting and certain ability interactions. Traits have no inherent passive mechanical benefit; they are referenced by spells/abilities.
- Trait case-sensitivity contract: canonical strings match `CharacterManager::ANCESTRIES` pattern (Title Case, enforced).

## Test path guidance (for QA)

| Requirement | Test path |
|---|---|
| Trait assignment at creation | Create dwarf character; verify traits include "Dwarf" and "Humanoid" |
| Trait persistence | Create character; reload session; verify traits unchanged |
| hasTraits check | Call hasTraits(dwarf_char, ["Humanoid"]); verify true. Call hasTraits(dwarf_char, ["Elf"]); verify false |
| Unknown trait rejection | POST unknown trait to character; verify error response |
| Case sensitivity | Query hasTraits(char, ["humanoid"]); verify false (case mismatch) |
| Duplicate idempotency | Assign "Humanoid" twice; verify traits array has one "Humanoid" entry |
