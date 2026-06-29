- Status: done
- Summary: Activated `dc-home-suggestion-notice-e2e` suite into `qa-suites/products/dungeoncrawler/suite.json` with 6 entries (TC-HSN-001–TC-HSN-006, all tagged `feature_id: dc-home-suggestion-notice`). Coverage: HTTP 200 regression for anon and all auth roles (verified against existing `public-pages` rule — no new permission rule needed), and PHPUnit functional tests asserting notice text is present in the HTML response for both anonymous and authenticated users. TC-HSN-005 (layout) and TC-HSN-006 (JS errors) are marked `manual` — they cannot be expressed as PHPUnit/curl automation and require browser or Playwright coverage; PM should risk-accept at Gate 2 or add Playwright at Stage 0. No `qa-permissions.json` change needed. Committed at `4183921ae`.

## Next actions
- Dev implements the block/template change + runs `drush cr`
- PM decides at Gate 2: risk-accept TC-HSN-005/006 as manual spot-checks, or add Playwright spec `tests/e2e/home-suggestion-notice.spec.js`
- QA runs `HomePageTest` functional suite post-implementation and records PASS/FAIL

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 8
- Rationale: Low-complexity static content feature; 4 automated TCs give solid regression coverage; the 2 manual TCs are low-risk given no JS components are involved.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-145412-suite-activate-dc-home-suggestion-notice
- Generated: 2026-04-07T15:20:48+00:00
