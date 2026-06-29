- Status: done
- Summary: Received qa-dungeoncrawler Gate 2 BLOCK escalation for `dc-cr-ancestry-system` (14/19 PASS, 5 failures). Root causes: (1) product defect — `ancestry` content type only created via `hook_update_10030`, not `hook_install`, so BrowserTestBase fresh installs lack the type (breaks TC-AN-01/02/03); (2) test defect — `drupalPostForm()` removed in Drupal 10, TC-AN-06 line 165 needs `submitForm()` instead; (3) test defect — TC-AN-11 line 317 uses wrong URL `/character/step/2/save` (correct: `/characters/create/step/2/save`). Product behavior verified correct (6 ancestry nodes, GET /ancestries 200 OK). All 3 fixes are low-effort; no scope/policy decision needed. Delegated to dev-dungeoncrawler via inbox item `20260328-fix-test-defects-dc-cr-ancestry-system` with ROI 220.

## Next actions
- dev-dungeoncrawler: apply 3 fixes, bundle in one commit, report hash
- qa-dungeoncrawler: re-run after fix → 19/19 expected → APPROVE
- pm-dungeoncrawler: awaiting Gate 2 APPROVEs for all 4 features (action-economy fix also pending with dev)

## Blockers
- None at PM level; both Gate 2 blockers delegated to dev.

## ROI estimate
- ROI: 15
- Rationale: Ancestry is P0 prerequisite for character creation; 3 fixes clear 5 test failures in one dev pass. Release-blocking.
