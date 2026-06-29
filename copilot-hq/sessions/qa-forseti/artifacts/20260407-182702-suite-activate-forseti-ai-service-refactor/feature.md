# Feature Brief

- Work item id: forseti-ai-service-refactor
- Website: forseti.life
- Module: ai_conversation
- Status: ready
- Release: 20260405-forseti-release-b
- Priority: P2
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Source: BA inventory AI-R1 (ROI 8)

## Goal

Refactor `AIApiService` (1260 lines) to extract the 14 direct DB queries into a dedicated `AIConversationStorageService`. This reduces coupling, makes the service testable in isolation, and prevents future regressions where storage logic is mixed into API logic.

## Non-goals

- Changing AI model selection logic or API call behavior.
- Rewriting the full service in a new architecture (incremental extraction only).
- Touching `GenAiDebugController` (tracked separately as AI-R2 / forseti-ai-debug-gate).

## PM Decision

Extract DB queries to `AIConversationStorageService`. Keep public method signatures unchanged so callers are unaffected. No flag needed — this is a pure refactor with identical external behavior.

## Gap Analysis

### Implementation status

| Requirement | Existing code path | Coverage status |
|---|---|---|
| AIApiService DB queries extracted to storage service | `AIApiService.php` (14 direct queries inline) | None |
| AIConversationStorageService exists | N/A | None (new class) |
| All callers updated to use storage service | N/A | None |
| No behavior change to existing callers | N/A | None (test-only after refactor) |

### Coverage determination

- **Feature type: enhancement** — New service class needed; extraction is dev work. Post-refactor, QA confirms behavior parity.

### Test path guidance for QA

- `web/modules/custom/ai_conversation/tests/src/Unit/` — unit tests for `AIConversationStorageService` CRUD methods.
- Confirm existing integration behavior unchanged: conversation history persists and loads correctly.

## Risks

- If any caller accesses DB queries directly via AIApiService (not through the public API), those must also be updated. Dev must audit all callers.

## Latest updates

- 2026-04-06: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-04-05: Handed off to QA for test generation (pm-qa-handoff.sh)

- 2026-04-05: Feature brief created from BA inventory AI-R1; scoped for 20260405-forseti-release-b.

## Security acceptance criteria
- Authentication/permission surface: `AIConversationStorageService` is a backend service class, not a route. All existing caller routes retain their existing permission checks — no new surfaces introduced.
- CSRF expectations: No new routes added. Existing route CSRF expectations unchanged.
- Input validation: Storage service methods must not accept raw user input directly; callers are responsible for sanitizing before passing to storage layer. Confirm no new `db_query()` with unsanitized strings introduced.
- PII/logging constraints: Conversation history stored in DB contains user messages. Storage service must not log message content at DEBUG level. Error logging must use message IDs, not content.
