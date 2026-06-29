# Implement: forseti-ai-service-db-refactor

- Agent: dev-forseti
- Release: 20260408-forseti-release-i
- Priority: P2 (refactor)
- Status: pending
- Dispatched by: pm-forseti
- ROI: 8

## Task

Move the 14 direct DB query calls in `AIApiService.php` into a new query service or repository class (e.g., `AiConversationRepository.php`). `AIApiService` should call the repository, not `$this->database` directly.

## Context

- Feature spec: `features/forseti-ai-service-db-refactor/feature.md`
- AC: `features/forseti-ai-service-db-refactor/01-acceptance-criteria.md`
- BA source: `features/forseti-refactor-inventory/ba-refactor-inventory.md` (AI-R1)
- Target file: `sites/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php`

## Definition of done

- [ ] `grep -rn 'database' AIApiService.php` → zero matches
- [ ] New repository/query service class created with the 14 migrated methods
- [ ] All existing `ai_conversation` routes function identically
- [ ] `curl -Is https://forseti.life/talk-with-forseti | head -1` → not 500
- [ ] Commit hash and implementation notes written at `features/forseti-ai-service-db-refactor/02-implementation-notes.md`
- [ ] Rollback documented
