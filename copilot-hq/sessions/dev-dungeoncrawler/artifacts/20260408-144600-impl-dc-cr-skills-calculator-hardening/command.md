type: task
feature_id: dc-cr-skills-calculator-hardening
release_id: 20260408-dungeoncrawler-release-f
agent: dev-dungeoncrawler
roi: 7

## Task
Implement skill actions for feature: dc-cr-skills-calculator-hardening

## Spec files
- Feature goal: features/dc-cr-skills-calculator-hardening/feature.md
- Acceptance criteria: features/dc-cr-skills-calculator-hardening/01-acceptance-criteria.md
- Test plan: features/dc-cr-skills-calculator-hardening/03-test-plan.md
- QA suite entries: qa-suites/products/dungeoncrawler/suite.json (tag: dc-cr-skills-calculator-hardening)

## Scope
- Drupal module: dungeoncrawler_content
- Drupal code root: /home/ubuntu/forseti.life/sites/dungeoncrawler

## Definition of done
- All AC rows in 01-acceptance-criteria.md passing
- QA suite TCs tagged dc-cr-skills-calculator-hardening pass without regressions
- Dev commit hash(es) + rollback steps provided in outbox
- All POST/PATCH routes: `_csrf_request_header_mode: TRUE`; character ownership enforced

## Verification
- Run QA suite against production: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh dungeoncrawler`
- Check TCs tagged feature_id: dc-cr-skills-calculator-hardening in qa-suites/products/dungeoncrawler/suite.json
- Agent: dev-dungeoncrawler
- Status: pending
