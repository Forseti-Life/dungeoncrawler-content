# Test Plan: dc-cr-character-creation

**Owner:** qa-dungeoncrawler  
**Date:** 2026-03-27  
**Feature:** Character Creation Workflow — 6-step multi-step creation, draft/active state machine, derived stat computation, access control  
**AC source:** `features/dc-cr-character-creation/01-acceptance-criteria.md`  
**Implementation notes:** `features/dc-cr-character-creation/02-implementation-notes.md`  

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit immediately after module changes; surface regressions quickly.
- KB: none found for character creation workflow specifically; this is the first end-to-end multi-step player journey in the pipeline.

## Dependencies (required before dev starts)
- `dc-cr-ancestry-system` — must be merged + seeded (6 ancestries)
- `dc-cr-background-system` — must be merged + seeded (5+ backgrounds)
- `dc-cr-character-class` — must be merged + seeded (12 classes)

> **Note:** PM will set this feature to `status: ready` only after the three prerequisite features are merged. Test cases here are groomed in advance; they activate at Stage 0 when this feature enters release scope.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` | PHPUnit functional (`./vendor/bin/phpunit`) | State machine (draft→active), derived stat computation, step sequencing, edge cases, data integrity, rollback |
| `role-url-audit` | `scripts/site-audit-run.sh` | Access control: creation endpoint anon redirect, player 403 on others' drafts, admin access |
| `playwright-suite` | Playwright (`npx playwright test`) | End-to-end 6-step creation flow, back-navigation with conflict clearing, concurrent session conflict |

> **Note:** The 6-step creation workflow is the first Playwright-required feature in the dungeoncrawler pipeline. The `playwright-suite` entries require a running local dev instance with seeded prerequisite content before they can execute.

---

## Test cases

### TC-CWF-01 — Initiating character creation produces a draft character entity
- **AC:** `[NEW]` A player can initiate character creation, producing a character entity in `draft` state
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterCreationWorkflowTest::testInitiateCreationProducesDraft()`
- **Setup:** Authenticated player; call creation-initiation API/endpoint
- **Expected:** Character entity created with `state=draft`; entity ID returned; character NOT on public character list
- **Roles:** authenticated player

### TC-CWF-02 — Workflow step order is enforced
- **AC:** `[NEW]` Steps must execute in order: ancestry → background → class → ability scores → skills/feats → review/save
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterCreationWorkflowTest::testStepOrderEnforced()`
- **Setup:** Draft character; attempt to submit step 3 (class) before step 2 (background) is complete
- **Expected:** Error returned; step 3 cannot advance until step 2 is saved
- **Roles:** authenticated player

### TC-CWF-03 — Completing each step saves choice and advances to next step
- **AC:** `[NEW]` Completing each step saves the player's choice to the draft character entity and advances to the next step
- **Suite:** `playwright-suite`
- **Test file:** `tests/e2e/character-creation.spec.ts::testStepProgressionSavesAndAdvances`
- **Setup:** Full local env with seeded ancestries, backgrounds, classes; authenticated player session
- **Expected:** Each step saves to draft entity and UI advances to the next step; progress persisted between page loads
- **Roles:** authenticated player

### TC-CWF-04 — Back-navigation updates choice and flags/clears downstream conflicts
- **AC:** `[NEW]` A player can navigate back to a prior step and change their selection; downstream choices that conflict with the change are flagged or cleared
- **Suite:** `playwright-suite`
- **Test file:** `tests/e2e/character-creation.spec.ts::testBackNavigationClearsConflicts`
- **Setup:** Draft character with steps 1–3 complete; navigate back to step 1 and change ancestry
- **Expected:** Any ability scores or skill choices that conflict with the new ancestry are either flagged with a warning or cleared; character entity updated
- **Roles:** authenticated player

### TC-CWF-05 — Derived stats computed and stored correctly after step 6
- **AC:** `[NEW]` Total HP = ancestry HP + class HP/level × 1; AC = 10 + Dex modifier + armor proficiency; saves = trained (level+2+ability mod); Perception = trained (level+2+Wis mod)
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterDerivedStatsTest::testDerivedStatsComputedAfterFinalize()`
- **Setup:** Complete all 6 steps with known inputs (e.g., human ancestry, fighter class, Str 18/Dex 14); call finalize
- **Expected:** `total_hp`, `ac`, `fortitude_save`, `reflex_save`, `will_save`, `perception` all match expected computed values for the chosen combination
- **Roles:** authenticated player

### TC-CWF-06 — Character transitions from draft to active after step 6
- **AC:** `[NEW]` After step 6, character transitions from `draft` to `active` state and is visible on player's character list
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterCreationWorkflowTest::testFinalizeTransitionsDraftToActive()`
- **Setup:** Draft character with all 6 steps complete; call finalize endpoint
- **Expected:** Character state = `active`; character appears on player's character list; was NOT visible before finalize
- **Roles:** authenticated player

### TC-CWF-07 — Abandoned draft persists and is resumable
- **AC:** `[NEW]` A player who abandons the workflow mid-creation has their draft character persisted and can resume from the last completed step
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterCreationWorkflowTest::testAbandonedDraftIsResumable()`
- **Setup:** Complete steps 1–3; close session; re-open creation workflow
- **Expected:** Draft character still exists; creation workflow resumes from step 4; prior selections intact
- **Roles:** authenticated player

### TC-CWF-08 — Concurrent session conflict returns clear message
- **AC:** `[NEW]` Two browser sessions cannot simultaneously edit the same draft character; the second session receives a clear conflict message
- **Suite:** `playwright-suite`
- **Test file:** `tests/e2e/character-creation.spec.ts::testConcurrentSessionConflict`
- **Setup:** Open draft character in session A; open same draft in session B; submit a change in session A; attempt to submit a change in session B
- **Expected:** Session B receives a conflict message (e.g., "This character is being edited in another session"); no data corruption
- **Roles:** authenticated player

### TC-CWF-09 — Ability score assignment respects ancestry/background boost/flaw rules
- **AC:** `[NEW]` Ability score assignment must respect PF2E boost/flaw rules; invalid combinations are rejected at save time
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterAbilityScoreTest::testBoostFlawValidationRejectsInvalidCombination()`
- **Setup:** Draft character with dwarf ancestry (Con boost, Cha flaw); attempt to assign Cha as a free boost at step 4
- **Expected:** Validation error "This ability score cannot be boosted due to an ancestry flaw"; save rejected
- **Roles:** authenticated player

### TC-CWF-10 — Empty prerequisite content returns clear error, not empty dropdown
- **AC:** `[NEW]` If a required prerequisite has no seeded content, the creation workflow returns a clear error rather than an empty dropdown
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterCreationWorkflowTest::testEmptyPrerequisiteContentReturnsError()`
- **Setup:** Temporarily uninstall ancestry seeder; initiate creation and attempt to reach ancestry step
- **Expected:** Workflow returns error "No ancestry options are available; contact your GM" rather than an empty dropdown
- **Roles:** authenticated player

### TC-CWF-11 — Incomplete prior step prevents finalization at step 6
- **AC:** `[NEW]` A player who reaches step 6 with an incomplete prior step cannot finalize; error identifies the missing step
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterCreationWorkflowTest::testIncompleteStepBlocksFinalize()`
- **Setup:** Draft character with step 3 (class) skipped; call finalize endpoint
- **Expected:** Error "Character creation incomplete: class selection is required (step 3)"; character remains `draft`
- **Roles:** authenticated player

### TC-CWF-12 — Derived stat computation with missing inputs does not crash
- **AC:** `[NEW]` Derived stat computation with missing inputs (e.g., no class selected) does not crash; returns partial result or "incomplete" flag
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterDerivedStatsTest::testPartialInputsReturnIncompleteFlag()`
- **Setup:** Draft character with ancestry but no class; call derived-stat computation method directly
- **Expected:** No exception thrown; result contains `incomplete=true` flag and partial data for known inputs; no null-pointer crash
- **Roles:** authenticated player

### TC-CWF-13 — Anonymous users cannot initiate character creation (redirected to login)
- **AC:** Anonymous users cannot initiate or view character creation; creation endpoint redirects to login
- **Suite:** `role-url-audit`
- **Entry:** `POST /api/character/create` — HTTP 302 (redirect to login) or 403 for anonymous
- **Expected:** 302/403 for anonymous; no character entity created
- **Roles:** anonymous

### TC-CWF-14 — Player cannot view another player's draft character (403)
- **AC:** Players can only create and edit their own characters; accessing another player's draft returns 403
- **Suite:** `role-url-audit`
- **Entry:** `GET /api/character/{draft-id}` (owned by player A, accessed by player B) — HTTP 403
- **Expected:** 403 for other authenticated players
- **Roles:** authenticated player (other's draft)

### TC-CWF-15 — Admin can view and edit any character draft
- **AC:** Admins can view and edit any character draft for GM/admin tooling purposes
- **Suite:** `role-url-audit`
- **Entry:** `GET /api/character/{draft-id}` — HTTP 200 for admin
- **Expected:** 200 for admin; 403 for other players; redirect/403 for anonymous
- **Roles:** admin (200), other player (403), anonymous (403)

### TC-CWF-16 — Draft characters are NOT visible on public character list
- **AC:** Draft characters are not visible on public character lists; only `active` characters are shown
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterCreationWorkflowTest::testDraftNotVisibleOnPublicList()`
- **Setup:** Create draft character; retrieve public character list
- **Expected:** Draft character absent from public list; active characters present
- **Roles:** anonymous, authenticated player

### TC-CWF-17 — At most 1 active draft creation session per character slot
- **AC:** A player may have at most 1 active draft creation session at a time per character slot (to prevent orphaned drafts)
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterCreationWorkflowTest::testDraftLimitPerCharacterSlot()`
- **Setup:** Player has existing draft character for slot 1; attempt to initiate a second creation for slot 1
- **Expected:** Error "You already have an active draft character for this slot"; no second draft created
- **Roles:** authenticated player

### TC-CWF-18 — Rollback: existing active characters unaffected when module is uninstalled
- **AC:** If character creation is disabled (module uninstall), existing `active` characters are unaffected; `draft` characters may be cleaned up via a drush command
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterCreationRollbackTest::testUninstallPreservesActiveCharacters()`
- **Setup:** Active character + draft character; uninstall creation module
- **Expected:** Active character entity intact; draft character can be removed via `drush character:cleanup-drafts` (or equivalent); no data corruption
- **Roles:** n/a (rollback check)

### TC-CWF-19 — Prerequisite seeding verification (drush check)
- **AC:** All prerequisite content types must be seeded before creation workflow is usable
- **Suite:** `drush-verify`
- **Command:**
  ```bash
  drush php-eval "
    print 'Ancestries: ' . \Drupal::entityQuery('node')->condition('type','ancestry')->count()->execute() . PHP_EOL;
    print 'Backgrounds: ' . \Drupal::entityQuery('node')->condition('type','background')->count()->execute() . PHP_EOL;
    print 'Classes: ' . \Drupal::entityQuery('node')->condition('type','character_class')->count()->execute() . PHP_EOL;
  "
  ```
- **Expected:** Ancestries ≥ 6, Backgrounds ≥ 5, Classes = 12
- **Roles:** n/a (seeding check)

### TC-CWF-20 — QA audit still passes after module deployment
- **AC:** QA automated audit must still pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler` (local dev)
- **Expected:** 0 violations, 0 failures
- **Roles:** all 6 roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| Step 2 — Manually create character via UI and verify derived stats displayed correctly | Derived stat display (UI rendering) requires visual/Playwright verification against a running instance with seeded content; the computation math is covered by TC-CWF-05 (module-test-suite). A separate Playwright test spec will be needed for visual stat display once the frontend is built. |
| Concurrent session conflict UX (TC-CWF-08) | Depends on the frontend implementation of session locking; TC-CWF-08 covers the API-level conflict signal but the exact UX message ("Edit in another session") must be validated in Playwright once the UI is built. |
| Ability score assignment UI (step 4) | The full boost/flaw selection UI interaction requires Playwright E2E coverage beyond what TC-CWF-09 validates at the service layer. |

---

## Regression risk areas

1. `dc-cr-ancestry-system` / `dc-cr-background-system` / `dc-cr-character-class` overlap: all three feed into the creation workflow HP/stats computation; if any of the three changes its data schema post-merge, derived stat computation in TC-CWF-05 may silently compute wrong values.
2. Draft state visibility: ensure the `state=draft` filter on public character lists is not inadvertently bypassed by any new query or view added alongside this module.
3. Ability score boost/flaw logic (TC-CWF-09): depends on ancestry and background applying their boosts/flaws correctly; regressions in those modules will cascade into creation workflow validation.
4. Concurrent session handling (TC-CWF-08): any change to the character entity locking mechanism could silently disable the conflict guard.
5. QA audit regression: any new routes added by this module must not introduce unexpected 404/403 results in the `role-url-audit` suite.
