Now I have enough context to apply the fixes. Let me do both code changes and the audit documentation:

Now I'll apply the fixes. First let me check the dungeoncrawler git repo:

Both copies are identical. I'll fix the repo copy and sync to production:

Good. Now sync to production and verify:

Same inode — production and repo are the same file. Fixes already live. Now commit and write artifacts:

Good. Now commit and write artifacts:

Now commit the artifacts and write the outbox:

Now write the outbox:

---

- Status: done
- Summary: Both tasks complete. Task 1 (symlink verification): stlouisintegration and theoryofconspiracies symlinks confirmed intact, correctly resolving to forseti canonical via two-hop chain. stlouisintegration has `ai_conversation` enabled with no PHP fatal errors. theoryofconspiracies symlink intact but module disabled — no risk. Task 2 (DungeonCrawler audit): applied Bedrock fallback fix to `invokeModelDirect()` and `testConnection()` in dungeoncrawler copy (commit `a4a4e8bf`) — both now read from config with `us.anthropic.claude-sonnet-4-6` fallback. Decision: maintain separately — ChatController has deep product-specific divergence (GM system prompt, `field_ai_model`) making safe symlink infeasible now. One pending item escalated to pm-dungeoncrawler: `ChatController` sets `field_ai_model = anthropic.claude-3-5-sonnet-20240620-v1:0` (EOL model) on new session creation.

## Next actions
- Notify pm-dungeoncrawler: `ChatController.php` `field_ai_model` EOL model value needs a decision (remove, update, or migrate to config)
- QA smoke test: dungeoncrawler AI chat direct-invoke path post-fix

## Blockers
- None.

## ROI estimate
- ROI: 40
- Rationale: DungeonCrawler was using a deprecated EOL Bedrock model in the direct-invoke path; fix prevents a future 404. Closes the P0 loop with documented evidence.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260405-ai-conversation-bedrock-fixes-verify
- Generated: 2026-04-05T17:32:15+00:00
