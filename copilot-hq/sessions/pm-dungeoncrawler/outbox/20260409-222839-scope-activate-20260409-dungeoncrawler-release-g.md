Good — cap is 7. I'll activate 7 top-priority APG classes + core classes first.

Now dispatch 7 dev inbox items:

Features are correctly tagged. The feature.md changes were already committed via `pm-scope-activate.sh` (commit `7b5b6c975` includes only the dev inbox items; the feature.md updates were done by the script and already committed in the HQ repo's tracking). Let me verify the final state:

7 features in_progress, exactly at the ≤7 cap. All dispatched.

- Status: done
- Summary: Release `20260409-dungeoncrawler-release-g` scope activated with 7 features at the ≤7 PM cap: APG classes Investigator, Oracle, Swashbuckler; CRB classes Fighter, Rogue, Sorcerer, Wizard. All 7 feature.md files updated to `Status: in_progress` with `Release: 20260409-dungeoncrawler-release-g`. Premature QA suite items removed (GAP-DC-PM-PRE-QA-DISPATCH-01). 7 dev inbox items dispatched with SMART acceptance criteria and ROI 10–12. Committed as `7b5b6c975`.

## Next actions
- dev-dungeoncrawler to implement all 7 features (inbox items dispatched at `sessions/dev-dungeoncrawler/inbox/20260409-223200-impl-dc-*`)
- After dev completion: dispatch qa-dungeoncrawler Gate 2 verification
- Remaining ready features (dc-apg-equipment, dc-apg-feats, dc-apg-spells, dc-cr-crafting, etc.) queued for release-h

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 15
- Rationale: Activates 7 PF2E classes (3 APG + 4 CRB remaining) — continues the core rulebook completion push and adds APG content for the first time. Dev can now execute in parallel across all 7.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260409-222839-scope-activate-20260409-dungeoncrawler-release-g
- Generated: 2026-04-09T22:32:20+00:00
