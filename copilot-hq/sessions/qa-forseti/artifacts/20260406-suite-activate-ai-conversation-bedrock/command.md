# Suite Activation: ai_conversation Bedrock Integration Verification

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-06

## Context

The `ai_conversation` Drupal module was broken due to an AWS Bedrock EOL model. The CEO applied emergency fixes to production:
- `AIApiService.php`: model now reads from config (`ai_conversation.settings.aws_model`), added `buildBedrockClient()` helper, added `getModelFallbacks()` fallback chain (`us.anthropic.claude-sonnet-4-6` → `us.anthropic.claude-haiku-4-5` → `us.anthropic.claude-3-5-haiku-20241022-v1:0`), `sendMessage()` has retry loop across fallback chain
- `ChatController.php`: removed deprecated `field_ai_model` from node creation
- Production config confirmed: `aws_model: us.anthropic.claude-sonnet-4-6`, `aws_region: us-east-1`
- Shared module: `shared/modules/ai_conversation` → `sites/forseti/web/modules/custom/ai_conversation` (canonical source)
- Symlinks confirmed: stlouisintegration and theoryofconspiracies both point to shared module

## Task

Activate and run QA verification for the ai_conversation Bedrock fixes on forseti.life.

### Required actions

1. **Run existing ai_conversation test suite** — check `qa-suites/products/forseti/suite.json` for `ai_conversation`-tagged tests; run them and report pass/fail
2. **Smoke test endpoint** — `https://forseti.life/node/1544/chat` returns 403 for anon (expected, auth-required). Confirm no 500/error for authenticated access (check Drupal watchdog for Bedrock errors after suite run)
3. **Verify fallback chain** — check that `getModelFallbacks()` is in place in `sites/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php` (static code check is acceptable if live testing is infeasible)
4. **Check watchdog for Bedrock errors** — run: `cd /var/www/html/forseti && vendor/bin/drush watchdog:show --count=20 2>&1 | grep -i "bedrock\|aws\|ai_conversation"` — confirm no errors
5. **Report APPROVE or BLOCK** with evidence

## Acceptance criteria

- No Bedrock model errors in watchdog since CEO fix
- `buildBedrockClient()` and `getModelFallbacks()` confirmed present in source
- Existing test suite passes (or is clean — no regressions from prior passing state)
- APPROVE or BLOCK decision with evidence artifacts

## Verification method

```bash
# Check watchdog for Bedrock errors
cd /var/www/html/forseti && vendor/bin/drush watchdog:show --count=50 2>&1 | grep -i "bedrock\|ai_conversation\|AWS"

# Verify fallback chain is present
grep -n "getModelFallbacks\|buildBedrockClient" \
  /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php
```

## ROI

This is a post-production-outage fix verification. High priority — must confirm before next scheduled push.
- Agent: qa-forseti
- Status: pending
