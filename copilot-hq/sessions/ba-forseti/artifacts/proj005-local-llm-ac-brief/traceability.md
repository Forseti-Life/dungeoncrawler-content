# AC Traceability Brief: forseti-ai-local-llm-provider-selection

- Feature: forseti-ai-local-llm-provider-selection
- Author: ba-forseti
- Date: 2026-04-12
- Release: 20260412-forseti-release-e
- Project: PROJ-005 (AI Conversation)

---

## ⚠ Critical correction: actual provider is AWS Bedrock, not OpenAI

The feature summary states the platform is "hard-wired to a single external LLM provider (OpenAI API)." **This is incorrect.** The actual provider is **AWS Bedrock** with Claude models (default: `us.anthropic.claude-sonnet-4-6`). There is no OpenAI key anywhere in the codebase or config schema.

**Evidence:**
- `AIApiService::buildBedrockClient()` creates `\Aws\BedrockRuntime\BedrockRuntimeClient`
- `config/install/ai_conversation.settings.yml` has `aws_access_key_id`, `aws_secret_access_key`, `aws_region`, `aws_model`
- `config/schema/ai_conversation.schema.yml` has no OpenAI keys

**Impact on AC scope:**
- AC-1, AC-3, AC-7: Replace "OpenAI" with "AWS Bedrock" as the first/fallback provider. The AC text saying "OpenAI fallback" should read "Bedrock fallback" or "system default (Bedrock)".
- AC-2: `field_ai_provider` values should be `bedrock` and `ollama` (not `openai` and `ollama`).
- AC-4: The new `OllamaApiService` will be the HTTP alternative — the existing service is Bedrock, not OpenAI.

**Recommendation to pm-forseti**: Update feature.md to replace "OpenAI" with "AWS Bedrock" throughout. This is a factual correction, not a scope change.

---

## Service architecture note: current `AIApiService` wiring

**File:** `sites/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php`

### Is the AWS/API key hardcoded or injected via config?

Injected via Drupal config (`ai_conversation.settings`), with environment variable fallback:
```php
$aws_access_key = $config->get('aws_access_key_id') ?: getenv('AWS_ACCESS_KEY_ID');
$aws_secret_key = $config->get('aws_secret_access_key') ?: getenv('AWS_SECRET_ACCESS_KEY');
```
Keys are **not hardcoded**. They resolve from `ai_conversation.settings` config object (admin-editable) or env vars. ✅ Safe.

### Is the model name configurable?

Yes. Model is configurable via `ai_conversation.settings.aws_model`. Default is `us.anthropic.claude-sonnet-4-6`. A fallback list is hardcoded in `getModelFallbacks()` but the primary model is config-driven. ✅ Configurable.

### Is there an interface `OllamaApiService` could implement?

**No interface exists.** `AIApiService` is a concrete class with no declared interface. The public contract is:
- `sendMessage(NodeInterface $conversation, string $message)` — primary chat entry point

There is **no `chat()` method** — the feature.md AC-3 references `AIApiService::chat()` but the actual method is `sendMessage()`. Dev must use `sendMessage()` or alias it.

**Recommended interface for dev-forseti** (BA draft):

```php
// New file: src/Service/LLMProviderInterface.php
interface LLMProviderInterface {
    public function sendMessage(NodeInterface $conversation, string $message): mixed;
    public function isAvailable(): bool;
}
```

`AIApiService` would implement `LLMProviderInterface`. The new `OllamaApiService` would also implement it. `AIApiService` (currently the outer service that resolves providers) would inject one of these at request time based on user/org preference.

**However**, given the complexity of `AIApiService` (rolling summary, token management, conversation storage), the cleanest approach is a **provider adapter pattern**:

1. Extract the raw API call into a thin `BedrockProviderAdapter` (implements `LLMProviderInterface`)
2. Create `OllamaProviderAdapter` (implements `LLMProviderInterface`)
3. `AIApiService` retains all business logic; it selects the correct adapter at request time

This avoids rewriting `AIApiService` and is the minimal-change path.

---

## AC Traceability Table

### AC-1: Admin form for org-default provider

| Item | Detail |
|---|---|
| **Feature AC text** | `/admin/config/forseti/ai-provider` allows setting org-default provider (`openai` or `ollama`) and `OLLAMA_BASE_URL` |
| **Corrected target** | Provider should be `bedrock` or `ollama` (not `openai`) |
| **Implementation file** | New: `src/Form/SettingsForm.php` — extend existing form to add provider fieldset; OR new `src/Form/AIProviderSettingsForm.php` |
| **Config key target** | New config keys: `ai_conversation.settings.default_provider` (string), `ollama_base_url` (string), `ollama_models` (sequence) |
| **Route target** | New route `ai_conversation.admin_provider_settings` at `/admin/config/forseti/ai-provider` OR extend `/admin/config/ai-conversation/settings` (existing path) |
| **Dependency** | Requires schema extension in `config/schema/ai_conversation.schema.yml` |
| **Verify** | `drush cget ai_conversation.settings default_provider` → `bedrock`; browse `/admin/config/forseti/ai-provider` as admin → HTTP 200 with form |
| **Risk** | ⚠ Path discrepancy: existing admin form is at `/admin/config/ai-conversation/settings`, not `/admin/config/forseti/ai-provider`. Dev must decide: new route or extend existing. PM decision needed (see risk flags). |

---

### AC-2: Per-user provider preference on account page

| Item | Detail |
|---|---|
| **Feature AC text** | User can set `field_ai_provider` and `field_ai_model` on `/user/{uid}/edit`; stored on user entity |
| **Corrected values** | `field_ai_provider` values: `bedrock` / `ollama` (not `openai`) |
| **Implementation file** | `ai_conversation.install` — new `hook_update_N` to add `field_ai_provider` (string) on user entity; `field_ai_model` already on `ai_conversation` content type (not user entity) |
| **Note** | `field_ai_model` currently exists on the **`ai_conversation` node** (conversation entity), NOT the user entity. AC-2 intends a user-level model preference — this is a **new user entity field**. Dev must add `field_ai_model` to the user entity in the install update hook. |
| **Verify** | `drush sql:query "SELECT field_ai_provider_value FROM user__field_ai_provider WHERE entity_id=<uid>"` → `ollama`; visit `/user/{uid}/edit` → see provider/model dropdowns |
| **Risk** | ⚠ Name collision: `field_ai_model` already exists on `ai_conversation` content type. Adding same field name to user entity is technically a different field storage but may confuse dev — PM should clarify or rename to `field_user_ai_provider` / `field_user_ai_model`. |

---

### AC-3: Provider resolution at request time

| Item | Detail |
|---|---|
| **Feature AC text** | `AIApiService::chat()` resolves: user preference → org default → OpenAI fallback |
| **Corrected text** | `AIApiService::sendMessage()` resolves: user preference → org default → Bedrock fallback |
| **Implementation file** | `src/Service/AIApiService.php` — add `resolveProvider(AccountInterface $account): LLMProviderInterface` private method |
| **Logic** | Read `$account->get('field_ai_provider')->value`; if set and provider is available → use it; else read `ai_conversation.settings.default_provider`; else use Bedrock |
| **Dependency** | AC-1 (default_provider config key must exist), AC-2 (user field must exist), AC-4 (OllamaApiService must exist) |
| **Verify** | Set user A `field_ai_provider=ollama`; send chat message → watchdog log shows Ollama adapter called; set user B `field_ai_provider=bedrock` → Bedrock called |
| **Risk** | ⚠ Resolution logic adds complexity to `sendMessage()` which already has fallback model chains. Must not break existing `getModelFallbacks()` for Bedrock path. |

---

### AC-4: OllamaApiService — HTTP POST to Ollama

| Item | Detail |
|---|---|
| **Feature AC text** | New `OllamaApiService` POSTs to `{OLLAMA_BASE_URL}/api/chat` with correct Ollama payload; streaming handled consistently |
| **Implementation file** | New: `src/Service/OllamaApiService.php` (or `OllamaProviderAdapter.php` if adapter pattern used) |
| **HTTP client** | Use `\Drupal::httpClient()` (Guzzle) — already available, no new dependency |
| **Payload format** | Ollama `/api/chat` expects: `{"model": "<model>", "messages": [...], "stream": false}` |
| **Verify** | `curl -X POST http://localhost:11434/api/chat -d '{"model":"llama3","messages":[{"role":"user","content":"hello"}],"stream":false}'` → 200 (requires local Ollama). In test: mock HTTP client returns valid Ollama response; `OllamaApiService::sendMessage()` parses it correctly |
| **Risk** | ⚠ Ollama streaming: the feature says "streaming response is handled consistently with the existing OpenAI path" — but the existing `AIApiService` does NOT stream (it uses `invokeModel()` blocking call, not `invokeModelWithResponseStream()`). The AC wording implies streaming parity but the current path is synchronous. Dev should match the existing synchronous pattern and set `"stream": false` for Ollama too. If streaming is truly intended, that's a new scope item. |

---

### AC-5: Provider unreachable — error banner + fallback

| Item | Detail |
|---|---|
| **Feature AC text** | If selected provider unreachable: show provider-error banner; fall back to org default without crashing |
| **Implementation file** | `src/Service/AIApiService.php` — wrap `OllamaApiService::sendMessage()` in try/catch; catch `GuzzleHttp\Exception\ConnectException` and `RequestException`; set error state; re-call `sendMessage()` with Bedrock provider |
| **UI file** | Chat controller or template — check for `provider_error` flag in response and render warning banner |
| **Verify** | With Ollama not running: user with `field_ai_provider=ollama` sends message → chat UI shows "Provider unavailable, using system default" banner; response still returns from Bedrock |
| **Risk** | ⚠ Fallback loop guard needed: if org default is also Ollama (misconfigured), fallback must not recurse. Dev must hardcode "last resort" to Bedrock. |

---

### AC-6: No regressions on existing chat routes

| Item | Detail |
|---|---|
| **Feature AC text** | Existing chat routes work regardless of effective provider |
| **Routes to verify** | `/forseti/chat`, `/forseti/conversations`, `/api/ai-conversation/create`, `/api/ai-conversation/{id}/message`, `/api/ai-conversation/{id}/history` |
| **Dependency** | No dependency — this is a regression check |
| **Verify** | Run `qa-suites/products/forseti/suite.json` suite `ai_conversation_user_chat`; all tests PASS. Also: `curl -s -b admin_cookies.txt https://forseti.life/forseti/chat` → HTTP 200 |
| **Risk** | Low. The Bedrock path must not be touched by provider resolution when user has no `field_ai_provider` set (NULL → use org default → Bedrock). AC-3 fallback chain covers this. |

---

### AC-7: OLLAMA_BASE_URL unset — settings error and disabled option

| Item | Detail |
|---|---|
| **Feature AC text** | If `OLLAMA_BASE_URL` unset and user selects Ollama: inline error; Ollama disabled in user settings until configured |
| **Implementation file** | `src/Form/SettingsForm.php` (admin form) — validate `ollama_base_url` is set when provider=ollama is saved; `src/Form/UserAIPreferencesForm.php` or user entity form alter — disable Ollama option in `field_ai_provider` dropdown when `ollama_base_url` is empty |
| **Config key** | `ai_conversation.settings.ollama_base_url` — must exist and be non-empty to enable Ollama option |
| **Verify** | With `ollama_base_url` empty: user `/edit` page shows Ollama disabled; admin form shows inline error if Ollama selected without URL. `drush cget ai_conversation.settings ollama_base_url` → empty → user form has Ollama disabled. |
| **Risk** | ⚠ Form alter vs dedicated form: AC-7 implies disabling a user account form field based on site config. This requires either `hook_form_user_form_alter()` in the module or a dedicated user preferences page. The hook approach is simpler but adds coupling. Dev should use hook. |

---

## Risk flags summary

| AC | Risk level | Issue | PM action needed? |
|---|---|---|---|
| AC-1 | ⚠ Medium | Admin path mismatch: `/admin/config/forseti/ai-provider` vs existing `/admin/config/ai-conversation/settings` | Yes — choose: new route or extend existing |
| AC-2 | ⚠ Medium | `field_ai_model` name collision with existing conversation node field; user entity field not yet defined | Yes — rename to `field_user_ai_model` to avoid confusion |
| AC-3 | ⚠ Medium | Feature says `::chat()` but actual method is `::sendMessage()` | No — dev fix, no PM action |
| AC-3 | ⚠ High | Provider "OpenAI" in all ACs should be "AWS Bedrock" | Yes — feature.md update |
| AC-4 | ⚠ Low | Feature implies streaming parity with "OpenAI path" but existing Bedrock path is synchronous | No — dev should match synchronous pattern |
| AC-5 | ⚠ Low | Fallback loop guard: if org default is also Ollama, need hardcoded last resort | No — dev responsibility |
| All | ⚠ High | No `LLMProviderInterface` exists; no Drupal service tag pattern for providers | No — dev architecture decision; BA recommends adapter pattern above |

---

## Recommended clarification for pm-forseti (AC tightening)

1. **Update feature.md**: Replace all "OpenAI" references with "AWS Bedrock". Provider values should be `bedrock` / `ollama` not `openai` / `ollama`.

2. **AC-1 admin route**: Confirm target path — `/admin/config/forseti/ai-provider` (new route) or extend existing `/admin/config/ai-conversation/settings`. The existing path already has `_permission: 'administer site configuration'`. Recommend: extend existing settings form with a new "Provider" fieldset (less routing work).

3. **AC-2 field naming**: Rename `field_ai_provider` → `field_user_ai_provider` and note that `field_ai_model` on user entity is distinct from `field_ai_model` on `ai_conversation` node. Use `field_user_ai_model` on user entity.

4. **AC-3 method name**: Replace `AIApiService::chat()` → `AIApiService::sendMessage()` in AC text.

5. **AC-4 streaming**: Clarify "handled consistently" — synchronous (matches current Bedrock implementation) or streaming (new scope). Recommend: synchronous for this release.
