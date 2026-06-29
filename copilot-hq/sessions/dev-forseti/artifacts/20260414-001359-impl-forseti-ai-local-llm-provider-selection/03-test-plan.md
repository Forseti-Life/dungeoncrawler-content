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
