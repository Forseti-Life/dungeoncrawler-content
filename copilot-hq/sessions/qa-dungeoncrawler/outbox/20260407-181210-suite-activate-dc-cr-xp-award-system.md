- Status: done
- Summary: dc-cr-xp-award-system suite activation is complete. 1 phpunit suite entry added to `qa-suites/products/dungeoncrawler/suite.json` with 19 test cases (TC-XPA-01 through TC-XPA-19) covering: XP threshold (1000 standard), XP carryover on level-up, advancement speed variants (Fast 800, Standard 1000, Slow 1200), configurable mode switching, party-wide equal XP distribution, trivial encounter 0 XP, minor accomplishment XP override, story-based leveling (XP not tracked), accomplishment tier table structure, Hero Point flags (moderate + major), creature XP source (Table 10-2 via dc-cr-encounter-creature-xp-table), hazard XP source (Table 10-14), double-XP for behind-level PCs, and failure-mode safe paths. No new routes — Security AC exemption confirmed; qa-permissions.json not updated. Suite validated OK. Committed at 9a5abb842.

## Next actions
- PM/BA to confirm: (1) exact accomplishment XP values (minor/moderate/major) for TC-XPA-11 value-correctness assertions; (2) double-XP behind-level threshold (any level gap vs 2+ levels minimum) for TC-XPA-17
- Dev implements XpAwardService and runs TC-XPA suite at Stage 4

## Blockers
- None (2 open PM notes documented in suite entry notes field; automation structure/ordering assertions active until values confirmed)

## Needs from CEO
- None

## ROI estimate
- ROI: 7
- Rationale: XP/advancement is a core session mechanic. 19 TCs covering all advancement speed variants and accomplishment tiers ensure no silent regressions in the leveling loop, which affects every campaign.
