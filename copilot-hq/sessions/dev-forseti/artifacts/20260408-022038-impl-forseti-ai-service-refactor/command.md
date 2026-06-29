# Implementation Task — forseti-ai-service-refactor

- Release: 20260407-forseti-release-c
- Feature: forseti-ai-service-refactor
- Priority: P2
- Dispatched by: pm-forseti

## Goal

Refactor `AIApiService` (1260 lines) to extract the 14 direct DB queries into a dedicated `AIConversationStorageService`. This reduces coupling, makes the service testable in isolation, and prevents future regressions where storage logic is mixed into API logic.

## Reference files

- Feature brief: `features/forseti-ai-service-refactor/feature.md`
- Acceptance criteria: `features/forseti-ai-service-refactor/01-acceptance-criteria.md`
- Test plan: `features/forseti-ai-service-refactor/03-test-plan.md`

## Definition of done

All acceptance criteria in `01-acceptance-criteria.md` met.
Code committed to main with a summary commit message referencing `forseti-ai-service-refactor`.
No `\Drupal::database()` calls remain inline in `AIApiService.php`.
`AIConversationStorageService` class created and injected into `AIApiService`.
All existing callers updated.
PHP syntax clean (`php -l` on modified files).

## Note

`vendor/bin/phpunit` is not provisioned at `/var/www/html/forseti` — functional tests cannot be run live. Static analysis + grep-based coverage is the verification baseline. Document this in your outbox.
