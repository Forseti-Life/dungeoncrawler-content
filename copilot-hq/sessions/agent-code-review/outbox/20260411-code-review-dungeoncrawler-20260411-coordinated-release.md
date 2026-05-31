# Code Review: dungeoncrawler 20260411-coordinated-release

- Status: blocked
- Summary: Pre-ship code review for dungeoncrawler `20260411-coordinated-release` is BLOCK. Product: DungeonCrawler (dungeoncrawler site, `dungeoncrawler_content` module). Release: `20260411-coordinated-release`. Feature at issue: `dc-cr-npc-system`. Finding: HIGH severity authz bypass in `NpcService` — `getCampaignNpcs()` (line 122) and `getNpc()` (line 104) both filter by `campaign_id` but never call `validateCampaignAccess($campaign_id)`, allowing any authenticated user with `access dungeoncrawler characters` to enumerate and read NPCs from campaigns they do not own. All write paths (create/update/delete) correctly call `validateCampaignAccess()`; only the read paths are missing the guard. This matches QA TC-NPCS-11 (BLOCK filed 16:43, commit `78fce300e8`). Fix dispatched to `dev-dungeoncrawler` inbox (`sessions/dev-dungeoncrawler/inbox/20260411-fix-npc-read-authz-coordinated-release/`); fix not yet committed.

## Verdict: BLOCK

**Product:** DungeonCrawler
**Module:** `dungeoncrawler_content` — `NpcService`
**Release:** `20260411-coordinated-release`
**Feature:** `dc-cr-npc-system`
**Base commit:** `b90bbebdbccd` — dungeoncrawler release-e Gate 2 APPROVE (2026-04-11T02:15:52)

**Commits in scope touching sites/dungeoncrawler/:**
- `9b3bfcb113` (16:15) — feat: dc-cr-gm-narrative-engine
- `39fa78d496` (16:23) — feat: dc-cr-multiclass-archetype
- `e2101f9633` (16:33) — feat: dc-cr-npc-system
- `cee0516283` (16:42) — fix: rate-limiting + multi-session summaries (QA BLOCK resolution for GNE-12 + GNE-02)

## Checklist

| Check | Result | Notes |
|---|---|---|
| New POST routes with CSRF token | PASS | All NPC write routes (POST/PATCH/DELETE) use `_csrf_request_header_mode: TRUE` |
| Authz bypass on new controllers | BLOCK | `getCampaignNpcs` and `getNpc` GET routes missing campaign ownership guard — see Finding 1 |
| Schema hook pairing (hook_schema + hook_update_N) | PASS | dc_npc + dc_npc_history created in `update_10042`; consistent with module pattern |
| Stale private duplicates of canonical data | PASS | No stale duplicates found |
| Hardcoded absolute paths | PASS | No hardcoded paths in new service files |
| JS fetch/XHR CSRF token in URL (not POST body) | N/A | No new Twig templates introduced |

## Findings

### Finding 1 — HIGH: NPC read endpoints bypass campaign ownership guard

**Product:** DungeonCrawler
**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/NpcService.php`
**Lines:** `getNpc()` (line 104), `getCampaignNpcs()` (line 122)

**Problem:** Both methods query `dc_npc` filtered by `campaign_id` only, without calling `validateCampaignAccess()`. Any authenticated user with `access dungeoncrawler characters` can:
- Enumerate all NPCs in any campaign: GET `/api/campaign/{any_id}/npcs`
- Read individual NPCs from any campaign: GET `/api/campaign/{any_id}/npcs/{npc_id}`

**Note:** QA flagged this as TC-NPCS-11 (BLOCK filed 16:43:51, commit `78fce300e8`). No fix commit exists yet.

**Fix (2 lines):**
```php
// Add at top of getCampaignNpcs() and getNpc():
$this->validateCampaignAccess($campaign_id);
```
Pattern already established by `createNpc()`, `updateNpc()`, and `deleteNpc()`.

## Next actions
- Await dev-dungeoncrawler fix commit (inbox: `sessions/dev-dungeoncrawler/inbox/20260411-fix-npc-read-authz-coordinated-release/`)
- After fix commit: CEO dispatches re-review inbox item to agent-code-review

## Blockers
- HIGH authz finding unfixed: `validateCampaignAccess()` still missing from `getCampaignNpcs()` and `getNpc()` — verified on live file at re-review; dev-dungeoncrawler inbox item status: pending

## Needs from CEO
- N/A (fix dispatched; blocked on dev-dungeoncrawler execution)

## Decision needed
- Should dc-cr-npc-system be (A) held in this release pending the 2-line authz fix, or (B) pulled from scope and deferred to the next dungeoncrawler release cycle?

## Recommendation
- **Recommend Option A: hold and fix in this cycle.** The fix is a 2-line change with a clear established pattern (`validateCampaignAccess()` already used in all write paths). Fix is already dispatched and should be fast to apply. Pulling the feature (Option B) would also break the GM narrative engine's NPC context integration (`AiGmService::buildAiPromptData()` calls `getCampaignNpcs()`) — pulling dc-cr-npc-system would cascade into needing to pull dc-cr-gm-narrative-engine as well, losing two features. Risk of shipping unfixed is a real campaign-data exposure; the fix path is clear and low-effort.

## ROI estimate
- ROI: 18
- Rationale: Unresolved campaign ownership bypass on NPC read endpoints is a real authz vulnerability that would expose user campaign data in production. Blocking the release protects active user campaigns from unauthorized enumeration, and the fix is trivial once dev-dungeoncrawler executes.
