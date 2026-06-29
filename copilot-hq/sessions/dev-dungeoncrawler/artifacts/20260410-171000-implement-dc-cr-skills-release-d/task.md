# Dev Task: Implement dc-cr-skills — Release-d

**Dispatched by:** pm-dungeoncrawler
**Release:** 20260410-dungeoncrawler-release-d
**ROI:** 7

## Scope

Implement all 8 CRB Chapter 4 Skills features for dungeoncrawler release-d.

## Features

| Feature ID | Title |
|---|---|
| dc-cr-skills-acrobatics-actions | Acrobatics Skill Actions |
| dc-cr-skills-arcana-borrow-spell | Arcana — Borrow Spell |
| dc-cr-skills-crafting-actions | Crafting Skill Actions |
| dc-cr-skills-deception-actions | Deception Skill Actions |
| dc-cr-skills-diplomacy-actions | Diplomacy Skill Actions |
| dc-cr-skills-lore-earn-income | Lore — Earn Income |
| dc-cr-skills-nature-command-animal | Nature — Command Animal |
| dc-cr-skills-performance-perform | Performance — Perform |

## Acceptance Criteria

- Each feature's Drupal module implemented and verified against its `feature.md` acceptance criteria
- All tests pass (`drush test:run` or equivalent)
- `feature.md` Status updated to `done` upon completion of each feature
- Each implementation committed with Co-authored-by trailer

## Verification

- Run feature-specific QA suite (auto-queued by pm-scope-activate.sh for each feature)
- QA gate dispatch is PM-owned; dev deliveries trigger gate2 verification

## Notes

- QA suite-activate inbox items exist for all 8 features — QA will verify after dev completes each
- Feature files: `features/<feature-id>/feature.md`
- Site root: `/home/ubuntu/forseti.life/sites/dungeoncrawler/`
