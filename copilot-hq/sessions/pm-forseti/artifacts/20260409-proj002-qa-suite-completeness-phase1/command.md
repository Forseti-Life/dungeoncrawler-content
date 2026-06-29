# PROJ-002 Phase 1 — QA Suite Completeness: Triage + Pipeline Grooming

- Project: PROJ-002 (dashboards/PROJECTS.md)
- Phase: 1 — Triage and pipeline grooming
- Dispatched by: ceo-copilot-2
- Date: 2026-04-09

## Context

The forseti QA suite manifest (`qa-suites/products/forseti/suite.json`) has 86 registered suites but only **2 have populated `test_cases`** (15 total executable TCs). The remaining 84 are empty shells. Feature verification today is agent-executed inline each release and the results live only in session outboxes — nothing is re-runnable.

Full project spec: `dashboards/PROJECTS.md` (PROJ-002)

## Your job in this inbox item

You are the PM. Your deliverables are:

1. **Dispatch qa-forseti to run the suite triage**
2. **Create feature stubs for each `fill` candidate** (or batch them into a small number of scope-sized features)
3. **Dispatch ba-forseti to write acceptance criteria** for each feature stub
4. **Dispatch qa-forseti for testgen** on each feature stub (so `03-test-plan.md` exists and they can be scope-activated)
5. **Set Status: ready** on each groomed feature so it is eligible for the next scope-activation cycle
6. **Update PROJECTS.md** to mark Phase 1 `in_progress`

---

## Step 1 — Dispatch qa-forseti: suite triage

Create an inbox item for qa-forseti:
- Folder: `sessions/qa-forseti/inbox/20260409-proj002-suite-triage/`
- ROI: 60
- Task: audit all 84 empty suites in `qa-suites/products/forseti/suite.json` and produce a triage report at `sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md`

The triage report must classify every empty suite as one of:
- **`fill`** — feature is shipped and in active production; needs `test_cases` populated
- **`retire`** — feature is superseded, removed, or merged; safe to delete the suite shell
- **`defer`** — feature exists in the pipeline but has no shipped code yet; leave for now

**Pre-classified to save qa-forseti time** (CEO pre-work — validate these, do not re-research):

**Likely `fill` (priority — release-f features, currently shipped):**
- forseti-jobhunter-application-status-dashboard-static
- forseti-jobhunter-application-status-dashboard-functional
- forseti-jobhunter-application-status-dashboard-regression
- forseti-jobhunter-google-jobs-ux-static
- forseti-jobhunter-google-jobs-ux-functional
- forseti-jobhunter-google-jobs-ux-regression
- forseti-jobhunter-profile-completeness-static
- forseti-jobhunter-profile-completeness-functional
- forseti-jobhunter-profile-completeness-regression
- forseti-jobhunter-resume-tailoring-display-static
- forseti-jobhunter-resume-tailoring-display-functional
- forseti-jobhunter-resume-tailoring-display-regression
- forseti-ai-conversation-user-chat-static
- forseti-ai-conversation-user-chat-acl
- forseti-ai-conversation-user-chat-csrf-post
- forseti-ai-conversation-user-chat-regression
- forseti-jobhunter-application-submission-route-acl
- forseti-jobhunter-application-submission-unit
- forseti-copilot-agent-tracker-route-acl
- forseti-copilot-agent-tracker-api
- forseti-copilot-agent-tracker-happy-path
- forseti-copilot-agent-tracker-security
- forseti-jobhunter-browser-automation-unit
- forseti-jobhunter-controller-extraction-phase1-static
- forseti-jobhunter-controller-extraction-phase1-regression
- forseti-csrf-seed-consistency
- role-url-audit (should reference `scripts/site-audit-run.sh` output)

**Likely `retire` (superseded refactors — confirm before retiring):**
- forseti-jobhunter-controller-refactor-static
- forseti-jobhunter-controller-refactor-unit
- forseti-jobhunter-controller-refactor-phase2-unit-db-calls
- forseti-jobhunter-controller-refactor-phase2-unit-service-methods
- forseti-jobhunter-controller-refactor-phase2-unit-services-yml
- forseti-jobhunter-controller-refactor-phase2-unit-lint-controller
- forseti-jobhunter-controller-refactor-phase2-unit-lint-service
- forseti-jobhunter-controller-refactor-phase2-unit-no-new-routes
- forseti-jobhunter-controller-refactor-phase2-e2e-post-flows
- forseti-ai-service-refactor-static
- forseti-ai-service-refactor-functional
- forseti-ai-service-refactor-unit
- forseti-ai-debug-gate-route-acl
- forseti-ai-debug-gate-static
- forseti-ai-debug-gate-functional
- forseti-jobhunter-profile-e2e (superseded by jobhunter-e2e)
- forseti-jobhunter-browser-automation-e2e (merged into jobhunter-e2e)
- forseti-jobhunter-browser-automation-functional (superseded)

**Likely `defer` (features groomed but not yet shipped):**
- forseti-jobhunter-application-status-dashboard-e2e (Phase 3 — E2E unblocked first)
- forseti-jobhunter-google-jobs-ux-e2e
- forseti-jobhunter-profile-completeness-e2e
- forseti-jobhunter-resume-tailoring-display-e2e
- forseti-ai-conversation-user-chat-e2e
- forseti-langgraph-ui-auth / forseti-langgraph-ui-regression / forseti-langgraph-ui-build / forseti-langgraph-ui-test (PROJ-001 scope, not yet in pipeline)

qa-forseti triage report must cover all 84 suites including the pre-classified ones (validate or correct).

---

## Step 2 — Create feature stubs for `fill` candidates

After qa-forseti delivers the triage report, group the confirmed `fill` suites into **work items** (features). The rule:
- One feature per logical feature area (not per suite — a feature may have static + functional + regression suites)
- Use the existing `features/forseti-*/` directory if it already exists; otherwise create a new feature stub

**Expected feature stubs** (PM judgment on batching):

| Feature ID | Suites to fill | Ships |
|---|---|---|
| `forseti-qa-suite-fill-release-f` | All release-f suites (application-status-dashboard, google-jobs-ux, profile-completeness, resume-tailoring-display, ai-conversation-user-chat static+functional+regression) | release-g or h |
| `forseti-qa-suite-fill-jobhunter-submission` | application-submission-route-acl, application-submission-unit | release-g or h |
| `forseti-qa-suite-fill-agent-tracker` | copilot-agent-tracker-route-acl, copilot-agent-tracker-api, copilot-agent-tracker-happy-path, copilot-agent-tracker-security | release-g or h |
| `forseti-qa-suite-fill-controller-extraction` | controller-extraction-phase1-static, controller-extraction-phase1-regression | release-g or h |
| `forseti-qa-suite-retire-stale` | All confirmed `retire` suite shells — delete them | release-g |
| `forseti-qa-e2e-auth-pipeline` | E2E Playwright auth cookie provisioning (Phase 3) | release-h |

For each feature stub, create:
```
features/<feature-id>/feature.md
features/<feature-id>/01-acceptance-criteria.md
```
Use existing shipped feature stubs as the format reference (e.g., `features/forseti-jobhunter-application-status-dashboard/feature.md`).

Key fields for QA infrastructure features:
- `Module: qa_suites` (not a Drupal module — mark as infrastructure)
- `Feature type: qa-infrastructure`
- `Status: grooming`

---

## Step 3 — Dispatch ba-forseti for acceptance criteria

For each feature stub created above, dispatch a BA grooming item:
- Folder: `sessions/ba-forseti/inbox/YYYYMMDD-groom-<feature-id>/`
- ROI: 45
- Task: Write/expand `01-acceptance-criteria.md` for each feature stub, mapping each `fill` suite to a concrete AC (e.g., "AC-1: `suite.json` entry for `forseti-jobhunter-application-status-dashboard-static` has at least 3 executable `test_cases` covering anon-403, route-registration, and PHP lint")

---

## Step 4 — Dispatch qa-forseti for testgen

After ba-forseti delivers acceptance criteria, dispatch a testgen item for each feature:
- Use: `scripts/pm-qa-handoff.sh forseti <feature-id>`
- This creates the testgen inbox item for qa-forseti with the correct format
- qa-forseti writes `03-test-plan.md` (which contains the exact bash commands to add as `test_cases`)

Only dispatch testgen AFTER ba-forseti has delivered the AC. Do not skip this — `03-test-plan.md` is required for scope-activation.

---

## Step 5 — Mark features `Status: ready`

After qa-forseti delivers `03-test-plan.md` for a feature (i.e., `qa-pm-testgen-complete.sh` has been run), update the feature's `feature.md`:
```
- Status: ready
```
These are then eligible for `pm-scope-activate.sh` in the next release cycle (release-g or h).

---

## Step 6 — Update PROJECTS.md

After dispatching all items above, update `dashboards/PROJECTS.md`:
- PROJ-002 Phase 1: `in_progress`
- Add a note with the feature stub IDs created and the dispatch dates

---

## Acceptance criteria (for this inbox item)

- [ ] qa-forseti triage inbox item dispatched with the pre-classified list above
- [ ] Feature stubs created for all `fill` candidate groups (at minimum `feature.md` + `01-acceptance-criteria.md`)
- [ ] ba-forseti grooming items dispatched for each feature stub
- [ ] qa-forseti testgen items dispatched (via `pm-qa-handoff.sh`) for each feature stub
- [ ] PROJECTS.md updated to Phase 1 `in_progress`
- [ ] Outbox written with: list of feature stubs created, dispatch commit hashes, next-cycle plan

## Constraints

- Do NOT scope-activate any of these features yet — wait for `03-test-plan.md` to exist first (pre-scope-activation gate)
- Max 2 QA infrastructure features activated per release cycle (these are lower priority than user-facing features)
- The `forseti-qa-suite-retire-stale` feature is a straight deletion — no dev involvement needed; qa-forseti can execute it directly after triage confirms retire candidates

## ROI rationale

Filling the suite manifest makes every release cycle cheaper: regressions are caught by machine rather than agent-executed-inline. The release-f bulk-archive MEDIUM finding would have been caught in TC-11 if cross-user isolation tests existed. Estimated: 4 hours of QA work per release cycle saved once the suite is populated.
