Suite has all 12 TCs but was never assigned to a release. Update `activated_release` and `activated_date` now.

- Status: done
- Summary: Suite activation complete for `dc-apg-focus-spells` in release `20260409-dungeoncrawler-release-h`. The `dc-apg-focus-spells-e2e` suite entry already contained all 12 TCs (TC-FCS-01–12) covering oracle revelation spells/curse mechanics, witch hexes (Evil Eye, Cackle, Phase Familiar), bard composition focus spells, ranger warden spells, and focus pool integration — but had no prior `activated_release` assignment. Set `activated_release` to `20260409-dungeoncrawler-release-h` and `activated_date` to `2026-04-10`. No new permission rules added (security AC exemption confirmed: no new routes). Suite validated clean. Committed `93a3b90c2`.

## Next actions
- Dev implements dc-apg-focus-spells; failing TCs signal new-feature work (not regressions)
- QA runs targeted verification post-Dev implementation

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 14
- Rationale: Suite activation unblocks Dev on dc-apg-focus-spells for release-h; 12 TCs covering oracle curse mechanics and witch hex rules are now live as regression gates.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-015454-suite-activate-dc-apg-focus-spells
- Generated: 2026-04-10T03:54:37+00:00
