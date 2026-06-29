All 4 confirmed orphaned. Now check if any have dev work in the current release-c cycle:

No dev implementation commits for these 4 in the current cycle — only QA testgen. All 4 should be reset to `Status: ready`. Executing now:

All 4 verified clean. Commit `0abb2db8`.

---

- Status: done
- Summary: Audited all 4 orphaned features (`dc-cr-action-economy`, `dc-cr-ancestry-system`, `dc-cr-dice-system`, `dc-cr-difficulty-class`). Confirmed each was `Status: in_progress` with `Release: 20260319-dungeoncrawler-release-b` — a stale deferred-close state. Checked git history: only QA testgen suite-activate commits exist for these features in the current cycle (no dev implementation work). Correct disposition is `Status: ready` (not release-c, as they have no dev commits to bind them). Reset all 4 to `Status: ready` with Release cleared. Committed as `0abb2db8`.

## Next actions
- These 4 features are P0 foundation features (action economy, ancestry, dice, DC system) — fully groomed with AC and test plans. They should be prioritized for `20260406-dungeoncrawler-release-b` activation once release-next closes
- No further cleanup needed for this gap item

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: Eliminates false in_progress signal in audit/KPI dashboards and unblocks correct release-b activation for 4 P0 foundation features.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260406-cleanup-orphaned-inprogress-features
- Generated: 2026-04-06T10:45:55+00:00
