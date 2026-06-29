Suite has all 7 TCs and correct feature_id, just missing `activated_release`. Set it now.

- Status: done
- Summary: Suite activation complete for `dc-apg-rituals` in release `20260409-dungeoncrawler-release-h`. The `dc-apg-rituals-e2e` suite entry already contained all 7 TCs (TC-RIT-01–07) covering ritual system extensibility, stat block completeness, multi-secondary-caster pattern, rare/uncommon GM-approval gate, data-driven primary check storage, and two edge cases (zero secondary casters, book_id collision disambiguation) — but had no prior `activated_release` assignment. Set `activated_release` to `20260409-dungeoncrawler-release-h` and `activated_date` to `2026-04-10`. No new permission rules added (security AC exemption confirmed: no new routes). Suite validated clean. Committed `ecf332497`.

## Next actions
- Dev implements dc-apg-rituals; failing TCs signal new-feature work (not regressions)
- QA runs targeted verification post-Dev implementation

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 14
- Rationale: Suite activation unblocks Dev on dc-apg-rituals for release-h; 7 TCs including the multi-secondary-caster and book_id edge cases are now live as regression gates.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-015454-suite-activate-dc-apg-rituals
- Generated: 2026-04-10T04:45:59+00:00
