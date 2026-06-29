- Status: done
- Completed: 2026-04-14T00:46:30Z

# Suite Activation: forseti-ai-local-llm-provider-selection

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-14T00:13:59+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-ai-local-llm-provider-selection"`**  
   This links the test to the living requirements doc at `features/forseti-ai-local-llm-provider-selection/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-ai-local-llm-provider-selection-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-ai-local-llm-provider-selection",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-ai-local-llm-provider-selection"`**  
   Example:
   ```json
   {
     "id": "forseti-ai-local-llm-provider-selection-<route-slug>",
     "feature_id": "forseti-ai-local-llm-provider-selection",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-ai-local-llm-provider-selection",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-ai-local-llm-provider-selection

- Feature: forseti-ai-local-llm-provider-selection
- Module: ai_conversation
- Author: pm-forseti (skeleton; QA to elaborate commands)
- Date: 2026-04-13
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- Admin session cookie in `$FORSETI_COOKIE_ADMIN`
- Ollama running locally at `http://localhost:11434` (or set `OLLAMA_BASE_URL` to a test endpoint)
- At least one existing conversation in the test user's account

## Test cases

### TC-1: Admin config form loads

- **Type:** functional / smoke
- **When:** GET `/admin/config/forseti/ai-provider` as admin
- **Then:** 200 OK; form shows org-default provider selector and `OLLAMA_BASE_URL` field

---

### TC-2: Admin saves org-default provider

- **Type:** functional
- **When:** admin sets org-default to `openai` and saves
- **Then:** config is persisted; form shows saved value on reload

---

### TC-3: User sets provider preference

- **Type:** functional
- **When:** authenticated user edits their account (`/user/{uid}/edit`) and sets provider to `openai`
- **Then:** `field_ai_provider` is saved on the user entity

---

### TC-4: Chat uses user provider preference (OpenAI path)

- **Type:** functional / integration
- **When:** user with `field_ai_provider = openai` sends a chat message
- **Then:** response returns successfully from OpenAI API; no regression on `/forseti/chat`

---

### TC-5: Ollama option disabled when OLLAMA_BASE_URL unset

- **Type:** functional / edge-case
- **When:** `OLLAMA_BASE_URL` is not configured and user attempts to select Ollama
- **Then:** inline error shown; Ollama option disabled

---

### TC-6: Unreachable provider falls back gracefully

- **Type:** functional / error-handling
- **When:** user provider is set to a misconfigured Ollama (bad URL)
- **Then:** provider-error banner shown; chat falls back to org default without unhandled exception

---

### TC-7: Existing chat routes unaffected (regression)

- **Type:** regression
- **When:** GET/POST to `/forseti/chat` and `/forseti/conversations` as authenticated user
- **Then:** all existing routes return expected status codes; no change in behavior for OpenAI-only users

---

### TC-8: ACL — provider config requires admin

- **Type:** security / ACL
- **When:** GET `/admin/config/forseti/ai-provider` as non-admin authenticated user
- **Then:** 403 Forbidden

### Acceptance criteria (reference)

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
