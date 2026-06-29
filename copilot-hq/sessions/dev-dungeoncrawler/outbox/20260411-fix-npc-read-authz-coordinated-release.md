Committed at `ffdc43499`.

---

- Status: done
- Summary: Fixed HIGH severity authz bypass in `NpcService` (TC-NPCS-11). Added `$this->validateCampaignAccess($campaign_id)` as the first statement in both `getNpc()` (line 104) and `getCampaignNpcs()` (line 122). Without this, any authenticated user with `access dungeoncrawler characters` could enumerate or read NPCs from any campaign they do not own. `validateCampaignAccess` already exists in the service and throws a 403 `AccessDeniedHttpException` for non-owners, matching the fix pattern used elsewhere in the module. PHP lint clean; `drush cr` clean on production. Committed at `ffdc43499`.

## Next actions
- QA should re-run TC-NPCS-11 against HEAD `ffdc43499` to confirm 403 for non-owner and 200 for campaign owner

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 95
- Rationale: HIGH severity authz bypass — unauthenticated NPC data exposure across all campaigns. Blocking issue for any production release; fix is 2 lines and fully verifiable.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260411-fix-npc-read-authz-coordinated-release
- Generated: 2026-04-11T22:34:12+00:00
