# QA Verification: fix-npc-read-authz-coordinated-release

- Status: done
- Summary: Re-verified `dc-cr-npc-system` against fix commit `ffdc43499`. TC-NPCS-11 (HIGH severity authz bypass) now PASS — `validateCampaignAccess($campaign_id)` is confirmed as the first statement in both `getNpc()` (L105) and `getCampaignNpcs()` (L124) in `NpcService.php`. The fix closes the read-path data exposure: non-owners now receive `AccessDeniedHttpException` (HTTP 403) when attempting to list or read NPCs for campaigns they do not own. All 5 NpcService write+read methods (L49 createNpc, L105 getNpc, L124 getCampaignNpcs, L144 updateNpc, L189 deleteNpc) are now guarded by `validateCampaignAccess`. All other in-scope TCs (NPCS-01, 03–07, 09–10) remain PASS from prior verification. TC-NPCS-02 remains deferred (inventory system out of scope). TC-NPCS-08 scope question remains open (PM decision: lore_notes sufficient vs explicit quest_giver flag). Site audit `dungeoncrawler-20260411-223817` clean — only expected anon 403s. **APPROVE.** This is the final Gate 2 BLOCK for release `20260411-dungeoncrawler-release-b`; all three features now have QA APPROVE status.

## Test case results

| TC | Result | Notes |
|---|---|---|
| TC-NPCS-01 | PASS | (unchanged) All required fields; enum validation on role + attitude |
| TC-NPCS-02 | DEFERRED | Merchant inventory system not in feature scope |
| TC-NPCS-03 | PASS | (unchanged) Abbreviated stat block present |
| TC-NPCS-04 | PASS | (unchanged) Diplomacy attitude step-up logic |
| TC-NPCS-05 | PASS | (unchanged) Deception detected attitude step-down, floor=hostile |
| TC-NPCS-06 | PASS | (unchanged) Attitude changes persisted to dc_npc table + history logged |
| TC-NPCS-07 | PASS | (unchanged) AI GM context includes name/role/attitude/lore |
| TC-NPCS-08 | SCOPE QUESTION | No explicit quest_giver flag — PM decision pending (lore_notes may suffice) |
| TC-NPCS-09 | PASS | (unchanged) dc_npc distinct from creature system |
| TC-NPCS-10 | PASS | (unchanged) Campaign NPC list populated; history audit trail with session_id |
| TC-NPCS-11 | **PASS** | Fixed: validateCampaignAccess() at L105 (getNpc) and L124 (getCampaignNpcs) — 403 for non-owners |

## Verification evidence

**TC-NPCS-11 evidence:**
- `NpcService.php` L105: `$this->validateCampaignAccess($campaign_id);` — first line of `getNpc()`
- `NpcService.php` L124: `$this->validateCampaignAccess($campaign_id);` — first line of `getCampaignNpcs()`
- `validateCampaignAccess()` (L373): queries `dc_campaigns.uid`, throws `AccessDeniedHttpException` (HTTP 403) for non-owners unless admin
- Full coverage: L49 (createNpc), L105 (getNpc), L124 (getCampaignNpcs), L144 (updateNpc), L189 (deleteNpc) — all 5 mutating/reading methods guarded

**Site audit:** `dungeoncrawler-20260411-223817` — Errors/concerns table: only expected anon 403s on `/campaigns` and `/characters/create`. No regressions.

## Release status summary (20260411-dungeoncrawler-release-b)

| Feature | QA Status | Verified Commit |
|---|---|---|
| dc-cr-gm-narrative-engine | **APPROVE** | cee051628 |
| dc-cr-multiclass-archetype | **APPROVE** | 063e8c633 |
| dc-cr-npc-system | **APPROVE** | ffdc43499 |

All three release-bound features cleared Gate 2. PM may proceed to release gate (Gate 3/4).

## Verdict

**APPROVE** — `dc-cr-npc-system` passes Gate 2. Security AC met. All in-scope TCs PASS or deferred with documented rationale.

## Next actions
- PM may proceed with coordinated release for `20260411-dungeoncrawler-release-b` — all Gate 2 BLOCKs resolved
- TC-NPCS-08 (quest_giver flag) remains an open scope question for PM; no blocking impact on current release
- No new Dev items identified

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 95
- Rationale: This was the final HIGH-severity security BLOCK holding the entire dungeoncrawler release. All three features are now Gate 2 APPROVED; the release can ship.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260411-unit-test-20260411-fix-npc-read-authz-coordinated-release
- Commit: aacb3c0f6 (checklist update — APPROVE)
- Generated: 2026-04-11
