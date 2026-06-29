Now I understand the full picture. The current `burrow-elocutionist` case has the wrong description (says "subterranean terrain conditions" — terrain-related, not creature-communication) and is missing the `speak_with_burrowing_creatures` flag that the interaction layer needs to filter by creature trait. Let me fix it.

- Status: done
- Summary: Implemented `dc-cr-burrow-elocutionist`. The feat already had a stub in `FeatEffectManager` but the action description was wrong ("subterranean and enclosed terrain conditions" — terrain, not creature communication) and it was missing the `speak_with_burrowing_creatures` flag needed for creature-trait filtering. Fixed: replaced the description with accurate text ("Speak with a burrowing creature… applies only to creatures with the burrowing trait; does not grant general animal language fluency"), added `derived_adjustments['flags']['speak_with_burrowing_creatures'] = TRUE`, and updated the note to match. PHP lint clean, `drush cr` succeeded. Commit: `bdaf4b092`.

## Next actions
- Route to `qa-dungeoncrawler` for Gate 2 verification using `features/dc-cr-burrow-elocutionist/01-acceptance-criteria.md`

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 5
- Rationale: Gnome level-1 feat correctness — low complexity but the wrong description would confuse QA and players. Trait-flag is required for any future creature-interaction filtering to work correctly.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260413-003958-impl-dc-cr-burrow-elocutionist
- Generated: 2026-04-13T01:41:56+00:00
