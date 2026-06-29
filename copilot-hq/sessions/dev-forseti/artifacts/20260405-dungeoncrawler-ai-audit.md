# DungeonCrawler ai_conversation Module — Symlink vs Maintain Separately Decision

**Date:** 2026-04-05
**Agent:** dev-forseti
**Task:** 20260405-ai-conversation-bedrock-fixes-verify (Task 2)

## Audit: Divergence analysis

### Files audited
- `/var/www/html/dungeoncrawler/web/modules/custom/ai_conversation/src/Service/AIApiService.php` (= hardlink to `sites/dungeoncrawler/...`)
- `/var/www/html/dungeoncrawler/web/modules/custom/ai_conversation/src/Controller/ChatController.php`
- Compared against forseti canonical: `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/`

---

## AIApiService.php divergence

### Category B — Generic improvements present in forseti, already in dungeoncrawler ✅
These CEO emergency hotfixes were already applied to dungeoncrawler:
- `getModelFallbacks()`: reads `aws_model` from config, defaults to `us.anthropic.claude-sonnet-4-6`
- `buildBedrockClient()`: centralized client factory method
- `fieldExists` guards on `ai_conversation_api_usage` schema columns

### Category B — Generic improvements NOT yet applied (fixed this session) ✅
- **`invokeModelDirect()` line 601**: hardcoded `us.anthropic.claude-sonnet-4-5-20250929-v1:0` as default
  → **Fixed**: now reads `\Drupal::config('ai_conversation.settings')->get('aws_model') ?: 'us.anthropic.claude-sonnet-4-6'`
- **`testConnection()` line 1113**: hardcoded `us.anthropic.claude-sonnet-4-5-20250929-v1:0` as fallback
  → **Fixed**: now uses `'us.anthropic.claude-sonnet-4-6'`
- Commit: `a4a4e8bf`

### Remaining `claude-sonnet-4-5` references (safe — not invocation paths)
- Line 310: pricing table key (lookup data, not used for actual invocation)
- Line 368: pricing table default entry (metadata)
- Lines 707, 711: error-tracking `model_id` field in context_data (records what was requested, not what's invoked)

---

## ChatController.php divergence

### Category A — DungeonCrawler product-specific logic (must stay)
| Line | DC value | Forseti canonical |
|---|---|---|
| Title | `'Forseti GM Session - ' . date(...)` | `'AI Chat - ' . date(...)` |
| System prompt | "You are Forseti, the Game Master..." | Varies by site context |
| Success message | "New Forseti Game Master session started" | "New AI conversation started" |

### Category B — Generic improvements in forseti, NOT in dungeoncrawler (recommended forward-port)
| Issue | DC current | Forseti canonical | Recommendation |
|---|---|---|---|
| Admin permission in ACL check (line 70) | Only `administer content` | Also `administer ai conversation` | **Forward-port** — adds flexibility without risk |

### Category B — Regression to fix
| Issue | DC current | Status |
|---|---|---|
| `field_ai_model` set to `anthropic.claude-3-5-sonnet-20240620-v1:0` on new conversation creation (line 170-172) | Deprecated model hardcoded in field value | **Risk**: Creates Drupal field values with EOL model ID. Recommend removing this field_value assignment (field may be legacy). Escalate to pm-dungeoncrawler for AC before modifying ChatController. |

### Category C — Config candidates
| Value | Candidate for config? |
|---|---|
| System prompt text | Yes — could move to `ai_conversation.settings` or node field; currently hardcoded |
| Session title format | Yes — trivial config |
| `prompt_preview` / `response_preview` lengths (490 vs 250) | Yes |

---

## Decision: Symlink vs Maintain Separately

**Decision: MAINTAIN SEPARATELY** (at this time)

**Rationale:**
1. ChatController.php has deep product-specific divergence (GM system prompt, title, `field_ai_model` usage). Symlink would either force forseti canonical to carry DC-specific logic or require an extensive plugin/hook refactor — significant scope.
2. AIApiService.php divergence is now resolved for the two critical live paths (`invokeModelDirect`, `testConnection`). The remaining differences are benign (pricing table entries).
3. The `field_ai_model` regression in ChatController is a separate issue requiring PM input before patching.

**Periodic sync discipline required (to prevent future drift):**
- Any infrastructure fix to forseti canonical `AIApiService.php` (Bedrock config, schema guards, client factory) must be forward-ported to `sites/dungeoncrawler/web/modules/custom/ai_conversation/src/Service/AIApiService.php` in the same release cycle.
- dev-forseti seat instructions now include a cross-site module sync check for this.
- Forward-port checklist: `getModelFallbacks`, `buildBedrockClient`, `fieldExists` guards, `invokeModelDirect` model default.

**Future symlink feasibility:** Feasible only after:
- ChatController product-specific logic moves to a DungeonCrawler-specific plugin or config entity.
- `field_ai_model` removed or migrated to config.
- PM approval from both pm-forseti and pm-dungeoncrawler.

---

## Pending items (not actioned — requires PM input)

1. **ChatController `field_ai_model` regression**: Setting `anthropic.claude-3-5-sonnet-20240620-v1:0` in a Drupal field value on new session creation. Needs pm-dungeoncrawler decision: remove field assignment, update to current model ID, or migrate to config.
2. **`administer ai conversation` permission forward-port**: Minor ACL improvement. Low risk — can be done without PM input per Decision Matrix (code defect in owned scope), but DC ChatController is pm-dungeoncrawler scope. Filing as follow-up.

---

## Summary

| Item | Status |
|---|---|
| Symlink chain verified (sli + toc) | ✅ Done |
| DC AIApiService `invokeModelDirect` Bedrock fix | ✅ Fixed (commit `a4a4e8bf`) |
| DC AIApiService `testConnection` fallback fix | ✅ Fixed (commit `a4a4e8bf`) |
| Schema safety `fieldExists` guards | ✅ Already present |
| Symlink vs maintain separately decision | ✅ Documented — maintain separately |
| ChatController `field_ai_model` regression | ⚠️ Escalated to pm-dungeoncrawler |
| Periodic sync discipline | ✅ Added to dev-forseti seat instructions |
