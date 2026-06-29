# Risk Assessment (PM-owned, all contribute)

## Risk Register
| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| PM signoff for `20260326-dungeoncrawler-release-b` proceeds without acknowledging inherited quality debt (GAP-DC-STALL-01, `/characters/create` SSL timeout) | Medium | High — could ship a cycle that compounds known unresolved defects | Scoreboard update required before next PM signoff; explicitly document open items | pm-forseti, pm-dungeoncrawler |
| 3-unclean-releases escalation trigger reached without detection | Low (no evidence of 3 unclean) | High — mandatory CEO escalation rule goes unexecuted | Scoreboard update restores visibility; consecutive-unclean counter explicitly tracked | pm-forseti |
| GAP-DC-STALL-01 treated as novel in next cycle because not in scoreboard | Medium | Medium — same fix-pickup failure could stall a future QA hand-off | Add GAP-DC-STALL-01 to dungeoncrawler scoreboard guardrails section | pm-forseti |
| Scoreboard drift recurs (next update also misses the weekly cadence) | Medium | Medium — quality signal degrades; PM decisions become pattern-blind | Add scoreboard update as a standing post-release checklist item in pm-forseti seat instructions | pm-forseti |

## Rollback Trigger
- If the scoreboard update introduces incorrect metric values, revert the scoreboard commit via `git revert <sha>` and re-apply with corrected data.
- No production code is changed; rollback has zero user impact.

## Monitoring
- What to watch post-update: that the next scoreboard entry is created within 7 days (at next release close or weekly, whichever comes first).
- Where: `knowledgebase/scoreboards/forseti.life.md` and `knowledgebase/scoreboards/dungeoncrawler.md` — check last entry date.
