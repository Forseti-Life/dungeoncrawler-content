Now I have everything I need. Let me implement all 5 changes in parallel:

Now refactor the persistent damage flat check in CombatEngine:

Now add hidden/concealed flat checks to RulesEngine.validateAttack. I need to check what the constructor looks like to see if Calculator is available:

RulesEngine doesn't have Calculator injected. Let me check what services.yml says for RulesEngine and how CombatEngine has access to Calculator:

I need to inject Calculator into RulesEngine. Let me add it to the constructor and services.yml, then update validateAttack:

Now add the hidden/concealed flat check enforcement at the end of validateAttack (before the final return):

Now add the `getActiveConditionTypes` helper to RulesEngine (it needs to query DB for active conditions). Let me find where the class ends:

Now update services.yml to inject Calculator into RulesEngine:

Now check what service ID Calculator is registered as:

Good, `dungeoncrawler_content.calculator` is correct. Now check if there's a circular dependency — RulesEngine uses Calculator, and Calculator doesn't use RulesEngine:

No circular dependency. Now wire Fortune/Misfortune through ConditionManager. Let me check what condition flags exist:

I'll add a `getFortuneFlags()` method to ConditionManager that returns `['has_fortune', 'has_misfortune']` based on active conditions:

Now lint everything:

All acceptance criteria verified. Now update dc_requirements and commit:

---

- Status: done
- Summary: Implemented the full PF2E flat check system across 5 files. Added `rollFlatCheck($dc, $options)` to `Calculator.php` handling DC bounds (≤1 auto-success, ≥21 auto-failure), fortune (take higher of two rolls), misfortune (take lower), fortune+misfortune cancellation to single roll, and secret check flag (omits roll value from response). Refactored `CombatEngine.php` persistent damage to use `rollFlatCheck(15)` with assisted DC 10 support. Updated `RulesEngine.php` to accept an injected `Calculator`, added `getActiveConditionTypes()` helper, and enforced hidden (DC 11) and concealed (DC 5) flat checks inside `validateAttack()` — failed checks return an invalid result with reason. Added `getFortuneFlags()` to `ConditionManager` to expose fortune/misfortune condition state to callers. Updated services.yml to inject calculator into rules_engine. All 6 dc_requirements (2102–2107) marked implemented. All acceptance criteria verified via drush.

## Next actions
- Callers of `rollSkillCheck()` and `rollSavingThrow()` should pass `getFortuneFlags()` results into options when fortune/misfortune conditions are relevant (follow-on wiring)
- QA: verify hidden/concealed attack path and persistent damage flat check via combat test scenario

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 35
- Rationale: Flat checks underpin hidden/concealed combat, persistent conditions, and fortune/misfortune — all high-frequency gameplay mechanics. This unblocks correct combat resolution for a broad set of encounters.

**Commit:** `313d192b2`

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-impl-flat-check-system
- Generated: 2026-04-06T20:51:26+00:00
