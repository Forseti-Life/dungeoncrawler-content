- Status: blocked
- Summary: Verified REQs 2179–2189 (Actions in Encounters) for DungeonCrawler (`dungeoncrawler` site, `dungeoncrawler_content` module, feature `dc-cr-hp-dying-actions-flat-check`). 22 of 23 REQs pass. All inbox-anticipated failures are already fixed in current code: quickened/slowed/stunned action count adjustments live in `CombatEngine::startTurn()`, recovery check auto-triggers for dying characters at start of turn, and disrupted action handling (deduct cost, suppress effect) is in `ActionProcessor::executeAction()`. One MEDIUM defect found: DEF-2182 — `ActionProcessor::executeFreeAction()` always routes through `validateActionEconomy($actor, 'free')` regardless of whether the action has a trigger. PF2e REQ 2182 requires that free actions with triggers consume `reaction_available` exactly like reactions do; the current implementation allows unlimited use of triggered free abilities per turn because no trigger check is performed. A PM decision is needed on whether to treat triggered free actions as consuming the reaction slot (Option A, spec-compliant) or to accept the gap and defer (Option B). Committed `fa9495e4d`.

## Product context
- **Website/site:** dungeoncrawler (`dungeoncrawler.forseti.life`)
- **Module:** `dungeoncrawler_content` (custom Drupal module)
- **Key service:** `ActionProcessor.php` (`executeFreeAction`, `validateActionEconomy`)
- **Feature/work item:** `dc-cr-hp-dying-actions-flat-check` (Actions in Encounters system)
- **REQ range:** 2179–2189 (PF2e Core Rulebook Ch09: Actions in Encounters)

## Next actions
- PM (`pm-dungeoncrawler`) to decide fix approach for DEF-2182 (see Decision needed below)
- If Option A approved: route to dev-dungeoncrawler — add `has_trigger: bool` to `$action_data` schema; in `executeFreeAction`, when `has_trigger=TRUE`, validate and consume `reaction_available` (same path as reactions)
- If Option B accepted: record risk acceptance in feature.md and proceed

## Blockers
- **DEF-2182 (MEDIUM):** Triggered free actions don't consume the reaction slot — any triggered free ability (e.g., Shield Block, parry variants) can be used unlimited times per round, which is an exploit and breaks PF2e action economy. Fix requires a PM decision on approach.

## Decision needed
- **Fix approach for DEF-2182:** Should triggered free actions consume `reaction_available` (Option A — spec-compliant, ~10 line change in `ActionProcessor.php`, requires `has_trigger` field in action data) or should the gap be deferred/accepted as a known deviation (Option B — record risk acceptance, no code change)?

## Recommendation
- **Recommend Option A (spec-compliant fix):** The `has_trigger` bool is a small schema addition and the routing change in `executeFreeAction` is ~10 lines in dev-dungeoncrawler's owned scope. Deferring (Option B) leaves a live exploit in the reaction economy for any action marked free-with-trigger, which will produce incorrect outcomes in any encounter involving Shield Block or similar abilities. Tradeoff: Option A requires action data (in `ContentRegistry` or caller) to correctly set `has_trigger`; if any existing action incorrectly omits the field, it would silently treat itself as untriggered (fail-open). Mitigation: dev-dungeoncrawler should add a unit test confirming `has_trigger=TRUE` → reaction consumed for a known triggered ability.

## Needs from Supervisor
- Confirm fix approach: Option A (consume reaction slot for triggered free actions) or Option B (defer, record risk acceptance)

## ROI estimate
- ROI: 55
- Rationale: Action economy is the core loop of every combat turn; the triggered free action gap is a medium exploit that breaks reaction limits for triggered abilities, and the fix is small and contained within dev-dungeoncrawler's scope.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-roadmap-req-2179-2189-actions
- Clarification rewrite: 2026-04-07 (inbox: 20260406-clarify-escalation-20260406-roadmap-req-2179-2189-actions)
- Original generated: 2026-04-06T23:24:30+00:00
