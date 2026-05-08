# Dungeon Crawler Content - Test Case Matrix

## Purpose

This matrix turns the current testing review into a prioritized implementation backlog for `dungeoncrawler_content`.

- Focus: high-risk behavior, access control, and JSON/API contract stability.
- Scope: functional and unit coverage for existing module capabilities.
- Goal: move from route existence checks to behavior assertions with real fixtures.

## Current Coverage Summary

Strong baseline coverage already exists for:

- Public/admin/character/campaign route accessibility.
- Character creation wizard flow and validation.
- Campaign state access/validation and entity lifecycle APIs.
- Core page/controller smoke checks.

High-value gaps identified during review:

- No direct functional tests for `GameObjectsController`, `GeneratedImageApiController`, `DungeonStateController`, `RoomStateController`, `CampaignStateController`, and architecture/testing admin pages.
- Incomplete assertions for newer campaign lifecycle flows (archive/unarchive status restoration, list segmentation behavior).
- Limited API contract assertions for some JSON endpoints (shape + error semantics).
- Limited negative tests around ownership and invalid payload handling for newer endpoints.

## Priority Legend

- **P0**: Blocker / core gameplay or security risk
- **P1**: High value / likely regression points
- **P2**: Important hardening / lower blast radius

## Test Case Backlog

| ID | Priority | Area | Type | Existing Class Target | Test Case | Key Assertions |
| --- | --- | --- | --- | --- | --- | --- |
| DCCT-001 | P0 | Campaign archive lifecycle | Functional | `tests/src/Functional/Routes/CampaignRoutesTest.php` | Archive owned campaign via form submit | 200 on form, confirmation message, campaign hidden from active section |
| DCCT-002 | P0 | Campaign archive lifecycle | Functional | `tests/src/Functional/Routes/CampaignRoutesTest.php` | Unarchive restores pre-archive status | pre-archive status persisted, unarchive restores it, campaign visible in active section |
| DCCT-003 | P0 | Campaign ownership guard | Functional | `tests/src/Functional/Routes/CampaignRoutesTest.php` | Non-owner archive/unarchive denied | 403 for non-owner, no campaign status mutation in DB |
| DCCT-004 | P0 | Campaign state API | Functional | `tests/src/Functional/CampaignStateAccessTest.php` | Owner fetches campaign state | 200 JSON, `success=true`, `campaignId` matches, `state` schema keys present |
| DCCT-005 | P0 | Campaign state API | Functional | `tests/src/Functional/CampaignStateAccessTest.php` | Non-owner fetch denied | 403 JSON, `success=false`, stable error message |
| DCCT-006 | P0 | Character access control | Functional | `tests/src/Functional/Routes/CharacterRoutesTest.php` | Non-owner cannot view/edit/delete | 403 on all 3 routes with no mutation side effects |
| DCCT-007 | P0 | Character creation step-save endpoint | Functional | `tests/src/Functional/CharacterCreation/CharacterCreationWorkflowTest.php` | POST `/characters/create/step/{step}/save` happy path | 200 JSON, session progression persists to next step |
| DCCT-008 | P0 | Character creation step-save endpoint | Functional | `tests/src/Functional/CharacterCreation/CharacterCreationWorkflowTest.php` | Invalid step payload rejected | 4xx JSON, validation message, no invalid state persisted |
| DCCT-009 | P1 | Game objects manager access | Functional | New `tests/src/Functional/Controller/GameObjectsControllerTest.php` | Admin can open `/dungeoncrawler/objects` | 200, expected headings/controls present |
| DCCT-010 | P1 | Game objects manager access | Functional | New `tests/src/Functional/Controller/GameObjectsControllerTest.php` | Non-admin denied `/dungeoncrawler/objects` | 403 and no table data rendered |
| DCCT-011 | P1 | Game objects filtering | Functional | New `tests/src/Functional/Controller/GameObjectsControllerTest.php` | Filter by schema/table/object type | filtered rows only, count updates, no PHP warnings |
| DCCT-012 | P1 | Game objects row edit | Functional | New `tests/src/Functional/Controller/GameObjectsControllerTest.php` | Edit row in form mode | successful save message, changed value visible on return |
| DCCT-013 | P1 | Game objects JSON edit | Functional | New `tests/src/Functional/Controller/GameObjectsControllerTest.php` | Invalid JSON payload rejected | validation error shown, row unchanged |
| DCCT-014 | P1 | Generated image API | Functional | New `tests/src/Functional/Controller/GeneratedImageApiControllerTest.php` | GET `/api/image/{image_uuid}` found case | 200 JSON, `success=true`, canonical image keys present |
| DCCT-015 | P1 | Generated image API | Functional | New `tests/src/Functional/Controller/GeneratedImageApiControllerTest.php` | GET image not found | 404 JSON, `success=false`, deterministic error key/message |
| DCCT-016 | P1 | Generated image API | Functional | New `tests/src/Functional/Controller/GeneratedImageApiControllerTest.php` | GET object images with filters | respects `campaign_id`, `slot`, `variant` filters |
| DCCT-017 | P1 | Generated image API | Functional | New `tests/src/Functional/Controller/GeneratedImageApiControllerTest.php` | Campaign image listing requires campaign access | owner allowed, non-owner denied 403 |
| DCCT-018 | P1 | Dungeon state API | Functional | New `tests/src/Functional/Controller/DungeonStateControllerTest.php` | GET dungeon state existing record | 200 JSON, expected state shape |
| DCCT-019 | P1 | Dungeon state API | Functional | New `tests/src/Functional/Controller/DungeonStateControllerTest.php` | POST dungeon state update | persisted state round-trip via subsequent GET |
| DCCT-020 | P1 | Dungeon state API | Functional | New `tests/src/Functional/Controller/DungeonStateControllerTest.php` | Invalid dungeon ID format | 404 route constraint hit |
| DCCT-021 | P1 | Room state API | Functional | New `tests/src/Functional/Controller/RoomStateControllerTest.php` | GET room state existing record | 200 JSON with room state keys |
| DCCT-022 | P1 | Room state API | Functional | New `tests/src/Functional/Controller/RoomStateControllerTest.php` | Access denied without permission | 403 JSON for anonymous/insufficient permission |
| DCCT-023 | P1 | Combat encounter API surface | Functional | `tests/src/Functional/Routes/ApiRoutesTest.php` | POST `/api/combat/action|get|set` route+contract checks | method allowed, non-404, JSON envelope keys stable |
| DCCT-024 | P1 | Encounter AI preview endpoint | Functional | New `tests/src/Functional/Controller/EncounterAiPreviewControllerTest.php` | Admin-only preview endpoint | admin 200 JSON, non-admin 403 |
| DCCT-025 | P1 | Admin architecture pages | Functional | New `tests/src/Functional/Controller/ControllerArchitectureControllerTest.php` | `/architecture/controllers` access/content | access content permission works; expected heading visible |
| DCCT-026 | P1 | Admin architecture pages | Functional | New `tests/src/Functional/Controller/EncounterAiIntegrationControllerTest.php` | `/architecture/encounter-ai-integration` access/content | access content permission works; expected heading visible |
| DCCT-027 | P1 | Testing dashboard route | Functional | New `tests/src/Functional/Controller/TestingDashboardControllerTest.php` | `/admin/dungeoncrawler/testing` admin-only | admin 200, non-admin 403 |
| DCCT-028 | P1 | Image generation UI routes | Functional | `tests/src/Functional/Routes/AdminRoutesTest.php` | `/admin/content/dungeoncrawler/image-generation` + legacy alias | both routes 200 for permitted user and show same form markers |
| DCCT-029 | P2 | Character state APIs | Functional | `tests/src/Functional/Controller/CharacterStateControllerTest.php` | Condition add/remove lifecycle | add returns condition id, remove deletes, subsequent GET confirms absence |
| DCCT-030 | P2 | Character state APIs | Functional | `tests/src/Functional/Controller/CharacterStateControllerTest.php` | Inventory update validation | invalid inventory payload rejected with stable error schema |
| DCCT-031 | P2 | Character state APIs | Functional | `tests/src/Functional/Controller/CharacterStateControllerTest.php` | XP gain + level-up sequence | XP increments correctly and level-up changes level-dependent fields |
| DCCT-032 | P2 | Character state APIs | Functional | `tests/src/Functional/Controller/CharacterStateControllerTest.php` | Cast spell/start turn validation | required fields enforced and turn-state transitions consistent |
| DCCT-033 | P2 | Route method constraints | Functional | `tests/src/Functional/Routes/ApiRoutesTest.php` | Wrong HTTP methods return 405 across API set | explicit 405 assertions for all POST-only/GET-only endpoints |
| DCCT-034 | P2 | Unit service hardening | Unit | `tests/src/Unit/Service/CharacterCalculatorTest.php` | Edge ability-score boundaries | PF2e modifier math at low/high boundaries |
| DCCT-035 | P2 | Unit service hardening | Unit | `tests/src/Unit/Service/CombatCalculatorTest.php` | Degrees-of-success + MAP logic | deterministic outcomes vs fixture vectors |
| DCCT-036 | P2 | Unit AI provider fallback | Unit | `tests/src/Unit/Service/EncounterAiIntegrationServiceTest.php` | AI provider timeout/failure fallback | stub fallback selected, logs emitted, caller receives safe payload |

## Suggested Implementation Order

1. Implement all P0 cases first (access + campaign lifecycle + step-save route behavior).
2. Implement P1 controller/API gaps next (object manager, generated image APIs, dungeon/room state, architecture/testing pages).
3. Finish with P2 hardening and deterministic unit edge-case coverage.

## Execution Notes

- Run functional tests from Drupal web root:
  - `cd sites/dungeoncrawler/web`
  - `../vendor/bin/phpunit -c modules/custom/dungeoncrawler_content/phpunit.xml --testsuite functional`
- Prefer fixture-backed setup (`tests/fixtures/*`) and existing test traits for repeatability.
- For JSON endpoints, assert both status code and response contract (`success`, `error`/`data` keys).