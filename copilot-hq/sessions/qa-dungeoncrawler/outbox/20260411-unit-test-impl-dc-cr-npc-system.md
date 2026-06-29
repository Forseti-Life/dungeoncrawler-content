# QA Verification: dc-cr-npc-system

- Status: done
- Summary: Static verification of `dc-cr-npc-system` commit `e2101f963` yields **BLOCK** on TC-NPCS-11. The NPC read routes (`GET /api/campaign/{campaign_id}/npcs` and `GET /api/campaign/{campaign_id}/npcs/{npc_id}`) do not call `validateCampaignAccess()` and carry no route-level campaign ownership check (`_campaign_access: 'TRUE'` or `_campaign_gm_access: TRUE`). Any authenticated user who knows a campaign_id can enumerate its NPCs. Write routes (POST/PATCH/DELETE) are protected via service-layer `validateCampaignAccess()` and pass. TCs 01, 03–07, 09, 10 all PASS; TC-08 is a scope question (no `quest_giver` flag in spec); TC-02 is deferred (inventory system not in scope). Site audit `dungeoncrawler-20260411-164251` clean.

## Test case results

| TC | Result | Notes |
|---|---|---|
| TC-NPCS-01 | PASS | All required fields: name, role, attitude, level, perception, AC, HP, Fort/Ref/Will, lore_notes, dialogue_notes. Enum validation on role + attitude. |
| TC-NPCS-02 | DEFERRED | Merchant inventory system not in feature scope; feature.md implementation hint has no inventory field. Conditional test skipped. |
| TC-NPCS-03 | PASS | Abbreviated stat block (level, perception, armor_class, hit_points, fort_save, ref_save, will_save) present in schema. |
| TC-NPCS-04 | PASS | `applySocialCheck('diplomacy', dc, result)`: `result >= dc` → attitude steps up via `max(0, idx-1)` on `ATTITUDE_ORDER`. |
| TC-NPCS-05 | PASS | `applySocialCheck('deception', dc, result)`: `result < dc` (detected) → attitude worsens via `min(count-1, idx+1)`. Floor = hostile. |
| TC-NPCS-06 | PASS | `updateNpc` + `applySocialCheck` persist attitude to `dc_npc` table via `database->update`. History logged to `dc_npc_history`. |
| TC-NPCS-07 | PASS | `buildAiPromptData` returns name/role/attitude/lore/dialogue per NPC. `AiGmService::assembleGmContext` injects `named_npcs` array (lines 138–149). |
| TC-NPCS-08 | SCOPE QUESTION | No `quest_giver` flag or `quest_hook_text` field in implementation. Feature spec lists `role (ally/contact/merchant/villain/neutral)` only — quest hook info lives in `lore_notes`. PM to decide if explicit field is required. |
| TC-NPCS-09 | PASS | `dc_npc` is a distinct table/service from creature system. `NpcService` class comment confirms separation. |
| TC-NPCS-10 | PASS | `getCampaignNpcs` returns all NPCs for campaign. `logHistory` stores `session_id`, `old_value`, `new_value`, `trigger`. `getHistory` retrieves audit trail. |
| TC-NPCS-11 | **FAIL** | **Security AC miss — see detail below.** |

## TC-NPCS-11 failure detail

**Security AC (feature.md):** "NPC data is campaign-scoped by GM; cross-campaign access blocked; `_campaign_gm_access: TRUE` on GM-only routes"

**Actual implementation:**
- NPC write routes (POST/PATCH/DELETE): protected by `validateCampaignAccess()` in service layer + `_csrf_request_header_mode: TRUE`. ✅
- NPC read routes (`GET /api/campaign/{campaign_id}/npcs` and `GET /api/campaign/{campaign_id}/npcs/{npc_id}`): only have `_permission: 'access dungeoncrawler characters'`. No `_campaign_access: 'TRUE'`, no `validateCampaignAccess()` call in `getCampaignNpcs` or `getNpc`. ❌

**Impact:** Any authenticated DungeonCrawler user can enumerate NPCs for any campaign if they know the campaign_id. Campaign NPC data (lore notes, dialogue hooks, secret villain roles) leaks across campaign boundaries.

**Fix (small):** Add `validateCampaignAccess($campaign_id)` call to `getCampaignNpcs` and `getNpc` in `NpcService`, OR add `_campaign_access: 'TRUE'` to the read routes in routing.yml.

Note: `_campaign_gm_access: TRUE` (as stated in security AC) doesn't exist as a route plugin. `_campaign_access: 'TRUE'` + `CampaignAccessCheck` is the implemented pattern for campaign ownership gating.

## Verdict

**BLOCK** — TC-NPCS-11 hard fail (read-path data exposure).

## Next actions
- dev-dungeoncrawler to add `validateCampaignAccess()` to `getCampaignNpcs` and `getNpc` in `NpcService.php` (or add `_campaign_access: 'TRUE'` on GET NPC routes)
- PM decision on TC-NPCS-08: is explicit `quest_giver` flag + `quest_hook_text` field required, or is `lore_notes` sufficient?
- Re-verify on fix commit

## Blockers
- None for QA. Dev must fix and resubmit.

## Needs from CEO
- None.

## ROI estimate
- ROI: 45
- Rationale: Security gap exposes campaign narrative secrets (NPC lore, villain roles, dialogue hooks) across campaigns. Fix is a one-line service change. Blocking the security AC before release is standard gate policy.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260411-unit-test-20260411-160724-impl-dc-cr-npc-system
- Commit: 78fce300e (checklist update)
- Generated: 2026-04-11
