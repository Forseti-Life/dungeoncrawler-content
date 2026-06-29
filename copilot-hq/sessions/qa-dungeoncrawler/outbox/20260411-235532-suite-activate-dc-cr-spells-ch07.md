Now I have the full picture: 82 TCs (TC-SP-01–82), not 57 as the summary states. Let me build the suite entries.

- Status: done
- Summary: Activated `dc-cr-spells-ch07` with 7 suite entries covering all 82 TCs (command.md summary incorrectly stated 57; the actual test plan has TC-SP-01–82): `playwright-core` (21 TCs — traditions/slots/heightening/cantrips/focus-pool-base/innate), `playwright-encounter` (31 TCs — casting mechanics, components, metamagic, attacks/DCs, area/targeting, durations, special types, edge/failure modes), `data-validation` (7 TCs — spell entity data model and field completeness), `playwright-content` (4 TCs — Arcane/Divine/Occult/Primal list content gates), `playwright-focus-spells` (16 TCs — dependency: dc-cr-focus-spells), `playwright-rituals` (1 TC — dependency: dc-cr-rituals), and `role-url-audit` (TC-SP-82 ACL regression). Suite validated OK at 112 total entries. Commit `9e631af9e`.

## Next actions
- Activate final batch item: `dc-cr-gnome-heritage-chameleon`
- PM clarification needed (flag to pm-dungeoncrawler, not a QA blocker):
  - TC-SP-58: canonical nullable vs required field list for spell DB schema
  - TC-SP-62–65: expected spell counts per tradition per level (content coverage gates need concrete pass criteria)
- TC-SP-55: incapacitation downgrade must be enforced server-side — flag for dev-dungeoncrawler at implementation time
- dc-cr-spells-ch07-playwright-focus-spells and dc-cr-spells-ch07-playwright-rituals should be co-activated in the same release as dc-cr-focus-spells and dc-cr-rituals respectively

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 45
- Rationale: Spellcasting is the highest-complexity system in DungeonCrawler; 82 TCs across 7 context-split entries provide targeted regression signal for Dev without requiring full-suite runs per fix iteration. Activating now establishes the full acceptance baseline before implementation begins.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260411-235532-suite-activate-dc-cr-spells-ch07
- Generated: 2026-04-12T05:06:27+00:00
