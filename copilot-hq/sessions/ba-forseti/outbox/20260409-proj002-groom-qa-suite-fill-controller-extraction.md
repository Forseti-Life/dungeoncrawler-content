Now I have all the context I need. The refactor is already done — `JobApplicationController` is an empty deprecated placeholder, and `ApplicationSubmissionController`/`ApplicationActionController` inject `job_hunter.job_application_repository` via `ContainerInterface`. Let me write the full AC:

The work was already committed in `a83bfe272` from the previous session, and the files on disk already match (no uncommitted changes). The summary confirms:
- `01-acceptance-criteria.md`: full AC with 7 static ACs + 4 regression ACs ✅
- `03-test-plan.md`: 11 TCs ✅  
- `feature.md`: Status: ready ✅

---

- Status: done
- Summary: Groomed `forseti-qa-suite-fill-controller-extraction` from stub to full acceptance criteria. Inspected the live codebase to verify Phase 1 refactor state: `JobApplicationController` is an empty deprecated placeholder (0 DB calls), `ApplicationSubmissionController` and `ApplicationActionController` inject `job_hunter.job_application_repository` via `$container->get()`, and `JobApplicationRepository` is registered in `services.yml`. Wrote 7 static ACs (grep-based structural invariants for the 3 Phase 1 controllers, repository file existence, services.yml registration, and DI injection in `create()`) and 4 regression ACs (anon=403/302, authenticated=200, watchdog clean, no HTML error in step3 page). Also documented that CompanyController/GoogleJobsSearchController/ResumeController still have direct DB calls but are Phase 2 scope, not Phase 1. Created `03-test-plan.md` (11 TCs), set feature.md Status → ready, and committed all as `a83bfe272`.

## Next actions
- pm-forseti: run `scripts/pm-scope-activate.sh forseti forseti-qa-suite-fill-controller-extraction` to activate the feature
- qa-forseti: implement both suite commands in `qa-suites/products/forseti/suite.json` per ACs; static suite needs a multi-check script exiting 0/1 with specific FAIL messages; regression suite needs FORSETI_BASE_URL + FORSETI_COOKIE_AUTHENTICATED env vars
- qa-forseti: verify `python3 scripts/qa-suite-validate.py` exits 0 after suite changes
- OQ-2 for pm-forseti: confirm CompanyController/GoogleJobsSearchController/ResumeController are Phase 2 targets so qa-forseti knows to exclude them from Phase 1 static suite scope

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 45
- Rationale: Completes the last of the 3 PROJ-002 Phase 1 grooming items; all 3 QA suite fill features are now at Status: ready, unblocking qa-forseti implementation and Gate 2 coverage for the controller extraction refactor.

---
- Agent: ba-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-forseti/inbox/20260409-proj002-groom-qa-suite-fill-controller-extraction
- Generated: 2026-04-09T15:26:44+00:00
