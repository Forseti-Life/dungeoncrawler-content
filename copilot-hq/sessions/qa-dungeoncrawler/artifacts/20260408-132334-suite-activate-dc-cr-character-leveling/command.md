# Suite Activation: dc-cr-character-leveling

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T13:23:34+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-character-leveling"`**  
   This links the test to the living requirements doc at `features/dc-cr-character-leveling/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-character-leveling-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-character-leveling",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-character-leveling"`**  
   Example:
   ```json
   {
     "id": "dc-cr-character-leveling-<route-slug>",
     "feature_id": "dc-cr-character-leveling",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-character-leveling",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan — dc-cr-character-leveling

- Feature: Character Leveling and Advancement
- Work item id: dc-cr-character-leveling
- Release target: 20260308-dungeoncrawler-release-b (NEXT RELEASE — grooming only)
- QA owner: qa-dungeoncrawler
- AC source: features/dc-cr-character-leveling/01-acceptance-criteria.md
- Date: 2026-03-09
- Status: groomed (NOT activated — do not add to suite.json until feature enters Stage 0)

## Knowledgebase check
- No prior lesson for character leveling specifically.
- PM decision (in AC): dc-cr-xp-rewards dependency removed — leveling gates on **session milestone**, not XP threshold.
- Ability boost cap rule: 18 cap applies only at character creation; post-creation boosts (levels 5/10/15/20) may push past 18 per PF2e rules.
- Feat prerequisite validation: must be enforced at selection time, not only at display time.
- See `COMBAT_ENGINE_ARCHITECTURE.md` for character entity model.

---

## Suite mapping

| Suite ID | Type | Purpose |
|---|---|---|
| `role-url-audit` | audit | ACL checks for level-up endpoint routes |
| `dc-cr-character-leveling-e2e` | playwright | End-to-end: level-up trigger, class features, ability boosts, skill/feat selection, edge cases, persistence |

> `dc-cr-character-leveling-e2e` is a NEW suite to be added to `qa-suites/products/dungeoncrawler/suite.json` at Stage 0.

---

## Test cases

### Happy Path

#### TC-001: Level-up trigger advances character level
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: Set a character to the session milestone trigger, then call the level-up API. Verify the character's level increments from N to N+1.
- Steps:
  1. Create or fixture a level 1 character.
  2. Set the character to the session milestone state (via test helper or GM API).
  3. POST to the level-up endpoint.
  4. GET character state; assert `character.level === 2`.
- Expected: HTTP 200; `character.level` incremented by 1.
- Roles covered: `dc_playwright_player` (own character), `dc_playwright_admin` (GM)
- AC: "A character at level N can trigger a level-up to level N+1 when they have reached the session milestone trigger"

#### TC-002: Level-up presents advancement table for new level
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: Trigger a level-up. Verify the response includes the class advancement table for the new level (class features, feat slots, ability boosts if applicable).
- Steps:
  1. Fixture a character ready to level to level 2.
  2. POST level-up.
  3. Assert response includes a `pending_choices` or `advancement` section listing available features/feats for the new level.
- Expected: Response body includes structured advancement data; not a bare success with no context.
- Roles covered: `dc_playwright_player`
- AC: "Level-up presents the character's class advancement table for level N+1"
- Note to PM/Dev: Clarify the response shape for pending_choices — is it one POST that returns choices, or a separate GET advancement endpoint?

#### TC-003: Auto-apply class features with no player choice
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: Level up a Fighter to level 3 (which grants Shield Block automatically — no player selection). Verify Shield Block is present in the character's abilities without any explicit player submission.
- Steps:
  1. Fixture a level 2 Fighter at milestone.
  2. POST level-up.
  3. GET character state; assert `character.abilities` or `class_features` includes Shield Block (or equivalent level 3 Fighter feature).
- Expected: Feature present; no pending feat/choice prompt for this feature.
- Roles covered: `dc_playwright_player`
- AC: "Class features granted at the new level are applied automatically"

#### TC-004: Ability boost selection at level 5
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: Level a character to level 5 (ability boost milestone). Verify the system prompts for 4 ability boost selections, allows submitting 4 distinct abilities, and persists the increased stats.
- Steps:
  1. Fixture a character at level 4, milestone reached.
  2. POST level-up; assert response includes ability boost prompt with 4 slots.
  3. Submit 4 distinct ability boost choices (e.g., Str, Dex, Con, Int).
  4. GET character state; assert the 4 chosen ability scores each increased by 2 (PF2e boost = +2).
- Expected: Stats updated; no duplicate boost within a single milestone allowed.
- Roles covered: `dc_playwright_player`
- AC: "player selects which abilities to boost (four boosts at each milestone); each ability may be boosted at most once per milestone"
- Note to PM/Dev: Confirm the numeric boost delta (PF2e: +2 if below 18, +1 if already 18+).

#### TC-005: Skill increase — raise one proficiency rank
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: At a level where a skill increase is granted (per class table), verify the player can select one skill to advance by one proficiency rank, and that the change persists.
- Steps:
  1. Fixture a character at the appropriate level (class-dependent; confirm with Dev).
  2. POST level-up; assert response includes a skill increase prompt.
  3. Submit skill choice (e.g., Arcana: Trained → Expert).
  4. GET character state; assert chosen skill rank incremented by one step.
- Expected: One skill rank incremented; no other skills changed.
- Roles covered: `dc_playwright_player`
- AC: "Skill increases at appropriate levels let the player raise one skill proficiency rank by one step"
- Note to PM/Dev: Confirm which level + class combination reliably has a skill increase for test fixture purposes.

#### TC-006: Feat selection — choose from eligible catalog
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: At a level with a feat slot (e.g., level 2 class feat), verify the feat catalog displayed is filtered by prerequisites, and that selecting a feat persists it.
- Steps:
  1. Fixture a character at level 1, milestone reached.
  2. POST level-up to level 2; assert response includes a class feat slot.
  3. GET available feats; confirm catalog is filtered (prerequisites met).
  4. Submit a valid feat selection.
  5. GET character state; assert feat is in `character.feats` or `class_feats`.
- Expected: Feat persisted; catalog only shows eligible feats.
- Roles covered: `dc_playwright_player`
- AC: "Feat slots display eligible feat catalog filtered by prerequisites; player selects and choice is persisted"

#### TC-007: Level-up idempotency — cannot re-trigger until next milestone
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: After a successful level-up (N→N+1), immediately attempt the same level-up transition again before a new milestone is reached. Verify rejection.
- Steps:
  1. Level up character from 1→2.
  2. Without setting a new milestone, POST level-up again.
  3. Assert HTTP 4xx response with a clear rejection message.
  4. GET character state; assert level is still 2 (not 3).
- Expected: 4xx; character level unchanged.
- Roles covered: `dc_playwright_player`
- AC: "Level-up cannot be re-triggered until the next milestone; the endpoint is idempotent for the same level transition"

#### TC-008: Completed level-up persists across save/reload
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: Complete a full level-up (including choices if applicable). Reload/restart the session. Verify all advancement data (level, features, boosts, skill ranks, feats) is intact.
- Steps:
  1. Level up character from 1→2 with all choices resolved.
  2. End session / reload (navigate away and back, or request fresh character state).
  3. GET character state.
  4. Assert level = 2; class features, feat selections, and any other choices match pre-reload state.
- Expected: All advancement data persists; no regression to level 1 or missing choices.
- Roles covered: `dc_playwright_player`
- AC: "Completed level-up persists across save/reload without data loss"

---

### Edge Cases

#### TC-009: Reject level-up past level 20
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: Attempt to level up a character already at level 20.
- Steps:
  1. Fixture a level 20 character at milestone.
  2. POST level-up.
  3. Assert HTTP 4xx; response body contains "Already at maximum level" (or equivalent).
- Expected: 4xx; clear error message; character remains at level 20.
- Roles covered: `dc_playwright_player`, `dc_playwright_admin`
- AC: "Attempting to level up past level 20 is rejected with a clear message"

#### TC-010: Reject level-up without milestone reached
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: Attempt to level up a character that has NOT reached the session milestone.
- Steps:
  1. Fixture a level 1 character with milestone NOT set.
  2. POST level-up.
  3. Assert HTTP 4xx; response body contains a clear rejection message about milestone requirement.
- Expected: 4xx; not a silent no-op or 200 with no effect.
- Roles covered: `dc_playwright_player`
- AC: "Attempting to level up without reaching the session milestone is rejected"

#### TC-011: Level skipping is not permitted
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: Attempt to jump a character from level 1 directly to level 3 (bypassing level 2).
- Steps:
  1. Fixture a level 1 character.
  2. POST a level-up request specifying target level 3 (if supported) or trigger level-up twice in rapid sequence before the first is complete.
  3. Assert the character advances by at most one level per valid trigger, not two.
- Expected: Character at level 2 only; second trigger rejected if milestone not re-set.
- Roles covered: `dc_playwright_player`
- AC: "Skipping levels is not permitted; each level must be applied in sequence"

#### TC-012: Auto-apply no-choice class features silently
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: For a level-up that grants only auto-apply features (no player selection needed), verify the response does NOT show unnecessary choice prompts and the features are applied immediately.
- Steps:
  1. Identify a level where only auto-apply features are granted (e.g., "gain +10 HP").
  2. POST level-up; assert response has no pending `feat_choice` or `ability_boost_choice` prompts.
  3. GET character state; assert HP increased by expected amount.
- Expected: No spurious choice prompts; HP (or other auto feature) updated immediately.
- Roles covered: `dc_playwright_player`
- AC: "A class feature with no player-choice component is auto-applied without prompting"
- Note to PM/Dev: Provide a specific level+class combination where only auto-apply features exist for test fixture.

#### TC-013: Ability boost at or near stat cap
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: At a level 5+ ability boost milestone, submit a boost for an ability already at 18. Verify the boost applies correctly per PF2e rules (still +2 if stat ≥ 18 at a level boost, unlike creation).
- Steps:
  1. Fixture a level 4 character with Strength = 18.
  2. Advance to level 5 (ability boost milestone).
  3. Select Strength as one of the four boosts.
  4. Assert Strength becomes 20 (18 + 2).
- Expected: Boost applied; no false rejection based on creation-only cap.
- Roles covered: `dc_playwright_player`
- AC: "An ability boost that would exceed the stat cap is correctly handled" — PF2e: post-creation boosts may exceed 18

---

### Failure Modes

#### TC-014: Non-existent character ID returns structured error
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: POST level-up for a character ID that does not exist.
- Steps:
  1. POST level-up with `character_id = 99999999` (or a UUID known to be absent).
  2. Assert HTTP 404; response is structured JSON, not a PHP exception or HTML error page.
- Expected: 404; structured error body.
- Roles covered: `dc_playwright_admin`
- AC: "Invalid character ID or non-existent character returns a structured error"

#### TC-015: Concurrent level-up requests serialized or rejected
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: Fire two simultaneous level-up POSTs for the same character. Verify the character ends up at exactly N+1 (not N+2) and no partial/corrupt state results.
- Steps:
  1. Fixture a level 1 character at milestone.
  2. Fire two concurrent POST requests to the level-up endpoint for the same character.
  3. Wait for both to complete; GET character state.
  4. Assert `character.level === 2` (not 3); no error 500.
- Expected: Exactly one level increment; second request either 4xx or 200 with no-op (idempotent).
- Roles covered: `dc_playwright_admin`
- AC: "Concurrent level-up requests are serialized or rejected (no partial double-level-up)"
- Note: This may require a dedicated concurrency test helper; flag to Dev if not feasible in playwright alone.

#### TC-016: Missing class advancement data returns actionable error
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: Attempt a level-up for a level where the class advancement data is absent or incomplete (simulate by using a character class with a gap in its advancement table if one can be created via test fixture, or by Dev providing a test hook).
- Steps:
  1. Create a test fixture with a class whose advancement table is missing level N data.
  2. POST level-up to level N.
  3. Assert HTTP 4xx or 5xx with an actionable error message (not a PHP exception / unhandled 500).
- Expected: Structured error; no unhandled exception.
- Roles covered: `dc_playwright_admin`
- AC: "Missing class advancement data for a given level returns an actionable error, not a PHP exception"
- Note to Dev: A test-only "incomplete" class fixture may be required. Confirm feasibility.

---

### Permissions / Access Control

#### TC-017: Only the controlling player (or GM) may trigger level-up
- Suite: `role-url-audit` + `dc-cr-character-leveling-e2e` (playwright ACL assertion)
- Description: Verify that a player can only level up their own character, not another player's. Verify GM/admin can level up any character.
- Steps (role-url-audit):
  - `dc_playwright_player`: 200 for own character level-up endpoint (allow)
  - `dc_playwright_player`: 403 for another player's character (deny)
  - `dc_playwright_admin`: 200 for any character (allow)
  - `anon`: 403 (deny)
- qa-permissions.json rule: add `dungeoncrawler-character-levelup` rule at Stage 0
- AC: "Only the character's controlling player (or GM) may trigger a level-up"

#### TC-018: Anonymous users cannot trigger or view level-up state
- Suite: `role-url-audit`
- Description: Attempt GET and POST on the level-up endpoint as anonymous.
- Expected: HTTP 403 for both GET (level-up state) and POST (trigger).
- Roles covered: `anon`
- AC: "Anonymous users cannot trigger or view level-up state (session-scoped)"

#### TC-019: Admin can force-apply a level or reset level-up state
- Suite: `dc-cr-character-leveling-e2e` (playwright)
- Description: As `dc_playwright_admin` (GM), force-apply a level to a character that has not reached the milestone. Verify the override works. Also verify reset: after a level-up, admin resets level-up state so the character is back at the pre-level state.
- Steps:
  1. Fixture a level 1 character WITHOUT milestone set.
  2. POST force-level-up as admin.
  3. Assert HTTP 200; character level = 2.
  4. POST admin-reset level-up state.
  5. Assert character level = 1 (or equivalent reset state).
- Expected: Admin override accepted; reset works.
- Roles covered: `dc_playwright_admin`
- AC: "Admin may force-apply a level or reset level-up state for GM tooling purposes"
- Note to PM/Dev: Confirm the force-apply and reset endpoints/routes. If not yet scoped, flag.

---

## AC items that cannot be expressed as automation

| AC item | Reason | Note to PM/Dev |
|---|---|---|
| Session milestone trigger mechanism | The AC doesn't specify the exact API or test surface for setting/clearing a milestone. If no test helper exists, tests cannot reliably gate on "milestone reached." | Dev to provide a test helper or GM API endpoint to set milestone state on a character (required for TC-001, TC-007, TC-010). |
| Concurrent request serialization (TC-015) | Playwright does not natively run concurrent HTTP requests to test race conditions. Requires a custom test harness or server-side integration test. | Recommend a PHPUnit-level concurrency test (or pessimistic locking verification) rather than playwright. Flag at Stage 0 activation. |
| Missing advancement data simulation (TC-016) | Requires a test fixture with an intentionally incomplete class table. May need a Dev-provided test-only class definition. | Dev to confirm if a "stub class" fixture is feasible for this test, or if it must be tested at the unit/integration level. |
| Force-apply / reset routes (TC-019) | AC mentions admin force-apply and reset but does not specify the routes/endpoints. | PM/Dev to confirm whether force-apply and reset are a single compound endpoint or separate routes, and what the URL shape is. |
| Feat prerequisite catalog (TC-006) | Without a documented feat catalog API, the test can only verify that some filtering occurs, not that all prerequisites are correctly applied. | Recommend a `GET /dungeoncrawler/feats?character_id=X` endpoint that returns eligible feats for automated prerequisite validation. |

---

## Stage 0 activation checklist (for when feature is selected into release)

- [ ] Add `dc-cr-character-leveling-e2e` suite entry to `qa-suites/products/dungeoncrawler/suite.json`
- [ ] Add `dungeoncrawler-character-levelup` rule to `org-chart/sites/dungeoncrawler/qa-permissions.json`
- [ ] Confirm: milestone test helper or GM API for setting milestone state (Dev deliverable — required before TC-001 can run)
- [ ] Confirm: force-apply and reset endpoint routes (TC-019)
- [ ] Confirm: level+class combinations for TC-003 (auto-apply), TC-005 (skill increase), TC-012 (no-choice level)
- [ ] Confirm: concurrent test strategy (playwright vs. PHPUnit for TC-015)
- [ ] Confirm: feat catalog query endpoint for TC-006 prerequisite validation
- [ ] Implement playwright test file for TC-001 through TC-019
- [ ] Validate suite: `python3 scripts/qa-suite-validate.py`

### Acceptance criteria (reference)

# Acceptance Criteria — dc-cr-character-leveling

- Feature: Character Leveling and Advancement
- Release target: 20260308-dungeoncrawler-release-b
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-03-08

## Scope

Implement the level-up flow that advances a character from their current level to the next (levels 1–20), applying new class features, ability boosts (at levels 5/10/15/20), additional skill increases, and new feats.

## Prerequisites satisfied

- dc-cr-character-creation: complete
- dc-cr-character-class: complete (advancement tables exist)
- dc-cr-background-system: complete (skill proficiencies established at creation)

## Knowledgebase check

None found for character leveling specifically. See `COMBAT_ENGINE_ARCHITECTURE.md` for character entity model. Note: dc-cr-xp-rewards dependency is removed — leveling gates on session milestone (not XP threshold) per prior PM decision.

## Happy Path

- [ ] `[NEW]` A character at level N can trigger a level-up to level N+1 when they have reached the session milestone trigger.
- [ ] `[NEW]` Level-up presents the character's class advancement table for level N+1 — showing which class features, feats, and ability boosts are available at that level.
- [ ] `[NEW]` Class features granted at the new level are applied automatically (no player choice required unless the feature requires a selection, e.g., a feat slot).
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, and 20: player selects which abilities to boost (four boosts at each milestone); each ability may be boosted at most once per milestone; choices are persisted.
- [ ] `[NEW]` Skill increases at appropriate levels (per class advancement table) let the player raise one skill proficiency rank by one step.
- [ ] `[NEW]` Feat slots at the new level (class feat, skill feat, general feat, ancestry feat as applicable) display the eligible feat catalog filtered by prerequisites; player selects and choice is persisted.
- [ ] `[NEW]` Level-up cannot be re-triggered until the next milestone; the endpoint is idempotent for the same level transition.
- [ ] `[NEW]` Completed level-up persists across save/reload without data loss (character level, features, boosts, skill ranks all survive session restart).

## Edge Cases

- [ ] `[NEW]` Attempting to level up past level 20 is rejected with a clear message ("Already at maximum level").
- [ ] `[NEW]` Attempting to level up without reaching the session milestone is rejected with a clear message.
- [ ] `[NEW]` Skipping levels (e.g., level 1 → 3) is not permitted; each level must be applied in sequence.
- [ ] `[NEW]` A class feature that has no player-choice component (e.g., "gain +10 HP") is auto-applied without prompting.
- [ ] `[NEW]` An ability boost that would exceed the stat cap (18 at character creation; per rule, boosts are capped at 18 unless the ability is already 18+) is correctly handled.

## Failure Modes

- [ ] `[NEW]` Invalid character ID or non-existent character returns a structured error.
- [ ] `[NEW]` Concurrent level-up requests for the same character are serialized or rejected (no partial double-level-up).
- [ ] `[NEW]` Missing class advancement data for a given level returns an actionable error, not a PHP exception.

## Permissions / Access Control

- [ ] Only the character's controlling player (or GM) may trigger a level-up.
- [ ] Anonymous users cannot trigger or view level-up state (session-scoped).
- [ ] Admin may force-apply a level or reset level-up state for GM tooling purposes.

### Route permission expectations (required for qa-permissions.json)

All player-facing leveling routes use `_character_access: TRUE` (own-character access check). Admin routes use `administer dungeoncrawler content`.

| Route | HTTP method | Access gate | anon | authenticated (own char) | content_editor | administrator | dc_playwright_player | dc_playwright_admin |
|---|---|---|---|---|---|---|---|---|
| `/api/character/{id}/level-up` | `[POST]` | `_character_access: TRUE` + `_csrf_request_header_mode: TRUE` | deny | allow (own) | allow (own) | allow | allow (own) | allow |
| `/api/character/{id}/level-up/status` | `[GET]` | `_character_access: TRUE` | deny | allow (own) | allow (own) | allow | allow (own) | allow |
| `/api/character/{id}/level-up/ability-boosts` | `[POST]` | `_character_access: TRUE` + `_csrf_request_header_mode: TRUE` | deny | allow (own) | allow (own) | allow | allow (own) | allow |
| `/api/character/{id}/level-up/skill-increase` | `[POST]` | `_character_access: TRUE` + `_csrf_request_header_mode: TRUE` | deny | allow (own) | allow (own) | allow | allow (own) | allow |
| `/api/character/{id}/level-up/feat` | `[POST]` | `_character_access: TRUE` + `_csrf_request_header_mode: TRUE` | deny | allow (own) | allow (own) | allow | allow (own) | allow |
| `/api/character/{id}/level-up/feats` | `[GET]` | `_character_access: TRUE` | deny | allow (own) | allow (own) | allow | allow (own) | allow |
| `/api/character/{id}/level-up/admin-force` | `[POST]` | `administer dungeoncrawler content` + `_csrf_request_header_mode: TRUE` | deny | deny | deny | allow | deny | allow |
| `/api/character/{id}/level-up/admin-reset` | `[POST]` | `administer dungeoncrawler content` + `_csrf_request_header_mode: TRUE` | deny | deny | deny | allow | deny | allow |

Notes:
- `_character_access: TRUE` restricts to character owner; no role-based permission name to verify in `permissions.yml`.
- `_csrf_request_header_mode: TRUE` is correct for all `[POST]` routes (header-based CSRF, not query-param token).
- `content_editor` has `authenticated` base role; `_character_access` further restricts to own character only.
- Admin routes use `administer dungeoncrawler content` — verify with `grep -r "administer dungeoncrawler content" web/modules/custom/dungeoncrawler_content/`.

## Gameplay-rule alignment

- PF2e Chapter 2: character advancement tables are the authoritative source.
- Ability boost caps: no ability may exceed 18 via boosts at character creation; subsequent boosts at levels 5/10/15/20 may push past 18 per PF2e rules (the cap only applies at creation).
- Feat prerequisites: must be validated at selection time (not just at display time).

## Test path guidance (for QA)

| Requirement | Test path |
|---|---|
| Level-up trigger | Set character to milestone; call level-up API; verify level incremented |
| Class feature auto-apply | Level up fighter to level 3 (Shield Block); verify ability in character state |
| Ability boost selection | Trigger level 5; submit boost choices; verify stats updated |
| Skill increase | Trigger applicable level; submit skill choice; verify proficiency rank incremented |
| Feat selection | Trigger level with feat slot; submit feat; verify feat in character abilities |
| Already max level | Attempt level-up on level 20 char; verify rejection |
| Save/reload persistence | Level up; restart session; verify all changes survived |
- Agent: qa-dungeoncrawler
