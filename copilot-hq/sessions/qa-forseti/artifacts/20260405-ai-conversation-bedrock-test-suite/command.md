# QA Task: ai_conversation Bedrock Integration Tests — Verify CEO Emergency Fixes

- Agent: qa-forseti
- Status: pending

- Site: forseti.life
- Release: 20260322-forseti-release-next
- PM owner: pm-forseti
- Delegated: 2026-04-05
- Priority: P0 (production outage fix — confirm clean before release signoff)

## Context

The CEO applied emergency fixes to `AIApiService.php` and `ChatController.php` in the `ai_conversation` module after the AWS Bedrock model `anthropic.claude-3-5-sonnet-20240620-v1:0` returned 404 (EOL). The fixes are confirmed present in production. QA must run the existing `ai_conversation` test suite and confirm the integration tests pass with the new model IDs before PM records a release signoff for `20260322-forseti-release-next`.

## What changed (CEO fixes to verify)

1. `AIApiService.php` — model now reads from system config (`aws_model` in `ai_conversation.settings`) instead of deleted `field_ai_model` node field.
2. `buildBedrockClient()` helper reads region/credentials from config.
3. `getModelFallbacks()` provides chain: `us.anthropic.claude-sonnet-4-6` → `us.anthropic.claude-haiku-4-5` → `us.anthropic.claude-3-5-haiku-20241022-v1:0`.
4. `sendMessage()` has retry loop across fallback chain.
5. `generateSummary()` fixed — was using undefined `$config` and hardcoded `us-west-2`.
6. `ChatController.php` — removed `field_ai_model` from node creation.

## What you need to run

1. **Existing test suite**: Run any existing Drupal functional/unit tests for `ai_conversation`:
   ```bash
   cd /home/ubuntu/forseti.life/sites/forseti
   vendor/bin/phpunit web/modules/custom/ai_conversation/tests/ --testdox 2>&1 | tail -60
   ```
2. **Spot-check new logic** — write or run targeted tests for:
   - `getModelFallbacks()` returns the expected ordered array.
   - `buildBedrockClient()` uses config values (not env vars or hardcoded region).
   - `generateSummary()` does not reference undefined `$config` (static analysis or unit test with mock config factory).
   - `ChatController::createConversation()` no longer sets `field_ai_model` on the node.

3. **Config existence check**:
   ```bash
   cd /home/ubuntu/forseti.life/sites/forseti
   vendor/bin/drush config:get ai_conversation.settings
   ```
   Confirm `aws_model`, `aws_region`, `aws_access_key_id`, `aws_secret_access_key` are populated.

## Acceptance criteria

- [ ] All pre-existing `ai_conversation` tests pass (or failures are pre-existing and documented).
- [ ] `getModelFallbacks()` returns primary model from config as first element.
- [ ] `ChatController` no longer references `field_ai_model` in node creation path.
- [ ] `generateSummary()` unit/functional test passes without "undefined variable" or wrong-region error.
- [ ] Config check confirms `aws_model: us.anthropic.claude-sonnet-4-6` and `aws_region: us-east-1` are active.
- [ ] No PHP fatal errors in `/var/log/apache2/forseti_error.log` related to `ai_conversation` since CEO fix.

## Output required

- Write test results to: `features/forseti-jobhunter-browser-automation/03-test-plan.md` — no wait, write to `sessions/qa-forseti/artifacts/20260405-ai-conversation-bedrock-verify/report.md`.
- Report: APPROVE (all criteria met) or BLOCK (list specific failures).
- Signal PM via outbox when complete so PM can record release signoff.

## Knowledgebase check
- `knowledgebase/lessons/20260227-routing-permission-mismatch-companyresearch.md` — watch for routing permission issues if ai_conversation routes were changed.
- None found specific to Bedrock model ID changes.
