Good — permission rule already exists and already has `feature_id`. Now standardize the 15 TCs to consistent format:

- Status: done
- Summary: Activated dc-cr-dice-system suite: existing stub had all 15 TCs (TC-DS-01 through TC-DS-15) already present but used old format (`method` key, no `suite_type`/`ac_tag`/`status`) and had stale type (`unit`) and incomplete command (`--filter=Dice` missing `tests/src/`). Standardized all 15 TCs: `method` → `class_method`, added `suite_type` (unit for service tests, functional for `DiceRollControllerTest`), `ac_tag` (EXTEND for TC-DS-01/08, NEW for remainder), and `status` (active for 14, deferred for TC-DS-15); type updated to `phpunit`; command fixed to `--filter=Dice tests/src/`. `api-dice-roll` permission rule already present in qa-permissions.json with correct `feature_id`. Suite validated OK (5 manifests). Commit: `e0e488bc`.

## Next actions
- Process remaining suite-activate items: dc-cr-difficulty-class (14 TCs already, check format)
- TC-DS-15 deferred: PM needs to confirm whether POST /dice/roll is anon-accessible or auth-required — update qa-permissions.json `api-dice-roll` rule from `ignore` to `anon=200` or `anon=403` once policy confirmed
- Process unit-test Gate 2 verification inbox items once drush eval syntax is confirmed

## Blockers
- TC-DS-15 ACL policy for POST /dice/roll unconfirmed (deferred, not blocking release)
- drush php:eval container-not-initialized error still unresolved for unit-test Gate 2 items

## Needs from Supervisor
- PM policy decision on TC-DS-15: is POST /dice/roll accessible to anonymous users, or does it require authentication? Current `api-dice-roll` rule is `ignore` (GET probe returns 405). Need explicit `anon=200` or `anon=403` to complete the ACL rule.

## Decision needed
- Should POST /dice/roll be accessible to anonymous users (game-session-level auth-gate per AC hint) or require Drupal authentication?

## Recommendation
- Default to `anon=ignore` (keep current rule) until PM confirms; this is a safe non-breaking default that doesn't introduce false positives in the audit.

## ROI estimate
- ROI: 75
- Rationale: Dice system is a core dependency for combat and skill checks — keeping its suite in consistent format ensures Stage 4 regression tooling can parse and run all 14 active TCs. TC-DS-15 policy decision is low urgency but should be resolved before Gate 4.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-035339-suite-activate-dc-cr-dice-system
- Generated: 2026-04-06T12:25:35+00:00
