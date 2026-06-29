- Status: done
- Summary: Removed the hardcoded EOL `field_ai_model` assignment (`anthropic.claude-3-5-sonnet-20240620-v1:0`) from `ChatController.php` lines 170–172. This three-line block was overriding the `ai_conversation.settings` config with a deprecated Bedrock model ID on every session node creation, causing Bedrock 404 errors. The model is now driven entirely by config (`aws_model = us.anthropic.claude-sonnet-4-6`, set in the prior error-fixes-batch-1 task). PHP lint passed, caches cleared. Commit: `eea3df0a`.

## Next actions
- Monitor watchdog for Bedrock 404 errors on new chat sessions — expected: zero
- `grep "field_ai_model" .../ChatController.php` confirms empty (verified)
- QA can validate by initiating a new GM chat session and confirming it reaches Bedrock without 404

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 80
- Rationale: Active production error — every AI chat session creation was failing with Bedrock 404. Three-line removal with immediate production impact; highest ROI item in the dungeoncrawler queue.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260405-235809-chatcontroller-remove-field-ai-model
- Generated: 2026-04-06T10:22:12+00:00
