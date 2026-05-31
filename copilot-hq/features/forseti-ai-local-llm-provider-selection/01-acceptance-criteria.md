# Acceptance Criteria: forseti-ai-local-llm-provider-selection

- Feature: forseti-ai-local-llm-provider-selection
- Module: ai_conversation
- Author: pm-forseti (extracted from feature.md)
- Date: 2026-04-13
- Project: PROJ-005

## Summary

Add a provider selection layer to `AIApiService` so users can choose between OpenAI and Ollama (self-hosted local LLM). Eliminates hard-wired OpenAI dependency and advances the org mission of decentralized services.

## Acceptance criteria

### AC-1: Admin provider configuration

**Given** an authenticated admin at `/admin/config/forseti/ai-provider`,
**When** they set org-default provider (`openai` or `ollama`) and `OLLAMA_BASE_URL`,
**Then** settings are saved and the available Ollama model list is configurable.

---

### AC-2: User provider preference

**Given** an authenticated user at `/user/{uid}/edit`,
**When** they select a personal provider preference (OpenAI or Ollama) and model,
**Then** preference is stored as `field_ai_provider` and `field_ai_model` on the user entity.

---

### AC-3: Provider resolution order

**Given** an authenticated user initiates a chat,
**When** `AIApiService::chat()` is invoked,
**Then** the effective provider is resolved as: user preference → org default → OpenAI fallback.

---

### AC-4: Ollama API service

**Given** the effective provider is Ollama,
**When** a chat request is made,
**Then** `OllamaApiService` (or equivalent) sends HTTP POST to `{OLLAMA_BASE_URL}/api/chat` with correct Ollama payload; streaming response is handled consistently with the existing OpenAI path.

---

### AC-5: Unreachable provider fallback

**Given** the user's selected provider is unreachable (connection refused / timeout),
**When** a chat request is made,
**Then** the chat UI shows a provider-error banner and falls back to the org default without crashing.

---

### AC-6: Existing routes unaffected

**Given** the feature is deployed,
**When** requests are made to `/forseti/chat` and `/forseti/conversations`,
**Then** all existing chat routes and conversation API endpoints work correctly regardless of the effective provider.

---

### AC-7: Ollama disabled until configured

**Given** `OLLAMA_BASE_URL` is unset,
**When** a user or admin attempts to select Ollama,
**Then** the settings form displays an inline error; the Ollama option is disabled in user account settings.

## Definition of done

- All 7 ACs pass QA verification on the dev environment.
- `AIApiService` is not tightly coupled to OpenAI after this feature (provider is injected or resolved via config).
- No regressions on existing `/forseti/chat` flow (suite `ai_conversation_user_chat`).
