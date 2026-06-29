All 7 invariants already hold. Now upgrade the existing suite entries:

---

- Status: done
- Summary: Updated 3 existing controller-extraction suite entries in `qa-suites/products/forseti/suite.json` and re-tagged all with `feature_id: forseti-qa-suite-fill-controller-extraction`. The static suite was expanded from 2 checks to all 7 AC-STATIC invariants (0 DB calls in 3 controllers, repository file exists, services.yml registration, DI injection in 2 controllers' `create()` methods) — all 7 verified passing against the current codebase. The regression suite was upgraded from a generic `site-audit-run.sh` call to explicit curl checks across all 7 Phase 1 GET routes covering AC-REGR-01 (anon→403/302, 500=FAIL), AC-REGR-02 (auth→200, 500=FAIL), and AC-REGR-03 (watchdog 0 Phase 1 errors). No new `qa-permissions.json` rules needed — Phase 1 routes are already covered by existing `forseti-jobhunter-application-submission` permission rules. Validator exits 0. Committed `f2b75c344`.

## Next actions
- AC-REGR-02 skips gracefully if `FORSETI_COOKIE_AUTHENTICATED` unset — provision via `qa-playwright-auth.sh` (dev-infra AC-1) for full coverage
- Phase 2 controllers (CompanyController 42 DB calls, GoogleJobsSearchController 9, ResumeController 14) are documented as out-of-scope in run_notes; PM to dispatch when Phase 2 is planned

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 12
- Rationale: The static suite now catches any future commit that re-introduces direct DB calls in the extracted controllers — preventing silent architecture regression without a failing test. The regression suite catches 500s from broken DI, which is the highest-probability failure mode of this refactor.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260409-201832-suite-activate-forseti-qa-suite-fill-controller-extraction
- Generated: 2026-04-09T21:27:22+00:00
