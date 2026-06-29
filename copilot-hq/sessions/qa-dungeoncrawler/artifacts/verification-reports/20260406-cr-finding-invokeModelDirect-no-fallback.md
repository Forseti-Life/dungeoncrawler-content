# Verification Report: 20260405-cr-finding-invokeModelDirect-no-fallback

- Date: 2026-04-06
- QA seat: qa-dungeoncrawler
- Feature/Item: 20260405-cr-finding-invokeModelDirect-no-fallback
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260405-cr-finding-invokeModelDirect-no-fallback.md
- Dev commit: `d93d222eb`
- Result: **APPROVE**

## Summary

Gate 1c code-review finding closed. Both `invokeModelDirect()` and `testConnection()` in `AIApiService.php` were constructing the AWS SDK client inline (bypassing `buildBedrockClient()` and `getModelFallbacks()`). Dev replaced both inline blocks with `$this->buildBedrockClient()` + `$this->getModelFallbacks()[0]`. All 3 AC checks pass. Live `testConnection()` returns `{"success":true,"model":"us.anthropic.claude-sonnet-4-6"}`.

## AC Verification

| Check | Expected | Result |
|---|---|---|
| `invokeModelDirect()` — no inline `new \Aws\Sdk(...)` | inline construction removed | PASS |
| `invokeModelDirect()` — uses `buildBedrockClient()` | method call present | PASS |
| `invokeModelDirect()` — uses `getModelFallbacks()` | fallback chain applied | PASS |
| `testConnection()` — no inline `new \Aws\Sdk(...)` | inline construction removed | PASS |
| `testConnection()` — uses `buildBedrockClient()` | method call present | PASS |
| `testConnection()` — returns `model` in response | observability field present | PASS |
| Live `testConnection()` | `{"success":true,"model":"us.anthropic.claude-sonnet-4-6"}` | PASS |

## Evidence

### Code inspection (2026-04-06T18:02 UTC)
- `invokeModelDirect has inline new \Aws\Sdk`: NO (correct)
- `invokeModelDirect uses buildBedrockClient`: YES
- `invokeModelDirect uses getModelFallbacks`: YES
- `testConnection has inline new \Aws\Sdk`: NO (correct)
- `testConnection uses buildBedrockClient`: YES
- `testConnection returns model in response`: YES

### Live verification
```
drush php:eval "\Drupal::service('ai_conversation.ai_api_service')->testConnection()"
→ Array ( [success] => 1 [message] => AWS Bedrock connection successful [model] => us.anthropic.claude-sonnet-4-6 )
```

### Site audit 20260406-170141
- 0 failures, 0 permission violations, 0 config drift
- Evidence: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260406-170141/findings-summary.md`

### Dev commit
- `d93d222eb` — `fix(dungeoncrawler): invokeModelDirect() and testConnection() use buildBedrockClient()`
- Confirmed in `git log` of forseti.life repo

## KB references
- None found specific to this item.

## Verdict: APPROVE

Inline SDK construction is fully removed from both methods. Fallback chain now applied consistently across all Bedrock call paths. No regressions detected. No new Dev items identified.
