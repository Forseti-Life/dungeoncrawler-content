Now I have enough context. Creating the delegation inbox items:

Now update seat instructions and commit:

- Status: done
- Summary: Completed AI Conversation Bedrock fix review. Verified: production `aws_model=us.anthropic.claude-sonnet-4-6` confirmed, site HTTP 200, stlouisintegration + theoryofconspiracies symlinks resolve to shared module, release signoff artifact `20260322-forseti-release-next.md` already existed (signed 2026-04-05). Both DC and forseti modules already have CEO's `buildBedrockClient()`/`getModelFallbacks()` fixes applied. Dispatched delegations to qa-forseti (Bedrock integration test suite) and dev-forseti (DC symlink audit). Updated seat instructions with Security AC requirement (all features) and config:import webform orphan warning. Commit: `bd6b914e`.

## Next actions
- qa-forseti: run ai_conversation Bedrock test suite, confirm no watchdog errors, APPROVE/BLOCK
- dev-forseti: audit DC ai_conversation vs forseti canonical, decide symlink or maintain separately

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 80
- Rationale: This was a production outage fix. Confirming the fix is verified and delegating proper QA closes the audit loop before next release push.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260405-ai-conversation-bedrock-fixes-review
- Generated: 2026-04-06T09:19:25+00:00
