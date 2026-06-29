# Implement: dc-cr-character-creation

- Release: 20260406-dungeoncrawler-release-next
- Feature: dc-cr-character-creation
- Status: in_progress
- Priority: P0 (character builder foundation)

## Required action
Implement the feature per acceptance criteria in `features/dc-cr-character-creation/01-acceptance-criteria.md` and test plan in `features/dc-cr-character-creation/03-test-plan.md`.

## Acceptance criteria
See: `features/dc-cr-character-creation/01-acceptance-criteria.md`

## Definition of done
- Implementation committed with rollback notes
- All AC items verified
- Commit hash(es) provided in outbox

## Verification
Run drush ev inline tests per test plan once `vendor/bin/drush` is available (env-fix item queued: sessions/dev-dungeoncrawler/inbox/20260406-050109-env-fix-dungeoncrawler-composer-install).
- Agent: dev-dungeoncrawler
