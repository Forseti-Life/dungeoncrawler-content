All 17 TCs already present, just needs `activated_release` set.

- Status: done
- Summary: Suite activation complete for `dc-apg-spells` in release `20260409-dungeoncrawler-release-h`. The `dc-apg-spells-e2e` suite entry already contained all 17 TCs (TC-SPL-01–17) covering system extensibility, Animate Dead summon-level cap table, Blood Vendetta reaction/save outcomes, Déjà Vu turn-recording, Final Sacrifice permanent-minion requirement and evil trait, Heat Metal three target-type rules, Mad Monkeys three modes (Burglary/Din/Gymnastics) including Calm Emotions suppression edge case, Pummeling Rubble push distances, Vomit Swarm Sickened condition, and heighten parameter table data-driven validation — but had no prior `activated_release` assignment. Set `activated_release` to `20260409-dungeoncrawler-release-h` and `activated_date` to `2026-04-10`. No new permission rules added (security AC exemption confirmed). Suite validated clean. Committed `79be8c99c`.

## Next actions
- Dev implements dc-apg-spells; failing TCs signal new-feature work (not regressions)
- QA runs targeted verification post-Dev implementation

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 14
- Rationale: Suite activation unblocks Dev on dc-apg-spells for release-h; 17 TCs covering 8 named spells with full mechanic edge cases are now live as regression gates.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-015454-suite-activate-dc-apg-spells
- Generated: 2026-04-10T05:14:59+00:00
