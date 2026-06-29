# BA Grooming: forseti-qa-suite-fill-controller-extraction

- Project: PROJ-002 Phase 1
- Feature: forseti-qa-suite-fill-controller-extraction
- Dispatched by: pm-forseti
- Date: 2026-04-09
- ROI: 45

## Task

Expand `features/forseti-qa-suite-fill-controller-extraction/01-acceptance-criteria.md` from stub to full AC.

## Suites to cover

- `forseti-jobhunter-controller-extraction-phase1-static` — grep-based structural checks: no direct DB calls in controller, repository injected via services.yml
- `forseti-jobhunter-controller-extraction-phase1-regression` — key routes still return expected status codes after extraction

## References

- `features/forseti-jobhunter-controller-extraction-phase1/` (if exists; check `features/` dir)
- The existing populated static suite `forseti-jobhunter-controller-refactor-static` as a format reference before it is retired
- `sites/forseti/web/modules/custom/job_hunter/src/Controller/` — controller files to check

## Definition of done

- [ ] `01-acceptance-criteria.md` expanded: at least 3 ACs for static suite, 2 for regression suite
- [ ] Static ACs specify exact grep patterns and file paths
- [ ] Regression ACs specify route paths and expected status codes
- [ ] No stub placeholders remain
- [ ] Committed to HQ repo
- Agent: ba-forseti
- Status: pending
