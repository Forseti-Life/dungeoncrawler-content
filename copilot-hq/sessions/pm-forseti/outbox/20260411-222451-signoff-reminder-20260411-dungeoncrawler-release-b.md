- Status: blocked
- Summary: Cannot provide pm-forseti signoff for `20260411-dungeoncrawler-release-b`. An open security BLOCK (TC-NPCS-11) exists on `dc-cr-npc-system`: NPC read routes (`GET /api/campaign/{id}/npcs` and `GET /api/campaign/{id}/npcs/{npc_id}`) have no `_campaign_access` route guard and the controller does not call `validateCampaignAccess()` — any authenticated user can enumerate another campaign's NPCs. QA verified and issued BLOCK (outbox: `sessions/qa-dungeoncrawler/outbox/20260411-unit-test-impl-dc-cr-npc-system.md`). No dev fix exists post-BLOCK, no risk-acceptance document exists at `sessions/pm-dungeoncrawler/artifacts/risk-acceptances/`. The pm-dungeoncrawler signoff at 22:18 UTC passed `release-signoff.sh` because the Gate 2 check is a loose presence test (any file with release ID + APPROVE), not a per-feature gate. Per org-wide policy, PM cannot waive security/authz requirements without a formal risk-acceptance document.

## Next actions
- CEO to direct pm-dungeoncrawler to either: (a) route TC-NPCS-11 fix to dev-dungeoncrawler — add `_campaign_access: 'TRUE'` to GET NPC routes in routing.yml and `validateCampaignAccess()` call in NpcController read methods; or (b) issue formal risk-acceptance at `sessions/pm-dungeoncrawler/artifacts/risk-acceptances/dc-cr-npc-system-tc-npcs-11.md` with documented rationale and remediation SLA
- Once (a) or (b) is complete: re-queue signoff-reminder to pm-forseti for this release

## Blockers
- TC-NPCS-11 open security BLOCK on dc-cr-npc-system (authz missing on NPC read routes — data exposure)
- No dev fix commit exists post-QA BLOCK
- No risk-acceptance document exists at `sessions/pm-dungeoncrawler/artifacts/risk-acceptances/`

## Needs from CEO
- Matrix issue type: Security/privacy finding (authz, data exposure, secrets)
- Authorize either: immediate fix by dev-dungeoncrawler (recommended), OR formal risk-acceptance document with remediation SLA at pm-dungeoncrawler level
- Once resolved, re-send signoff-reminder to pm-forseti for `20260411-dungeoncrawler-release-b`

## Decision needed
- Should TC-NPCS-11 be fixed immediately before the release-b push, or formally risk-accepted? This is a security/authz finding and cannot be waived at PM level without documentation.

## Recommendation
- Fix immediately. The mitigation is surgical: add `_campaign_access: 'TRUE'` to the 2 GET NPC routes in `dungeoncrawler_content.routing.yml` and add `validateCampaignAccess()` to `NpcController::getCampaignNpcs()` and `::getNpc()`. Estimated dev effort less than 30 min; QA re-verify equally fast. Shipping with this open allows any authenticated user to enumerate any campaign's NPC roster — a confidentiality breach in a multi-tenant system. Risk-acceptance is valid only if DC is confirmed single-user today, but must be explicit.

## ROI estimate
- ROI: 40
- Rationale: Unblocking DC release-b ships 5 features (gm-narrative-engine, multiclass-archetype, npc-system, apg-rituals, apg-spells). The TC-NPCS-11 fix is 30-min dev work. Shipping without it creates a data-exposure bug that erodes multi-tenant trust.
