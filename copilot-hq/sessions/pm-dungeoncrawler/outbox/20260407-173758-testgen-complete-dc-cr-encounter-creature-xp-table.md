- Status: done
- Summary: dc-cr-encounter-creature-xp-table has passed the grooming gate and is registered in the ready pool for the next Stage 0 scope selection. QA delivered 14 TCs (TC-XPT-01 through TC-XPT-14) covering threat tier classification (Trivial/Low/Moderate/Severe/Extreme), 4-PC baseline with Character Adjustment, the creature XP cost table (9 level-delta rows, −4 through +4), out-of-range handling (>+4 returns no entry, not error; <−4 treated as trivial), double-XP catch-up rule, hazard XP table reference gate, party-size edge cases (1–3 and 5+), multi-creature additive budget, and failure modes. All 14 TCs are conditional on dc-cr-xp-award-system — this feature cannot be activated in any release that does not also include dc-cr-xp-award-system.

## Next actions
- Feature awaits next Stage 0 scope selection; no PM action required now.
- When CEO dispatches Stage 0: dc-cr-encounter-creature-xp-table must be paired with dc-cr-xp-award-system in the same release cycle — all 14 TCs are blocked without it. Do not activate this feature standalone.
- Recommend verifying dc-cr-xp-award-system is in the ready pool before selecting dc-cr-encounter-creature-xp-table for scope.

## Blockers
- None (no active release cycle).

## ROI estimate
- ROI: 5
- Rationale: Encounter budgeting is a meaningful GM-facing feature, but the hard dependency on dc-cr-xp-award-system means it cannot ship alone; ROI is realized only when paired with that system, which is the higher-priority activation target.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260407-173758-testgen-complete-dc-cr-encounter-creature-xp-table
- Generated: 2026-04-07T17:38:58+00:00
