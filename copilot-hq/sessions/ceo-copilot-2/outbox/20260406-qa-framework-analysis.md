# CEO Outbox: QA Framework Analysis — dungeoncrawler

- Agent: ceo-copilot-2
- Generated: 2026-04-06T17:33:00+00:00
- Status: done

## Summary

Board requested a deep-dive into the QA role and testing framework. Analysis complete. Two Gate 2 BLOCKs dispatched to dev-dungeoncrawler. Four structural gaps documented below with recommended actions.

---

## Actions taken this cycle

| Item | Action | Dispatched to |
|---|---|---|
| dc-cr-difficulty-class BLOCK | Fix dispatch: routing.yml `_access: TRUE` on /rules/check | dev-dungeoncrawler inbox |
| dc-cr-equipment-system BLOCK | Fix dispatch: encumbrance formula + STR req penalty | dev-dungeoncrawler inbox |

---

## QA stack overview

| Layer | Tool | State |
|---|---|---|
| ACL/URL audit | `scripts/site-audit-run.sh` → `site-full-audit.py` | ✅ Active; ALLOW_PROD_QA=1 gate |
| Feature unit tests | `drush ev` inline PHP (Playwright workaround) | ✅ Active |
| PHPUnit | 73 test files (Functional + Unit); `dungeoncrawler_content` + `dungeoncrawler_tester` | ⚠️ Not required_for_release |
| Playwright | Referenced in `suite.json` tools | ❌ Not provisioned |
| Suite manifest | `qa-suites/products/dungeoncrawler/suite.json` | 18 suites, 17 required_for_release |

**Current production audit (20260406-170141)**: ALL PASS — 0 violations, 0 404s, 0 permission failures.

---

## Structural gaps identified

### GAP-1: No staging environment (highest impact)
**Problem**: Production IS the only environment (`dungeoncrawler.forseti.life`). All live QA requires `ALLOW_PROD_QA=1`. PHPUnit functional tests need `SIMPLETEST_BASE_URL` + a test database — neither exists. The `module-test-suite` suite entry exists but is not `required_for_release` for this reason.

**Risk**: Every live test runs against production. A bad test could affect real users. PHPUnit functional test suite (73 test files) is effectively dead code — it cannot run in CI or headlessly.

**Recommendation**: Provision a staging/test database (`dungeoncrawler_test`) + test web root, even if it's just a second vhost on the same box. This unblocks PHPUnit and reduces production test risk. Assign to pm-infra + dev-infra.

---

### GAP-2: Playwright not provisioned
**Problem**: `suite.json` lists `"tools": ["playwright", "python"]` and all e2e suites use `drush ev` inline PHP as a workaround. Playwright test scripts are defined in test plans but no runner infrastructure exists.

**Risk**: As the feature surface grows, `drush ev` one-liners will break down for UI/JS-heavy flows (dungeon map, combat UI, character creation wizard).

**Recommendation**: Add a Playwright provisioning story to pm-dungeoncrawler's roadmap. Minimum viable: Node.js + `@playwright/test` installed on the host, one headless browser pass against production. Can be a pm-infra item.

---

### GAP-3: ROI starvation of Gate 2 unit-test items (GAP-DC-GATE2-ROI-01)
**Problem**: Gate 2 unit-test inbox items are dispatched at ROI 43–56. Competing work items regularly score 84–300. Under strict ROI ordering, unit-test items are never reached — causing 3–5 session stagnation cycles requiring manual CEO intervention.

**Current workaround**: Manual CEO dispatch of clarify-escalation items.

**Recommendation**: Set a minimum floor ROI of `75` for all Gate 2 unit-test dispatch items, or implement a priority lane in the orchestrator for Gate 2 verification. This is a pm-dungeoncrawler + orchestrator/dev-infra fix. ROI of fixing the floor: high (unblocks release cycle without manual CEO intervention).

---

### GAP-4: Synthetic dispatch flood (partially mitigated)
**Problem**: Orchestrator broadcast synthetic improvement-round items to 26 seats (`--help`, `stale-test-release-id-999`, `fake-no-signoff-*`). Fast-exit guards added to QA/dev instructions, but the root fix (DATE arg validation + signoff gate in `improvement-round.sh`) is tracked in dev-infra inbox (`20260405-improvement-round-sequencing-fix`, ROI 94) and not yet deployed.

**Recommendation**: Prioritize the dev-infra orchestrator fix. Until deployed, synthetic floods will continue. The instruction-layer guards reduce waste but do not eliminate it.

---

## QA inbox status (qa-dungeoncrawler — 15 items)

| Type | Count | Action |
|---|---|---|
| clarify-escalation | 2 | qa-dungeoncrawler must rewrite outboxes with Decision/Recommendation fields |
| unit-test verification (pending) | 13 | Normal Gate 2 pipeline; no CEO action needed |

The 2 clarify-escalation items (`dc-cr-difficulty-class`, `dc-cr-equipment-system`) will be auto-resolved once dev fixes are shipped and QA re-runs Gate 2.

---

## Regression checklist open items

| Feature | Status | Blocker |
|---|---|---|
| dc-cr-difficulty-class | BLOCK | Route permission fix dispatched to dev |
| dc-cr-equipment-system | BLOCK | Encumbrance/STR fix dispatched to dev |
| dc-cr-conditions | in_progress | Combat tables created (commit 9d4d51e10); awaiting QA Gate 2 retest |
| 20260405-cr-finding-invokeModelDirect-no-fallback | open | QA targeted regression check pending |

---

## Next actions

- **dev-dungeoncrawler**: 2 fix items dispatched; complete and notify qa-dungeoncrawler for Gate 2 retest
- **pm-dungeoncrawler**: review GAP-3 (ROI floor for Gate 2 items) — recommend floor of 75
- **pm-infra + dev-infra**: GAP-1 staging env (medium-term), GAP-4 orchestrator fix (high priority, ROI 94 already tracked)
- **pm-dungeoncrawler**: add Playwright provisioning to roadmap (GAP-2)

## Blockers
- None requiring Board escalation.

## Needs from Board
- N/A
