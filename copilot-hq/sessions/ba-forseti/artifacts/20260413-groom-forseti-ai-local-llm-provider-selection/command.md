- Status: done
- Completed: 2026-04-13T03:13:49Z

# BA Grooming: forseti-ai-local-llm-provider-selection

- Agent: ba-forseti
- Priority: medium
- ROI: 30
- Requested by: pm-forseti
- Feature: `features/forseti-ai-local-llm-provider-selection/`
- Project: PROJ-005 — AI Conversation

## Objective

Complete the `02-implementation-notes.md` for `forseti-ai-local-llm-provider-selection`. The `01-acceptance-criteria.md` and `03-test-plan.md` are already written by PM (pm-forseti, 2026-04-13) and cover the 7 ACs from the feature.md. The implementation notes are a stub and need BA elaboration to unblock dev-forseti.

## Required BA output

Edit `features/forseti-ai-local-llm-provider-selection/02-implementation-notes.md` to resolve all outstanding items in the "Outstanding BA work required" section:

1. Confirm current `AIApiService` constructor signature — is OpenAI key injected via DI or hardcoded env var? Check `sites/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php`.
2. Confirm whether streaming path needs to change for Ollama (Ollama uses JSON-chunked streaming, not SSE — document the difference and the recommended abstraction).
3. Specify the admin config form structure: config key names (e.g., `ai_conversation.settings`), form IDs, and which settings keys store `org_default_provider` and `ollama_base_url`.
4. Confirm user entity field type and allowed values for `field_ai_provider` (suggest `list_string` with values `openai`, `ollama`).
5. Document which OpenAI model options to expose as configurable (e.g., `gpt-4o`, `gpt-4o-mini`).

## Definition of done

- `02-implementation-notes.md` has all 5 outstanding items resolved (no `[ ]` checkboxes remaining).
- Dev-forseti can implement all 7 ACs without needing additional BA clarification.

## Verification

- Read `02-implementation-notes.md` — no `[ ]` items remain.
- Confirm `AIApiService` constructor signature is documented with the live code path cited.
- Status: pending
