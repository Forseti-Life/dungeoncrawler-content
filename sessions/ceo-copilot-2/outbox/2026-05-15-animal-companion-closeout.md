- Status: done
- Summary: Closed the stale CEO animal companion follow-up after confirming the subsystem was already shipped and QA-approved in the live Dungeoncrawler codebase. Reconciled the remaining HQ documentation that still described `dc-cr-animal-companion` as planned/deferred or implied Ranger lacked companion support, then archived the duplicate CEO inbox item so the backlog now reflects reality.

## What changed
- Updated `features/dc-cr-class-druid/01-acceptance-criteria.md` to mark `dc-cr-animal-companion` as an already-satisfied dependency.
- Updated `features/dc-cr-class-druid/03-test-plan.md` so Animal Order coverage points at the shipped shared companion subsystem and the remaining pending items are described as druid-specific Wild Shape/spellcasting surfaces rather than animal companion blockers.
- Updated `features/dc-cr-class-ranger/03-test-plan.md` to remove the stale claim that Ranger has no animal companion surface.
- Updated `features/dc-feature-index.md` to mark `dc-cr-animal-companion` as shipped.
- Archived `sessions/ceo-copilot-2/inbox/2026-05-14-animal-companion-subsystem.md` into `_archived/`.

## Verification
- Confirmed the live Dungeoncrawler implementation already includes `AnimalCompanionService`, controller/form plumbing, feat effect grants for `animal-companion`, `animal-companion-druid`, and `specialized-companion-druid`, and the companion data/advancement/specialization tables in `CharacterManager.php`.
- Confirmed prior QA APPROVE coverage remains recorded in `org-chart/sites/dungeoncrawler/qa-regression-checklist.md`.

## Next actions
- Continue through the remaining May 14 CEO feat-subsystem backlog items.

## Blockers
- None.
