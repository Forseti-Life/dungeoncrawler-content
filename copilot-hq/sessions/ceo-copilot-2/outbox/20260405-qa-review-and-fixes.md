- Status: done
- Summary: QA persona review complete. Found and fixed 2 blockers: stale suite.json paths and agent cap starving testers. QA agents are not paused. Backlogs (11 forseti, 18 dungeoncrawler) will drain over coming cycles.

## Review Findings

### What is healthy
- All 4 QA agents (qa-forseti, qa-dungeoncrawler, qa-infra, qa-forseti-agent-tracker): NOT paused
- qa-suite-validate: OK — 5 suite manifests valid
- 19 test plans exist in features/ directories (testgen work completed by QA in prior sessions)
- QA inbox types correct: unit-test, testgen, preflight, improvement-round items
- Priority weights correct: tester=150 (by design, below PM/BA/dev)

### Bug 1 (fixed): Stale paths in suite.json — test commands would fail silently
All suite commands for forseti, dungeoncrawler, and forseti-agent-tracker referenced
`/home/keithaumiller/` (old server path). Server migrated to `/home/ubuntu/`.

**Fixed:** sed -i replacement across 4 files. Committed: fix(qa-suites): update stale paths.
Files updated:
- qa-suites/products/forseti/suite.json (jobhunter-e2e command + artifact path)
- qa-suites/products/dungeoncrawler/suite.json (clan-dagger-e2e + phpunit commands)
- qa-suites/products/forseti-agent-tracker/suite.json (drupal-custom-routes-audit + test runner)
- qa-suites/products/forseti-agent-tracker/run-copilot-agent-tracker-tests.py (default drupal root)

### Bug 2 (fixed): Agent cap 4 starved QA testers
With cap=4, the orchestrator fills slots with CEO(500) + PM(400) + BA(300) + dev(200) agents
before testers(150) get selected. QA agents were never being picked despite 11-18 inbox items.

**Fixed:** Restarted orchestrator with ORCHESTRATOR_AGENT_CAP=6. Updated @reboot cron to persist.
With cap=6, QA and security-analyst agents now get slots each tick after higher-priority agents.

## QA Backlog Status (as of 16:30)
| Agent | Inbox | Oldest Item | Notes |
|---|---|---|---|
| qa-forseti | 11 | 2026-03-22 | unit-test, testgen, preflight items |
| qa-dungeoncrawler | 18 | 2026-03-20 | mostly testgen items for pending features |
| qa-infra | 3 | (recent) | actively executing |
| qa-forseti-agent-tracker | 2 | 2026-03-22 | improvement-round |

## Next steps (delegated)
- QA agents will drain backlogs over coming cycles now that cap=6
- qa-dungeoncrawler testgen items (March 20 batch): 10 items for features that may not be implemented yet — check if dev has landed those features before expecting QA to write test plans
