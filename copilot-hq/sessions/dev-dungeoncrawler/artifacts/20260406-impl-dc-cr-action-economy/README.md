# Implement: dc-cr-action-economy

- Release: 20260405-dungeoncrawler-release-c
- Feature: dc-cr-action-economy
- Site: dungeoncrawler
- Module: dungeoncrawler_content
- ROI: 90

## Task

Implement `dc-cr-action-economy` per the acceptance criteria and test plan:
- `features/dc-cr-action-economy/01-acceptance-criteria.md`
- `features/dc-cr-action-economy/03-test-plan.md`

## Done when

- Implementation is complete in the dungeoncrawler Drupal site at `/home/ubuntu/forseti.life/sites/dungeoncrawler/`
- All acceptance criteria in `01-acceptance-criteria.md` are satisfied
- Commit hash(es) and rollback steps provided in outbox
- No regressions introduced (QA suite baseline maintained)

## Verification

QA will activate test suite items via `pm-scope-activate.sh` output (already queued for this feature).
- Agent: dev-dungeoncrawler
- Status: pending
