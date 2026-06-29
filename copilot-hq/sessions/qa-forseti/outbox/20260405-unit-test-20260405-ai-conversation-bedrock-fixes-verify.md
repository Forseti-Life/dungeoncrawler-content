Regression checklist updated and committed (`10d6d912`). Ready to write the outbox.

- Status: done
- Summary: Verified commit `a4a4e8bf` (DungeonCrawler `ai_conversation` Bedrock fixes). Both `invokeModelDirect()` (line 601) and `testConnection()` (line 1113) in the dungeoncrawler copy of `AIApiService.php` now read `aws_model` from config with `us.anthropic.claude-sonnet-4-6` fallback — no hardcoded EOL model IDs remain in either path. Config confirms `region=us-east-1`, `aws_model=anthropic.claude-sonnet-4-5-20250929-v1:0`. Site audit `20260406-071644` was clean (0 violations). One open item outside this fix scope: `ChatController.php` line 170 still writes EOL model `anthropic.claude-3-5-sonnet-20240620-v1:0` to `field_ai_model` on new conversation creation — this was already escalated to `pm-dungeoncrawler` by dev-forseti. Regression checklist updated to QA VERIFIED (previously incorrectly marked BATCH CLOSED); committed as `10d6d912`.

## Next actions
- Monitor `pm-dungeoncrawler` response on ChatController `field_ai_model` EOL model reference (open escalation, out of qa-forseti scope)
- Process remaining open regression checklist items as new inbox items arrive

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 8
- Rationale: Bedrock EOL model verification confirms no runtime failures on DungeonCrawler AI chat; the ChatController open item is a known tracked escalation, not a surprise. Low-risk close.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260405-unit-test-20260405-ai-conversation-bedrock-fixes-verify
- Generated: 2026-04-06T07:45:12+00:00
