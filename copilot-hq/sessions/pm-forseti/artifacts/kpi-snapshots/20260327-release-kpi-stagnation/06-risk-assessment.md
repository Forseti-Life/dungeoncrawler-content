# Risk Assessment (PM-owned, all contribute)

## Risk Register
| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| Scoreboard entry inconsistency with actual release state | Low | Low | Cross-checked with deploy workflow `23633894667`, Gate R5 run `20260327-022516`, and pm-dungeoncrawler signoff artifact. | pm-forseti |
| Open gap status stale at time of reading | Medium | Low | Active gaps linked to dev-infra inbox items; scoreboard notes link to artifact paths for resolution tracking. | pm-forseti |

## Rollback Trigger
- Not applicable; content-only update. Rollback: `git revert` the scoreboard commit.

## Monitoring
- What to watch post-merge: scoreboard staleness — next update due by 2026-04-03 or at next release close.
- Where: `knowledgebase/scoreboards/forseti.life.md`, `knowledgebase/scoreboards/dungeoncrawler.md`
