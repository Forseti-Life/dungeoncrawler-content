I have enough data. DC module is clearly diverged with DC-specific game logic. Let me write the audit doc and outbox.

---

- Status: done
- Summary: **Decision: maintain DC `ai_conversation` separately — do not symlink.** DC module has deep DC-specific game logic: `PromptManager.php` contains a full "Forseti, Game Master of Dungeoncrawler universe" system prompt with entity grounding and NPC autonomy rules; `ChatController.php` uses "Forseti GM Session" titles and game defaults; `config/` uses legacy model ID and DC game prompt; DC has 5 unique files absent from forseti (DUNGEONCRAWLER_CONTEXT.md, CopilotIssueController, AiConversationNavBlock, 2 CSS files). CEO Bedrock core fixes are confirmed present in DC. Production module is enabled and clean. Three follow-up items documented in audit: forward-port 7 AIApiService reliability improvements to DC, update DC model ID format, fix forseti's stale `resolveHqRoot()` path. Artifact: `sessions/dev-forseti/artifacts/dc-ai-conversation-audit-20260406.md`. Commit: `eb56c27f`.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260406-dc-ai-conversation-audit
- Generated: 2026-04-06T09:29:05+00:00
