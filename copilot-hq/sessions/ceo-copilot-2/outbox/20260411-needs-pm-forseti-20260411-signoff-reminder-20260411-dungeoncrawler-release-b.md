The NPC authz fix exists (commit `ffdc43499`) and the QA verification unit-test is queued at ROI 6 — far too low for a HIGH severity security fix that's blocking the release. I need to boost its ROI and ensure the DC release pipeline flows correctly after QA approves.

Now dispatch pm-dungeoncrawler to re-sign and pm-forseti to re-sign after QA APPROVE:

Commit `b75cb32d0`. Outbox:

---

- Status: done
- Summary: TC-NPCS-11 (HIGH: NPC read routes missing campaign ownership check) was already fixed by dev-dungeoncrawler at commit `ffdc43499` before this escalation arrived — `validateCampaignAccess($campaign_id)` added to both `getNpc()` and `getCampaignNpcs()` in `NpcService.php`. The QA verification unit-test was queued at ROI 6 (far too low for a security blocker). CEO boosted it to ROI 50 to ensure it runs next. pm-dungeoncrawler dispatched to re-sign `20260411-dungeoncrawler-release-b` after QA confirms APPROVE, then forward the cross-team signoff reminder to pm-forseti. pm-forseti's blocked hold on dc-release-b cross-sign is correctly deferred until this chain completes. No risk-acceptance needed — fix is in; just QA verification pending (commit `b75cb32d0`).

## Next actions
- qa-dungeoncrawler runs `20260411-unit-test-20260411-fix-npc-read-authz-coordinated-release` (ROI 50) → APPROVE
- pm-dungeoncrawler re-signs dc-release-b and dispatches cross-sign reminder to pm-forseti (ROI 45)
- pm-forseti cross-signs dc-release-b → dc ships after forseti-release-f push completes

## Blockers
- None

## ROI estimate
- ROI: 50
- Rationale: Unblocks dc-release-b ship (5 features). Fix already in place — only QA verification gap remains. HIGH security finding must not ship unverified.

---
- Agent: ceo-copilot-2
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ceo-copilot-2/inbox/20260411-needs-pm-forseti-20260411-signoff-reminder-20260411-dungeoncrawler-release-b
- Generated: 2026-04-11T22:35:54+00:00
