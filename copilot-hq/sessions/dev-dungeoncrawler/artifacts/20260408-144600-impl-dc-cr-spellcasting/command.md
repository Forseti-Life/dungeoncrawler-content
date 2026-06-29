type: task
feature_id: dc-cr-spellcasting
release_id: 20260408-dungeoncrawler-release-f
agent: dev-dungeoncrawler
roi: 7

## Task
Implement the Spellcasting Rules System for DungeonCrawler.

## Spec files
- Feature goal: features/dc-cr-spellcasting/feature.md
- Acceptance criteria: features/dc-cr-spellcasting/01-acceptance-criteria.md
- Test plan: features/dc-cr-spellcasting/03-test-plan.md
- QA suite entries: qa-suites/products/dungeoncrawler/suite.json (tag: dc-cr-spellcasting)

## Scope
- Drupal module: dungeoncrawler_content
- Drupal code root: /home/ubuntu/forseti.life/sites/dungeoncrawler

## Acceptance criteria summary
- Spell slots tracked by level (1–10) per class progression table
- Casting traditions: arcane, divine, occult, primal
- Prepared vs. spontaneous casting logic
- Spell attack rolls (d20 + proficiency + key ability mod + item bonus)
- Spell DCs (10 + proficiency + key ability mod)
- Heightening spells (slot-level and signature spell rules)
- Cantrips do not expend slots
- All POST/PATCH routes: `_csrf_request_header_mode: TRUE`; character ownership enforced via `_character_access: TRUE`

## Definition of done
- All AC rows in 01-acceptance-criteria.md passing
- QA suite TCs tagged dc-cr-spellcasting pass without regressions
- Dev commit hash(es) + rollback steps provided in outbox

## Verification
- Run QA suite against production: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh dungeoncrawler`
- Check TCs tagged feature_id: dc-cr-spellcasting in qa-suites/products/dungeoncrawler/suite.json
- Agent: dev-dungeoncrawler
- Status: pending
