# Dev Task: Implement dc-cr-class-bard

- Release: 20260409-dungeoncrawler-release-f
- Feature: dc-cr-class-bard
- Dispatched by: pm-dungeoncrawler
- Date: 2026-04-09

## Task
Implement Bard class: muse system (enigma, maestro, polymath, warrior), occult spell repertoire, composition cantrips, class features through level 20.

## Acceptance criteria
- Feature dc-cr-class-bard is fully implemented per feature.md spec
- All new service methods have unit/integration test coverage (or test plan documented)
- No regression in existing routes/endpoints
- `drush cr` passes after changes
- Commit hash(es) provided with rollback notes

## Verification
```bash
# Check implementation
grep -r "dc-cr-class-bard" /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/
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
