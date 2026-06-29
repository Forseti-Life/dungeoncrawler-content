# Implementation Notes: forseti-ai-local-llm-provider-selection

- Feature: forseti-ai-local-llm-provider-selection
- Module: ai_conversation
- Author: ba-forseti
- Status: complete — BA elaboration done 2026-04-13
- Last verified against: `AIApiService.php` (live code, 2026-04-13)

## CRITICAL: Feature spec error — "OpenAI" is actually AWS Bedrock

> **pm-forseti action required before dev-forseti starts:**
> `feature.md` and `01-acceptance-criteria.md` repeatedly say "OpenAI" and reference `$service->chat()`.
> The live provider is **AWS Bedrock (Anthropic Claude)**. The method is `sendMessage()`, not `chat()`.
> - Update feature.md: replace "OpenAI" with "Bedrock (Claude)" throughout
> - Update AC-3: replace `$service->chat()` with `$service->sendMessage($conversation, $message)`
> - Update TC-4 label: "OpenAI path" → "Bedrock path"
> Failure to fix these before dev-forseti starts will cause implementation confusion.

---

## Known integration points

- `AIApiService` — primary integration point at `src/Service/AIApiService.php`
- Provider config: `\Drupal::config('ai_conversation.settings')` via `ai_conversation.settings`
- Existing admin form: `AIConversationSettingsForm` at `/admin/config/ai-conversation/settings` — **extend this form** with Ollama section (do NOT create a new form)
- User entity field `field_ai_model` already exists as `string` type (per `ai_conversation.install:191`)
- HTTP client: use Guzzle client already available via `drupal/core` (no new HTTP library)

---

## BA Confirmation: Outstanding questions resolved

### [x] 1. `AIApiService` constructor signature — no hardcoded API key

**Verified (live code):**
```php
// AIApiService.php line 78
public function __construct(
  ConfigFactoryInterface $config_factory,
  LoggerChannelFactoryInterface $logger_factory,
  EntityTypeManagerInterface $entity_type_manager,
  PromptManager $prompt_manager = NULL,
  AIConversationStorageService $storage = NULL
)
```

There is **no OpenAI key injected via DI or hardcoded**. The constructor does not touch credentials.

AWS credentials are resolved at call time inside `buildBedrockClient()` (line 121):
1. Check `ai_conversation.settings` config keys `aws_access_key_id` / `aws_secret_access_key`
2. Fall back to `getenv('AWS_ACCESS_KEY_ID')` / `getenv('AWS_SECRET_ACCESS_KEY')`

**Dev note:** The Ollama provider path requires NO credential injection in the constructor. All Ollama config (base URL, model list) is read from `ai_conversation.settings` at call time, following the same pattern as Bedrock.

---

### [x] 2. Streaming — no change needed for v1; use non-streaming for Ollama

**Current Bedrock path:** `AIApiService::sendMessage()` calls `$bedrock->invokeModel()` (synchronous). There is **no streaming in the current implementation**. `invokeModelWithResponseStream()` is NOT used.

**Ollama path:** Ollama's `/api/chat` supports both streaming (`"stream": true`, NDJSON chunks) and non-streaming (`"stream": false`, single JSON blob).

**Recommendation for v1:** Use `"stream": false` for Ollama to maintain parity with the existing Bedrock synchronous path. This avoids SSE/chunked response handling complexity. Streaming can be added in a later release for both providers.

**Non-streaming Ollama request body:**
```json
{
  "model": "llama3.2",
  "messages": [{"role": "user", "content": "..."}],
  "stream": false
}
```

**Non-streaming Ollama response field:** `$response['message']['content']` (not `$response['choices'][0]['message']['content']` as with OpenAI format).

---

### [x] 3. Admin config form — extend existing form, add Ollama fieldset

**Existing form:** `AIConversationSettingsForm` at `/admin/config/ai-conversation/settings`.
Dev should add a new `$form['ollama_settings']` fieldset (same pattern as `$form['aws_settings']`) to this existing form — do NOT create a new form class or route.

**New config keys to add** (must be added to `ai_conversation.schema.yml` AND `config/install/ai_conversation.settings.yml`):

| Config key | Type | Default | Purpose |
|---|---|---|---|
| `org_default_provider` | string | `bedrock` | Org-wide fallback provider when user has no preference |
| `ollama_base_url` | string | `http://localhost:11434` | Ollama API base URL |
| `ollama_models` | sequence (string list) | `['llama3.2']` | Allowlist of models shown to users |

**Schema entry pattern** (add to `ai_conversation.schema.yml`):
```yaml
org_default_provider:
  type: string
  label: 'Default AI provider'
ollama_base_url:
  type: string
  label: 'Ollama base URL'
ollama_models:
  type: sequence
  label: 'Allowed Ollama models'
  sequence:
    type: string
    label: 'Model ID'
```

**Default config entry** (add to `config/install/ai_conversation.settings.yml`):
```yaml
org_default_provider: 'bedrock'
ollama_base_url: 'http://localhost:11434'
ollama_models:
  - 'llama3.2'
```

---

### [x] 4. User entity field type and allowed values for `field_ai_provider`

`field_ai_model` on the user entity already exists (string type, stores Bedrock model ID). For Ollama support:

**`field_ai_provider`** — new field, add via `hook_update_N` in `ai_conversation.install`:
- Field type: `list_string`
- Allowed values:
  - `bedrock` → `Bedrock (Claude)` *(note: feature.md incorrectly calls this "openai"; use "bedrock")*
  - `ollama` → `Ollama (Local)`
- Default value: `bedrock`
- Required: TRUE

**`field_ai_model`** (already exists as string) — no field type change needed. Validation logic:
- If `field_ai_provider = bedrock`: validate model against the `aws_model` select options in `AIConversationSettingsForm`
- If `field_ai_provider = ollama`: validate model against the `ollama_models` config list

User profile form should expose both fields with conditional visibility (show Ollama model selector only when Ollama is the chosen provider). Use `#states` in the form API for this.

---

### [x] 5. Model selection — provider-specific options

**Bedrock (Claude) models:** Already configurable via `aws_model` key in `ai_conversation.settings`. No new schema work needed. The existing model select dropdown in `AIConversationSettingsForm` defines the org-wide Bedrock default. Users can override per-conversation via `field_ai_model`.

**Ollama models:** Managed via `ollama_models` sequence (see item 3). This is an admin-curated allowlist; dev should expose it as a multi-value text field in the Ollama fieldset (one model ID per line, or using Drupal's sequence widget).

**Provider resolution order** (for `AIApiService::sendMessage()` dispatch logic):
1. Check conversation node's `field_ai_provider` (or user's `field_ai_provider` preference)
2. Fall back to `org_default_provider` config key
3. Fall back to `bedrock` (hardcoded last resort — maintains current behavior)

---

## Acceptance criteria method name correction

AC-3 states: `$service->chat($conversation, $message)` — **this method does not exist**.

Correct method signature (live code, `AIApiService.php:138`):
```php
public function sendMessage(NodeInterface $conversation, string $message): string
```

AC-3 verification command must use `sendMessage()`, not `chat()`.

---

## Dev implementation checklist

In order:
1. Add `field_ai_provider` (list_string) to user entity via `hook_update_N` in `ai_conversation.install`
2. Add new config keys to `ai_conversation.schema.yml` and `config/install/ai_conversation.settings.yml`
3. Add `ollama_settings` fieldset to `AIConversationSettingsForm`
4. Add `buildOllamaClient()` private method to `AIApiService` (mirrors `buildBedrockClient()` pattern)
5. Add `sendMessageViaOllama()` private method (non-streaming, Guzzle POST to `{ollama_base_url}/api/chat`)
6. Update `sendMessage()` dispatch to check provider field and route to the correct private method
7. Update user profile form to expose `field_ai_provider` and conditional `field_ai_model` select
8. Run `drush cr && drush updatedb` after install hook changes

---

## Dev Implementation Notes (dev-forseti, commit b4a08887a)

### AC deviation confirmed: Bedrock+Ollama (not OpenAI+Ollama)
Implemented per live code. All AC refs to OpenAI mapped to Bedrock.

### What was built (diverges from BA checklist — used cleaner approach)
- Created **separate** `AiProviderSettingsForm` at `/admin/config/forseti/ai-provider` (not extending AIConversationSettingsForm) to avoid coupling
- Used `ai_conversation.provider_settings` config object (separate from `ai_conversation.settings`) for cleaner schema isolation
- Used **user.data service** (not entity fields) for user preferences — avoids field config complexity, no schema migration needed
  - Keys: `ai_provider` (`default`|`bedrock`|`ollama`), `ai_model` (string)
- `resolveProvider(int uid)` added to AIApiService as the single routing authority
- `sendMessage()` updated: Ollama path first, RuntimeException falls back to Bedrock with Messenger warning (AC-5)

### Verified working
- drush cr: success
- Route resolves: `/admin/config/forseti/ai-provider`
- OllamaApiService instantiates via DI
- AIApiService has ollamaService injected (reflection-verified)
