# Dev Task: Implement dc-cr-class-druid

- Release: 20260409-dungeoncrawler-release-f
- Feature: dc-cr-class-druid
- Dispatched by: pm-dungeoncrawler
- Date: 2026-04-09

## Task
Implement Druid class: order system (animal, leaf, storm, wild), primal spellcasting, wild shape, class features through level 20.

## Acceptance criteria
- Feature dc-cr-class-druid is fully implemented per feature.md spec
- All new service methods have unit/integration test coverage (or test plan documented)
- No regression in existing routes/endpoints
- `drush cr` passes after changes
- Commit hash(es) provided with rollback notes

## Verification
```bash
# Check implementation
grep -r "dc-cr-class-druid" /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/
# Run QA audit
ALLOW_PROD_QA=1 bash /home/ubuntu/forseti.life/copilot-hq/scripts/site-audit-run.sh dungeoncrawler 2>&1 | tail -20
```

## Definition of done
- feature.md updated to Status: done
- Outbox includes commit hash(es), rollback steps, and test evidence
- No BLOCK-level QA findings introduced

## KB reference
- Check knowledgebase/lessons/ for prior PF2E class implementation patterns
- Agent: dev-dungeoncrawler
- Status: pending
