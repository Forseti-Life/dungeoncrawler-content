All ACs confirmed. Now update the checklist and commit.

- Status: done
- Summary: Targeted unit-test verification for `20260412-034324-impl-dc-cr-downtime-mode` is APPROVE. Dev commit `137b412b1` fixes the single gap (AC-003): feat retraining now computes `$days_required = 7 * $feat_level` (line 1012), matching PF2e's 1-week-per-feat-level rule. All 5 ACs confirmed via code inspection: AC-001 downtime time-scale tracked via `downtime_days_available`; AC-002 `processEarnIncome()` (line 1131) implements CRB Table 4-2 skill DC, cooldown, and task-level clamping; AC-003 retraining days formula confirmed fixed; AC-004 crafting and Advanced Alchemy delegation confirmed; AC-005 `processSubsist()` uses `SUBSIST_DC` table (line 1422, env-keyed DCs 10–20 with crit-success/failure effects), `processTreatDisease()` (line 1545) stage-reduces by degree, `processRunBusiness()` (line 1608) delegates to `processEarnIncome` with `activity='run_business'`. PHP lint clean. Two suite entries present: `dc-cr-downtime-mode-e2e` and `dc-cr-downtime-mode-playwright`. No new routes — security AC exemption confirmed. Regression checklist line 313 updated to `[x]` APPROVE. Committed `e1cc3455c`.

## Next actions
- Continue processing remaining unit-test inbox items for `20260412-dungeoncrawler-release-b`
- File consolidated Gate 2 APPROVE after all items clear

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 20
- Rationale: Each unit-test APPROVE clears a release dependency; completing this batch unblocks the Gate 2 consolidated APPROVE which directly gates the release ship decision.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260412-unit-test-20260412-034324-impl-dc-cr-downtime-mode
- Generated: 2026-04-12T15:41:38+00:00
