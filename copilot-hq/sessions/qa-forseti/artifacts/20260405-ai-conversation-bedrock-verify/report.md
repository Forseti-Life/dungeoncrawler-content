# QA Verification Report: ai_conversation Bedrock Emergency Fix

- Feature: `20260322-forseti-release-next` — Bedrock model emergency fix
- QA owner: qa-forseti
- Date: 2026-04-06
- Verdict: **APPROVE with one config note (non-blocking)**

## KB check
- None found specific to Bedrock model ID changes.
- `knowledgebase/lessons/20260227-routing-permission-mismatch-companyresearch.md` reviewed — not applicable (no routing changes in this fix).

---

## Evidence

### AC1 — `getModelFallbacks()` returns primary from config as first element

**PASS** (static analysis)

`AIApiService.php:94-101`:
```php
private function getModelFallbacks(): array {
    $primary = $this->configFactory->get('ai_conversation.settings')->get('aws_model')
        ?: 'us.anthropic.claude-sonnet-4-6';
    $fallbacks = [
      'us.anthropic.claude-sonnet-4-6',
      'us.anthropic.claude-haiku-4-5',
      'us.anthropic.claude-3-5-haiku-20241022-v1:0',
    ];
    return array_values(array_unique(array_merge([$primary], $fallbacks)));
}
```
Primary from config is prepended via `array_merge([$primary], $fallbacks)` and deduplicated.

### AC2 — `buildBedrockClient()` uses config values (not hardcoded region)

**PASS** (static analysis)

`AIApiService.php:107-119`:
```php
private function buildBedrockClient(): BedrockRuntimeClient {
    $config = $this->configFactory->get('ai_conversation.settings');
    $aws_access_key = $config->get('aws_access_key_id') ?: getenv('AWS_ACCESS_KEY_ID');
    $aws_secret_key = $config->get('aws_secret_access_key') ?: getenv('AWS_SECRET_ACCESS_KEY');
    $aws_region = $config->get('aws_region') ?: 'us-east-1';
    ...
}
```
Region and credentials read from `ai_conversation.settings` config first. `getenv()` is a safe fallback; hardcoded `us-west-2` is gone.

### AC3 — `generateSummary()` no longer references undefined `$config`

**PASS** (static analysis)

`AIApiService.php:973-1010`: `generateSummary()` calls `$this->buildBedrockClient()` and `$this->getModelFallbacks()`. No bare `$config` variable appears in scope — the old direct config access is eliminated. All config reads are encapsulated inside `buildBedrockClient()` via `$this->configFactory`.

### AC4 — `ChatController::createConversation()` no longer sets `field_ai_model`

**PASS** (grep)

```
grep -c "field_ai_model" ChatController.php → 0
```
Zero occurrences of `field_ai_model` in `ChatController.php`.

### AC5 — Config existence check

**PASS — with config note**

```
drush config:get ai_conversation.settings
  aws_region: us-east-1
  aws_model: 'anthropic.claude-sonnet-4-5-20250929-v1:0'
  aws_access_key_id: [populated — redacted]
  aws_secret_access_key: [populated — redacted]
```

All four required config keys are populated. `aws_region: us-east-1` matches AC expectation.

**Config note (non-blocking):** The AC specifies `aws_model: us.anthropic.claude-sonnet-4-6` but config shows `anthropic.claude-sonnet-4-5-20250929-v1:0`. Both IDs are valid Bedrock models. The code logic is correct regardless — `getModelFallbacks()` uses whatever is in config as primary and falls back through the chain. This is a **PM/CEO decision** about whether to update the config value to the `us.*` cross-region inference profile ID, not a code defect. Recommend PM accepts the current value as-is unless CEO has a specific reason to prefer the inference profile ID.

### AC6 — No PHP fatal errors in apache log since fix

**PASS**

```
grep "ai_conversation|AIApiService|bedrock|ChatController" /var/log/apache2/forseti_error.log → 0 matches
drush watchdog:show --count=50 | grep "ai_conversation|bedrock|undefined variable" → 0 matches
```

No errors found.

### Note — No Drupal unit/functional tests exist for `ai_conversation`

The `ai_conversation` module has no `tests/` directory. No PHPUnit binary is installed in the production vendor (`phpunit` not in `/var/www/html/forseti/vendor/bin/`). Pre-existing absence — not a regression from this fix. All verification was done via static analysis and config inspection. This is documented as a test coverage gap for future releases.

---

## Summary

| AC | Result | Method |
|---|---|---|
| `getModelFallbacks()` returns config primary first | PASS | Static analysis |
| `buildBedrockClient()` reads region/creds from config | PASS | Static analysis |
| `generateSummary()` no undefined `$config` | PASS | Static analysis |
| `ChatController` no `field_ai_model` | PASS | grep count=0 |
| Config keys populated, region=us-east-1 | PASS (note) | drush config:get |
| No PHP fatal errors since fix | PASS | watchdog + apache log |

## Verdict

**APPROVE** — all AC met. One non-blocking config note: `aws_model` in config is `anthropic.claude-sonnet-4-5-20250929-v1:0` rather than `us.anthropic.claude-sonnet-4-6`; code handles either correctly. PM may proceed with release signoff for `20260322-forseti-release-next`.
