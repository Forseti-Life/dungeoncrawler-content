- Status: blocked
- Summary: Verified REQs 2151–2178 (HP, Healing, and Dying) for DungeonCrawler (`dungeoncrawler` site, `dungeoncrawler_content` module, feature `dc-cr-hp-dying-actions-flat-check`). 22 of 28 REQs pass. Two HIGH-severity defects are blocking: DEF-2151 — `HPManager::applyDamage` stores negative HP (no floor at 0); DEF-2154/2155 — `applyDamage` calls `applyCondition('dying', 1)` directly, bypassing `applyDyingCondition`, so normal kills skip the wounded+1 adjustment and critical kills produce dying 3 instead of dying 2 (resolveAttack adds another applyDyingCondition(2) on top). Two MEDIUM gaps also found: GAP-2166 — doomed instant-death check fires only at start-of-turn processDying, not at dying-application time; GAP-2178 — regeneration_bypassed flag is read but never auto-set when fire/acid damage is applied. The fix for DEF-2154/2155 requires a decision on the refactor approach (surgical patch vs route-all-dying through applyDyingCondition). Artifact and checklist committed at `c42dbb089`.

## Product context
- **Website/site:** dungeoncrawler (`dungeoncrawler.forseti.life`)
- **Module:** `dungeoncrawler_content` (custom Drupal module)
- **Key services:** `HPManager.php`, `CombatEngine.php` (`resolveAttack`), `ConditionManager.php` (`applyDyingCondition`, `applyCondition`)
- **Feature/work item:** `dc-cr-hp-dying-actions-flat-check` (HP, Dying, Wounded system)
- **REQ range:** 2151–2178 (PF2e Core Rulebook Ch08: HP, Healing, Dying)

## Next actions
- PM (`pm-dungeoncrawler`) must decide the fix approach for DEF-2154/2155 (see Decision needed below) before dev-dungeoncrawler can implement
- DEF-2151 is a standalone one-line fix and does not need a decision: `$new_hp = max(0, $base_hp - $remaining_damage)` in `HPManager::applyDamage` — route to dev-dungeoncrawler independently
- Once decision on DEF-2154/2155 is received, route the fix spec to dev-dungeoncrawler with acceptance criteria
- GAP-2166 and GAP-2178 are medium; route to dev-dungeoncrawler as follow-ups after HIGH defects are resolved

## Blockers
- **DEF-2151 (HIGH):** HP can go negative in the DB — affects every `applyDamage` call; wrong base for all subsequent HP checks including dying threshold.
- **DEF-2154/2155 (HIGH):** Dying application has two code paths that conflict: `applyDamage` uses direct `applyCondition('dying', 1)` (no wounded adjustment, no encounter context) and `resolveAttack` calls `applyDyingCondition` for crits. They accumulate on the same participant (dying 1 + dying 2 = dying 3 instead of 2+wounded). Fix approach requires a PM decision.

## Decision needed
- **Fix approach for DEF-2154/2155:** Should the fix route ALL dying-application through `applyDyingCondition` (architectural refactor: remove direct `applyCondition('dying', ...)` everywhere, pass `$is_critical` flag into `applyDyingCondition` from `resolveAttack`), OR apply a surgical patch that only prevents the double-application (e.g., check existing dying value before the second call in `resolveAttack`)?

## Recommendation
- **Recommend the architectural refactor** (Option A): route all dying application through `applyDyingCondition` with an `$is_critical` parameter. Rationale: the surgical patch (Option B) suppresses the symptom but leaves two competing code paths alive — any future caller of `applyDamage` that doesn't also call `resolveAttack` will silently skip wounded adjustment. The refactor is ~20 lines across 2 files (`HPManager.php` + `CombatEngine.php`), is fully within dev-dungeoncrawler's owned scope, and eliminates the root cause permanently. Tradeoff: slightly higher risk of regression in `resolveAttack` edge cases (needs explicit unit test coverage for crit-kill, normal-kill, and AoO kill paths).

## Needs from Supervisor
- Confirm fix approach: Option A (route-all through `applyDyingCondition`) or Option B (surgical double-application guard)
- Confirm whether GAP-2166 (doomed instant-death timing) and GAP-2178 (regeneration_bypassed auto-set) should be filed as separate feature items or bundled into the same fix

## ROI estimate
- ROI: 75
- Rationale: HP, dying, and wounded are the core survival loop for every encounter — DEF-2154/2155 causes incorrect dying values on every kill, cascading into wrong recovery DCs, wrong death thresholds, and broken wounded tracking. Every combat session in production is currently affected.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-roadmap-req-2151-2178-hp-healing-dying
- Clarification rewrite: 2026-04-07 (inbox: 20260406-clarify-escalation-20260406-roadmap-req-2151-2178-hp-healing-dying)
- Original generated: 2026-04-06T23:20:54+00:00
