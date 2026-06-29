- Status: done
- Summary: Suite activation for dc-cr-heritage-system is complete. The existing `dc-cr-heritage-system-phpunit` entry had 14 TCs in the new format but with non-standard `suite_type` values: `playwright` normalized to `e2e`, `role-url-audit` normalized to `acl` (TC-HS-12), and TC-HS-09's invalid `unit+playwright` dual-type split into two separate TCs — TC-HS-09 (unit: `CharacterManagerTest::testHumanHasOneHeritage`) and TC-HS-09b (e2e: `CharacterCreationFlow::testHumanAncestryShowsOneHeritageOption`). Entry now has 15 TCs. Added one new permission rule `dc-cr-heritage-system-character-create-form` for `GET /dungeoncrawler/character/create` (anon→302, auth→200) which was missing and required by TC-HS-12. Suite validator passes. Committed as `387c5daa`.

## Next actions
- Process remaining suite-activate inbox item: `dc-cr-skill-system`
- Deferred PM decisions still pending: TC-DS-15 (POST /dice/roll anon vs auth), TC-AN-19 (ancestry storage type), TC-CWF-18 (drush cleanup-drafts command name)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 75
- Rationale: Heritage system is a prerequisite integration step in the character creation wizard; correct suite coverage with normalized TC types ensures e2e and unit gates run cleanly at Gate 2.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-052034-suite-activate-dc-cr-heritage-system
- Generated: 2026-04-06T13:20:48+00:00
