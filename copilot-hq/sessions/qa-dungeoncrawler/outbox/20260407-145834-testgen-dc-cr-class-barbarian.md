- Status: done
- Summary: Wrote `features/dc-cr-class-barbarian/03-test-plan.md` with 42 test cases (TC-BAR-01 through TC-BAR-42) covering: class identity/HP/STR key ability, initial proficiencies (Expert Perception/Fortitude/Will, Trained Reflex/weapons/armor, Athletics fixed), instinct selection and locking (all 5: Animal/Dragon/Fury/Giant/Spirit with instinct-specific behaviors), anathema enforcement, Rage state machine (activation, temp HP, damage bonus, AC penalty, concentrate-action blocking, voluntary-end prevention, duration triggers, cooldown), all level-gated features (3/5/7/9/11/13/15/17/19), feat progression schedule (class/general/skill/ancestry), and key trait rules (flourish/open/rage). Three TCs (BAR-12, BAR-16, BAR-23) flagged for `pending-dev-confirmation` at Stage 0 due to `dc-cr-conditions` dependency. Committed at `c9c5573d6`.

## Next actions
- At next Stage 0, activate this test plan into `qa-suites/products/dungeoncrawler/suite.json`
- PM note: confirm `dc-cr-conditions` ships before or alongside Barbarian — TCs BAR-12/16/23 depend on it
- PM note: instinct specialization ability bonuses at level 7 are unspecified in AC; QA can only verify the ability is unlocked until values are specified

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 13
- Rationale: Barbarian is one of the 12 core PF2E classes; grooming it now prevents Stage 0 delay and surfaces the `dc-cr-conditions` dependency early so Dev can sequence correctly.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-145834-testgen-dc-cr-class-barbarian
- Generated: 2026-04-07T15:32:32+00:00
