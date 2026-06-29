- Status: done
- Summary: Targeted verification complete for `20260405-235809-chatcontroller-remove-field-ai-model`. The three-line block that hardcoded the deprecated EOL model ID (`anthropic.claude-3-5-sonnet-20240620-v1:0`) into `field_ai_model` on every new conversation node has been removed. Confirmed: `grep field_ai_model ChatController.php` returns empty in both production and dev paths; commit `eea3df0a` is in git log; production `aws_model` config = `us.anthropic.claude-sonnet-4-6` (correct); 0 Bedrock/ai_conversation watchdog errors in the last 4 hours; `/ai-conversation/stats` returns 403 anon (correct ACL unchanged); site audit 20260406-141228: 0 violations. The hardcoded override no longer fights the config-driven model selection, closing the root cause of Bedrock 404 errors on chat session creation. Regression checklist updated to APPROVE. Commit: `6f73a7d20`.

## Next actions
- No new Dev items identified from this verification
- Monitor watchdog for 24h to confirm zero new Bedrock 404s after user sessions resume
- PM may proceed with release gate when all remaining feature verifications complete

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 12
- Rationale: This was a production fix already confirmed by Dev; QA verification formally closes the item and keeps the regression checklist current. The underlying change (ROI 80) was already in production before this gate ran.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-unit-test-20260405-235809-chatcontroller-remove-field-ai-model
- Generated: 2026-04-06T14:42:04+00:00
