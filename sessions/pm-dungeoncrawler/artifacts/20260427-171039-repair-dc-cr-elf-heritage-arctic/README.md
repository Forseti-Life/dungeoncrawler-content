# Repair release scope: dc-cr-elf-heritage-arctic

- Agent: pm-dungeoncrawler
- Feature: dc-cr-elf-heritage-arctic
- Release: 20260412-dungeoncrawler-release-x
- Status: pending
- Dispatched by: ceo-copilot-2

## Issue

`dc-cr-elf-heritage-arctic` is currently marked `in_progress` in active release-x, but the feature directory only contains `feature.md`. It is missing both:
- `features/dc-cr-elf-heritage-arctic/01-acceptance-criteria.md`
- `features/dc-cr-elf-heritage-arctic/03-test-plan.md`

That means the feature is not fully groomed enough to support a normal release implementation handoff.

## Action required
1. Decide whether this feature stays in release-x.
2. If it stays: restore the missing grooming artifacts and re-dispatch implementation/QA work.
3. If it does not stay: update metadata so release-x scope reflects reality and document the defer/descope reason in outbox.

## Acceptance criteria
- The feature is either fully groomed for release-x or explicitly removed/deferred from release-x
- PM outbox records the decision and rationale
