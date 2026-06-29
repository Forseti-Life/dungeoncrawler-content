- Status: blocked
- Summary: pm-forseti cannot co-sign `20260411-dungeoncrawler-release-b` (dungeoncrawler release, features: `dc-cr-npc-system`, `dc-cr-gm-narrative-engine`, `dc-cr-multiclass-archetype`). An open HIGH-severity security defect (TC-NPCS-11: NPC read routes lack campaign ownership authorization) was discovered after pm-dungeoncrawler recorded their signoff at 22:18 UTC. Fix commit `ffdc43499` (`validateCampaignAccess()` added to `NpcService::getNpc()` and `::getCampaignNpcs()`) was made, but QA re-verification is still queued (ROI 50 in qa-dungeoncrawler inbox). Per org-wide policy, pm-forseti cannot waive security/authz requirements — signoff is blocked until QA APPROVES the fix and pm-dungeoncrawler re-records their signoff.

## Product context
- Website: dungeoncrawler
- Module: `dungeoncrawler_content` / NPC system (`NpcService`, `NpcController`)
- Feature: `dc-cr-npc-system` (Release: `20260411-dungeoncrawler-release-b`)
- Security defect: TC-NPCS-11 — any authenticated user can enumerate another campaign's NPCs via `GET /api/campaign/{id}/npcs` and `GET /api/campaign/{id}/npcs/{npc_id}` — no `_campaign_access` route guard, no `validateCampaignAccess()` call in controller read paths
- Fix: commit `ffdc43499` — `validateCampaignAccess()` added as first statement in `NpcService::getNpc()` (L105) and `NpcService::getCampaignNpcs()` (L124)
- Role: pm-forseti (release operator — must co-sign before coordinated push)

## Next actions
- qa-dungeoncrawler: process `20260411-unit-test-20260411-fix-npc-read-authz-coordinated-release` (ROI 50) — APPROVE/BLOCK on `ffdc43499`
- pm-dungeoncrawler: re-record signoff after QA APPROVE
- pm-forseti: co-sign and execute coordinated push once both signoffs confirmed

## Blockers
- QA APPROVE on TC-NPCS-11 fix (`ffdc43499`) not yet issued
- pm-dungeoncrawler re-signoff not yet recorded

## Needs from CEO
- N/A — fix is in flight; unblocks automatically when qa-dungeoncrawler processes inbox item (ROI 50)

## Decision needed
- Should the release proceed without waiting for QA re-verify (accept TC-NPCS-11 risk with a formal risk-acceptance document), OR hold until QA APPROVE is issued?

## Recommendation
- Hold for QA APPROVE. TC-NPCS-11 is HIGH severity (cross-campaign data exposure) and the fix is committed and queued for re-verify at ROI 50 — the delay is hours, not days. Risk-accepting a HIGH authz bypass in a community platform contradicts the mission. The fix path is clear; accept the short delay.
- Tradeoff: accepting risk would unblock the push immediately but leaves a known authz bypass in production until a future hotfix cycle.

## ROI estimate
- ROI: 50
- Rationale: Unblocking this co-sign unblocks the full coordinated release (DC + Forseti); HIGH security defect on community platform must not be shipped unresolved.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260411-coordinated-signoff-20260411-dungeoncrawler-release-b
- Generated: 2026-04-11T22:37:19+00:00
- Updated: 2026-04-12T04:37:40+00:00 (added required escalation context per clarify-escalation inbox item)
- Resolution: TC-NPCS-11 QA APPROVED (`ffdc43499`, outbox `sessions/qa-dungeoncrawler/outbox/20260411-unit-test-20260411-fix-npc-read-authz-coordinated-release.md`); release shipped in coordinated push `20260411-coordinated-release-next` at 2026-04-12T01:45:42+00:00
