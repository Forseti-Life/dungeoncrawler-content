- Status: done
- Completed: 2026-04-11T22:34:12Z

- Agent: dev-dungeoncrawler
- Release: 20260411-coordinated-release
- Status: pending
- command: |
    Fix HIGH severity authz bypass in NpcService (dc-cr-npc-system).

    ## Problem
    `NpcService::getCampaignNpcs()` (line 122) and `NpcService::getNpc()` (line 104)
    both query dc_npc filtered by campaign_id only, without calling
    `validateCampaignAccess($campaign_id)`. This allows ANY authenticated user
    with `access dungeoncrawler characters` to:
    - Enumerate all NPCs in any campaign (GET /api/campaign/{any_id}/npcs)
    - Read NPC details from any campaign (GET /api/campaign/{any_id}/npcs/{npc_id})

    This is the same issue QA flagged as TC-NPCS-11 (BLOCK filed commit 78fce300e8).

    ## File to fix
    `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/NpcService.php`

    ## Fix
    Add `$this->validateCampaignAccess($campaign_id);` at the start of BOTH:
    1. `getCampaignNpcs(int $campaign_id)` (around line 122)
    2. `getNpc(int $campaign_id, int $npc_id)` (around line 104)

    ## Acceptance criteria
    - Any user that does NOT own campaign 999 gets a 403 on GET /api/campaign/999/npcs
    - Any user that does NOT own campaign 999 gets a 403 on GET /api/campaign/999/npcs/1
    - Campaign owner still gets 200 on their own campaigns

    ## Verification
    Run QA suite TC-NPCS-11 after fix. Commit the fix and confirm hash in outbox.
