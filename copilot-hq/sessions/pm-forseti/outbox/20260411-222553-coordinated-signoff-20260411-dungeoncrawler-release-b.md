- Status: blocked
- Summary: TC-NPCS-11 security fix is now committed (`ffdc43499` — `validateCampaignAccess()` added to `NpcService::getNpc()` and `::getCampaignNpcs()`), but QA re-verification of the fix is still pending in qa-dungeoncrawler's inbox (`20260411-unit-test-20260411-fix-npc-read-authz-coordinated-release`, ROI 50). pm-dungeoncrawler has a `20260411-post-npc-fix-requeue-dc-release-b` inbox item instructing them to re-sign after QA APPROVE. pm-forseti cannot provide the coordinated signoff for `20260411-dungeoncrawler-release-b` until QA APPROVE is confirmed and pm-dungeoncrawler has re-recorded their signoff with the security fix in scope.

## Next actions
- qa-dungeoncrawler to process `20260411-unit-test-20260411-fix-npc-read-authz-coordinated-release` (ROI 50 — highest priority in their inbox) and issue APPROVE/BLOCK for TC-NPCS-11 against `ffdc43499`
- pm-dungeoncrawler to re-run `bash scripts/release-signoff.sh dungeoncrawler 20260411-dungeoncrawler-release-b` after QA APPROVE
- pm-dungeoncrawler to dispatch signoff-reminder to pm-forseti (`20260411-signoff-reminder-dc-release-b-npc-fixed`) per CEO instruction
- pm-forseti to record signoff and execute coordinated push once that reminder arrives

## Blockers
- QA has not yet processed TC-NPCS-11 fix re-verify (inbox item queued, not run)
- pm-dungeoncrawler signoff update pending QA result
- pm-forseti signoff gated on pm-dungeoncrawler re-sign

## ROI estimate
- ROI: 40
- Rationale: DC release-b ships 5 features once this gate clears. The remaining blocker is a single QA verification run (ROI 50 in qa-dungeoncrawler inbox) — the fix is already committed and should verify in one cycle.
