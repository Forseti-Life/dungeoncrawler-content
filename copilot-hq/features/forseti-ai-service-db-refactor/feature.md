# Feature Brief: forseti-ai-service-db-refactor

- Work item id: forseti-ai-service-db-refactor
- Website: forseti.life
- Module: ai_conversation
- Project: PROJ-005
- Group Order: 3
- Group: ai-services
- Group Title: AI Services & Integration
- Group Sort: 2
- Status: shipped
- Release: 20260408-forseti-release-i
- Priority: P2
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- BA source: AI-R1 (features/forseti-refactor-inventory/ba-refactor-inventory.md)

## Goal

Move the 14 direct DB queries in `AIApiService.php` (1260 lines) out of the service class and into a dedicated repository or query service, following the service-layer pattern already established in `job_hunter`. `AIApiService` should call the query service, not `$this->database` directly.

## Background

`AIApiService.php` contains 14 direct `$this->database` (or equivalent) calls bypassing any service layer. This makes unit testing impossible without a real database and couples AI logic to the schema. No PM flag required — dev-startable per BA analysis.

## Definition of Done

- `AIApiService.php` has zero direct DB query calls (all delegated to a query service/repository).
- New query service/repository class created (e.g., `AiConversationRepository.php`).
- All existing AI conversation routes function identically (QA smoke test at `https://forseti.life/talk-with-forseti` with auth).
- Commit hash recorded in implementation notes.

## Security acceptance criteria

- Authentication/permission surface: No new routes or permissions introduced. All existing `ai_conversation` route permissions unchanged.
- CSRF expectations: No routing changes. Existing CSRF behavior on AI routes unchanged.
- Input validation: No new input surfaces. Validation logic (if any) remains in service — behavior identical.
- PII/logging constraints: No new logging introduced. AI conversation content must not be logged at a more granular level than before. Existing Drupal watchdog patterns apply.

## Rollback

`git revert` the refactor commit. No schema changes.
