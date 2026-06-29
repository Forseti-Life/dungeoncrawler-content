# Dev Task: DungeonCrawler ai_conversation Module Audit

**From:** pm-forseti  
**To:** dev-forseti  
**Date:** 2026-04-06

## Context

The CEO applied emergency Bedrock fixes to both the forseti and dungeoncrawler `ai_conversation` modules. The canonical source is now `sites/forseti/web/modules/custom/ai_conversation/` and a shared symlink exists at `shared/modules/ai_conversation`.

The DC module at `sites/dungeoncrawler/web/modules/custom/ai_conversation/` has already received the CEO's Bedrock fixes (confirmed: `buildBedrockClient()` and `getModelFallbacks()` are present in DC's `AIApiService.php`). However, DC has NOT been symlinked to the shared module — it remains a standalone copy.

**Unique artifacts in DC module not found in forseti:**
- `sites/dungeoncrawler/web/modules/custom/ai_conversation/DUNGEONCRAWLER_CONTEXT.md`

**Known DC-specific code concern:**
The `buildOptimizedContext()` in DC's `AIApiService.php` calls `promptManager->getBaseSystemPrompt()` — which should return a DC-specific system prompt. Verify whether this prompt is site-specific or generic.

## Tasks

### 1. Audit DC ai_conversation for DC-specific logic

Compare `sites/dungeoncrawler/web/modules/custom/ai_conversation/` with canonical `sites/forseti/web/modules/custom/ai_conversation/`. Identify:
- Any DC-specific system prompt, context-building logic, or game/character references
- Any config schema differences (`config/install/ai_conversation.settings.yml`)
- Whether `PromptManager.php` has DC-specific behavior
- Whether `ChatController.php` has DC-specific logic

### 2. Decision: symlink or maintain separately

Based on your audit:
- If DC module is functionally identical to forseti canonical: create symlink (`/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/ai_conversation` → `/home/ubuntu/forseti.life/shared/modules/ai_conversation`), move any DC-specific docs to `DUNGEONCRAWLER_CONTEXT.md` inside the shared module
- If DC module has diverged DC-specific logic (e.g., game character context, campaign system prompts): document what's unique in `DUNGEONCRAWLER_CONTEXT.md`, propose a config-driven abstraction for the DC-specific parts, and maintain as separate until refactored

### 3. Verify DC production module loads correctly

```bash
# Check DC module is enabled and no PHP errors
cd /var/www/html/dungeoncrawler && vendor/bin/drush pm:list 2>&1 | grep ai_conversation
cd /var/www/html/dungeoncrawler && vendor/bin/drush watchdog:show --count=20 2>&1 | grep -i "bedrock\|ai_conversation"
```

## Acceptance criteria

- Audit document written to `sessions/dev-forseti/artifacts/dc-ai-conversation-audit-20260406.md` with:
  - List of DC-specific vs shared code
  - Decision: symlink or maintain separately
  - If symlink: confirm symlink created and DC site loads correctly
  - If separate: document the DC-unique logic and a refactor proposal
- Commit any code changes with hash

## Verification method

```bash
# Compare the two modules' PHP files
diff /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/ai_conversation/src/Service/AIApiService.php \
     /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php | head -60

diff /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/ai_conversation/src/Controller/ChatController.php \
     /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/src/Controller/ChatController.php | head -60
```

## ROI

Medium — the symlink decision prevents future drift between sites. DC module already has CEO Bedrock fixes so production is not broken; this is maintenance/cleanup.
