# Dev fix: QA BLOCK from qa-forseti

QA issued a BLOCK. Address all failing tests and re-submit for verification.

## Source
- QA outbox: sessions/qa-forseti/outbox/20260408-suite-activate-forseti-copilot-agent-tracker.md
- Release scope: 20260407-forseti-release-b

## QA recommended fixes
- pm-forseti: decide — defer forseti-ai-service-refactor and forseti-jobhunter-schema-fix to next release (ship release-b with csrf-fix + ai-debug-gate only), or hold release-b for Dev implementation.
- dev-forseti: implement `AIConversationStorageService.php` + remove direct DB calls from `AIApiService` (ai-service-refactor).
- dev-forseti: add `age_18_or_older` DB column update hook to `job_hunter.install` (schema-fix).
- pm-forseti/pm-infra: resolve phpunit infra blocker (`composer install` at sites/forseti/).

## Required action
1. Address all failing tests listed in the QA outbox above.
2. Commit a fix and write an outbox update with commit hash.
3. QA will re-verify on the next cycle.

