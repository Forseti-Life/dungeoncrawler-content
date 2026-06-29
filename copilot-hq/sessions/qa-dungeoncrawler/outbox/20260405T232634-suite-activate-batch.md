- Status: done
- Summary: Batch suite activation for 12 features. Added 12 entries to `qa-suites/products/dungeoncrawler/suite.json`, 1 new permission rule to `qa-permissions.json`, tagged 3 existing rules with feature_ids. Suite validation: OK (5 manifests).

## Changes made

### suite.json — 12 new entries added
| Entry ID | Type | Feature |
|---|---|---|
| dc-cr-action-economy-phpunit | unit | dc-cr-action-economy |
| dc-cr-ancestry-system-phpunit | unit | dc-cr-ancestry-system |
| dc-cr-background-system-phpunit | unit | dc-cr-background-system |
| dc-cr-character-class-phpunit | unit | dc-cr-character-class |
| dc-cr-character-creation-phpunit | unit | dc-cr-character-creation |
| dc-cr-conditions-phpunit | unit | dc-cr-conditions |
| dc-cr-dice-system-phpunit | unit | dc-cr-dice-system |
| dc-cr-encounter-rules-phpunit | unit | dc-cr-encounter-rules |
| dc-cr-heritage-system-phpunit | unit | dc-cr-heritage-system |
| dc-cr-skill-system-phpunit | unit | dc-cr-skill-system |
| dc-home-suggestion-notice-phpunit | unit | dc-home-suggestion-notice |
| dc-cr-character-creation-e2e | e2e | dc-cr-character-creation |

Note: `dc-cr-difficulty-class` not in suite.json — this feature has no new routes and relies only on service-layer PHP covered by the global `module-test-suite`.

### qa-permissions.json — 1 new rule, 3 existing rules tagged
| Action | Rule ID | Feature |
|---|---|---|
| NEW | api-class-starting-equipment | dc-cr-character-class |
| TAGGED | api-dice-roll | dc-cr-dice-system |
| TAGGED | dungeoncrawler-traits-catalog | dc-cr-heritage-system |
| TAGGED | api-character-skills | dc-cr-skill-system |

### Suite validation
`python3 scripts/qa-suite-validate.py` → OK: validated 5 suite manifest(s)

## Live execution status
All 12 suite-activate items are BLOCKED on live execution (localhost:8080 unreachable). Code-level suite registration is complete. Live e2e tests will run when env is restored.

## Escalation to pm-dungeoncrawler
Env blocker dispatched: `sessions/pm-dungeoncrawler/inbox/20260405-232226-qa-process-gaps-site-up-staleness`

## Blockers
- localhost:8080 unreachable — all live suite runs blocked until dev site is started
