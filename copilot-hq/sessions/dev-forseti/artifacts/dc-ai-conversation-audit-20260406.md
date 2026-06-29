# DC ai_conversation Module Audit
**Date:** 2026-04-06  
**Auditor:** dev-forseti  
**Task:** `20260406-dc-ai-conversation-audit`

---

## Decision: Maintain DC module separately (do NOT symlink)

**Rationale:** The DC `ai_conversation` module has deep, pervasive DC-specific game logic throughout PromptManager, ChatController, config schema, and extra controllers. It is not a copy of the forseti module with minor overrides — it is a purpose-built game assistant module. Symlink would require the shared module to handle two fully different AI personas and config schemas.

---

## Audit Findings

### 1. PromptManager.php — DIVERGED (DC-specific game GM persona)

DC `PromptManager::getBaseSystemPrompt()` returns a 100+ line "Forseti, Game Master of Dungeoncrawler universe" system prompt including:
- GM identity rules (entity grounding, NPC autonomy doctrine, no invented characters)
- DungeonCrawler domain focus (campaign flow, encounter pacing, NPC intent)
- Suggestion flow categories: `safety_feature`, `content_update`, `community_initiative`, `partnership`, etc.

Forseti `PromptManager::getBaseSystemPrompt()` returns the forseti.life community platform prompt (>200 lines covering community philosophy, services like Job Hunter, membership process, etc.)

**These are entirely different AI personas. Cannot be unified without config-driven abstraction.**

### 2. ChatController.php — DIVERGED (DC-specific conversation defaults)

| Area | DC value | Forseti value |
|---|---|---|
| Conversation title | `'Forseti GM Session - ' . date(...)` | `'AI Chat - ' . date(...)` |
| Default system prompt | GM-specific DC prompt | Consulting company placeholder |
| Hardcoded model in `startChat()` | `anthropic.claude-3-5-sonnet-20240620-v1:0` | No hardcoded model |
| Access permission | `node.view` only | `node.view` + `administer ai conversation` |
| Comment on method | "Start Game Master chat" | "Start AI Chat" |

### 3. config/install/ai_conversation.settings.yml — DIVERGED

| Setting | DC value | Forseti value |
|---|---|---|
| `aws_model` | `anthropic.claude-3-5-sonnet-20240620-v1:0` (legacy format) | `us.anthropic.claude-sonnet-4-6` (current) |
| `system_prompt` | Full Dungeoncrawler GM prompt (30 lines) | Forseti community platform prompt (64 lines) |
| Suggestion categories | Game-focused (`safety_feature`, `content_update`, `community_initiative`) | Community-focused (`automation_service`, `member_tool`, `community_initiative`) |

**Note:** DC's model ID uses the legacy format without the `us.` regional prefix. This should be updated to `us.anthropic.claude-3-5-sonnet-20240620-v1:0` or migrated to the current Sonnet model.

### 4. AIApiService.php — DC IS BEHIND FORSETI (Bedrock core fixes present, but missing later improvements)

CEO Bedrock core fixes **confirmed present in DC**:
- `buildBedrockClient()` method ✓
- `getModelFallbacks()` method ✓
- Fallback loop structure ✓

**DC is missing these forseti improvements (applied after CEO Bedrock fix):**
| Issue | DC (current) | Forseti (current) |
|---|---|---|
| `prompt_preview` field length | 490 chars | 250 chars |
| `response_preview` field length | 490 chars | 250 chars |
| Schema field guard before cache lookup | Missing | Present (guards against missing `response_preview`/`success` columns) |
| Credentials check before SDK init | Missing (SDK instantiated even if creds empty) | Present (early return with `'AWS credentials not configured'`) |
| Bedrock fallback attempt log | Missing `logInfo()` call | Present |
| Summary fallback error log | Missing `logError()` call | Present |
| `buildBedrockClient()` HTTP timeouts | Missing `http.timeout`/`connect_timeout` | Present (15s / 10s) |

**Recommended follow-up (not in scope of this audit):** Forward-port these 7 improvements from forseti AIApiService to DC AIApiService. This is a low-risk mechanical sync — the improvements are safety/reliability fixes, not persona changes.

### 5. File inventory differences

**Files only in DC (not in forseti):**
- `DUNGEONCRAWLER_CONTEXT.md` — PF2E game platform context document (Pathfinder 2E rules assistant persona)
- `css/ai-model-pricing.css` — Styling for model pricing admin page
- `css/genai-debug.css` — Styling for GenAI debug admin page
- `src/Controller/CopilotIssueController.php` — DC-specific copilot issue controller
- `src/Plugin/Block/AiConversationNavBlock.php` — DC-specific navigation block plugin

**Files only in forseti (not in DC):**
- `src/Controller/SuggestionTrackingController.php` — Forseti suggestion tracking controller

### 6. DUNGEONCRAWLER_CONTEXT.md

This file (in DC module root) is a detailed PF2E game assistant context document describing:
- Dungeon Crawler Life platform (Pathfinder 2E tactical dungeon crawler)
- Hex map system, character management, combat mechanics
- Technical architecture (PixiJS, Drupal 11.2+, AWS Bedrock)
- Feedback/suggestion categories for the game platform

This is the correct DC-specific documentation and should remain in the DC module.

### 7. Side finding: forseti AIApiService has stale HQ path

`sites/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php` contains `resolveHqRoot()` with hardcoded path `/home/keithaumiller/copilot-sessions-hq`. This is **not in DC** (DC lacks this method entirely). This is a forseti-scope issue outside this audit; logging as follow-up item.

---

## Production module status

```
DC production path: /var/www/html/dungeoncrawler
Module: ai_conversation — Enabled (confirmed)
Recent watchdog: No bedrock or ai_conversation errors in last 20 entries (clean)
```

DC module loads correctly in production.

---

## Recommended follow-up items (post-audit, prioritized)

1. **[DC — medium]** Forward-port 7 AIApiService improvements from forseti to DC (schema guard, credentials check, HTTP timeouts, preview length, logging improvements)
2. **[DC — low]** Update DC config `aws_model` from legacy format to `us.anthropic.claude-3-5-sonnet-20240620-v1:0` or current Sonnet model
3. **[forseti — low]** Fix `resolveHqRoot()` stale path in forseti's AIApiService.php (`/home/keithaumiller/copilot-sessions-hq` → `/home/ubuntu/forseti.life/copilot-hq`)

---

## Verification commands

```bash
# Confirm DC module loaded, no errors
cd /var/www/html/dungeoncrawler && vendor/bin/drush pm:list | grep ai_conversation
# → AI Conversation (ai_conversation) ... Enabled

# Confirm no keithaumiller paths in DC module
grep -r 'keithaumiller' /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/ai_conversation/
# → no output expected

# Check DC ChatController still has GM session title
grep 'GM Session\|Game Master' /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/ai_conversation/src/Controller/ChatController.php
# → Forseti GM Session lines

# Confirm DUNGEONCRAWLER_CONTEXT.md present
ls /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/ai_conversation/DUNGEONCRAWLER_CONTEXT.md
```
