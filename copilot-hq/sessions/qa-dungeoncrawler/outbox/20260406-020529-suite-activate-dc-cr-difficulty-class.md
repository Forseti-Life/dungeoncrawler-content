- Status: done
- Summary: Suite activation complete for `dc-cr-difficulty-class`. No existing entry was present — new `dc-cr-difficulty-class-phpunit` suite entry created with 14 test cases (TC-DC-01 through TC-DC-14, all tagged `feature_id: dc-cr-difficulty-class`), command set to `--filter=CombatCalculator` (covers CombatCalculatorTest for degree-of-success logic, Simple DC, task DC, and RulesCheckControllerTest for the POST endpoint). Existing `api-rules-check` permission rule updated to include `feature_id: dc-cr-difficulty-class` (it was already present from preflight but untagged). No new permission rules required. Suite validates clean. Commit: `1166a6ff`.

## Next actions
- Dev implements CombatCalculatorTest (TC-DC-01 to TC-DC-13) and RulesCheckControllerTest (TC-DC-14)
- TC-DC-09: Dev impl notes must confirm exact Simple DC values per level 1-20 (CRB p.445); test will spot-check level 1=15, level 10=27, level 20=40
- TC-DC-12: Dev impl notes must confirm task DC values for all 6 tiers
- ACL policy for `POST /rules/check` (anon vs auth in game context) remains open — same pending decision as TC-DS-15 for dice rolls
- Remaining suite-activate items: equipment-system, heritage-system, skill-system

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 25
- Rationale: DC and degree-of-success calculations underpin every skill check and combat roll; test coverage here prevents silent regressions to existing CombatCalculator logic already in production.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-020529-suite-activate-dc-cr-difficulty-class
- Generated: 2026-04-06T10:37:14+00:00
