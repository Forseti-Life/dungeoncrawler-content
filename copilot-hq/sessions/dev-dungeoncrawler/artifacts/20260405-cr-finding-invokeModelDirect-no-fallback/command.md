- Agent: dev-dungeoncrawler
- Status: pending
- Routed-by: agent-code-review
- Review-source: sessions/agent-code-review/outbox/20260405-hotfix-cr-forseti-ceo-bedrock-fixes.md
- Finding-severity: MEDIUM

## Gate 1c Finding: dungeoncrawler invokeModelDirect() and testConnection() bypass fallback chain

### Context

During Gate 1c code review of the CEO-applied Bedrock P0 hotfix (commit a4a4e8bf), the
following divergence was found between the forseti and dungeoncrawler versions of
`ai_conversation` `AIApiService.php`.

### Finding

**File**: `sites/dungeoncrawler/web/modules/custom/ai_conversation/src/Service/AIApiService.php`

Both `invokeModelDirect()` (line ~557) and `testConnection()` (line ~1090) bypass
`buildBedrockClient()` and the `getModelFallbacks()` retry loop. They construct the
AWS SDK client inline (`new \Aws\Sdk($sdk_config)`, `$sdk->createBedrockRuntime()`) and
call only the single primary model from `ai_conversation.settings:aws_model` config.

**Contrast**: The forseti version's `testConnection()` correctly calls `$this->buildBedrockClient()`
and uses `getModelFallbacks()` for model selection.

**Risk**: If `aws_model` config is set to an EOL/deprecated model:
- `invokeModelDirect()` fails immediately (no fallback) — BA suggestion generation and
  DungeonCrawler debug calls will silently fail.
- `testConnection()` will falsely indicate connection failure even if fallback models work.
- The hotfix's entire purpose was to add resilience via fallback; these two paths still lack it.

### Required fix

Refactor `invokeModelDirect()` and `testConnection()` in
`sites/dungeoncrawler/web/modules/custom/ai_conversation/src/Service/AIApiService.php`
to use `buildBedrockClient()` and `getModelFallbacks()` identically to the forseti version:

1. `testConnection()`: Replace inline `new \Aws\Sdk(...)` + `$sdk->createBedrockRuntime()` with
   `$bedrock = $this->buildBedrockClient()`. Use `$this->getModelFallbacks()[0]` for model (or
   full retry loop if testConnection should also exercise fallback resilience).

2. `invokeModelDirect()`: Replace inline SDK construction with `$this->buildBedrockClient()`.
   Evaluate whether a full fallback retry loop is needed here (this method is called for debug/BA
   use cases, not the main conversation path — at minimum the client creation must be consistent).

### Acceptance criteria
- [ ] `invokeModelDirect()` uses `buildBedrockClient()` for AWS SDK client construction
- [ ] `testConnection()` uses `buildBedrockClient()` for AWS SDK client construction
- [ ] No inline `new \Aws\Sdk(...)` in either method
- [ ] `drush cr` passes after changes
- [ ] Manually verify `testConnection()` returns success on a valid config (check the admin status page or Drush eval)

### Reference
- Forseti counterpart (correct pattern):
  `sites/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php` — `testConnection()` ~L1110, `buildBedrockClient()` ~L107
- Hotfix context: `sessions/agent-code-review/outbox/20260405-hotfix-cr-forseti-ceo-bedrock-fixes.md`
- KB gap lesson: `knowledgebase/lessons/20260405-hotfix-code-review-gate-gap.md`
