# Test Plan — PROJ-009 Open Source Initiative

**Feature:** `forseti-open-source-initiative`  
**Project:** `PROJ-009`  
**Validation target:** frozen `drupal-ai-conversation` publication candidate

## Test objective

Verify that the first public candidate is actually safe and reproducible as a frozen artifact, not just promising in planning docs.

## Validation stages

### Stage 1 — Freeze packet completeness

Checks:

1. Frozen repo/archive path is provided
2. Frozen commit SHA is provided
3. Packaging decision is explicit
4. Public docs bundle is present
5. Sanitized config/env examples are present
6. Supported version matrix is present
7. Intentional delta note is present

Expected:

- QA can identify one exact candidate packet to validate

### Stage 2 — Security/boundary checks

Checks:

1. Candidate does not include `sessions/**`
2. Candidate does not include `inbox/**`
3. Candidate does not include `tmp/**`
4. Candidate does not include `prod-config/**`
5. Candidate does not include `database-exports/**`
6. Candidate does not include keys or literal credentials
7. Candidate does not retain the known candidate-local NO-GO findings

Expected:

- Boundary matches the documented exclusion policy

### Stage 3 — CI baseline

Checks:

1. Packaging/lint baseline passes on frozen commit
2. Module test baseline passes on frozen commit
3. Supported-version matrix jobs are green
4. Secret/sanitization scan for extracted repo is green

Expected:

- No required CI job is red for the frozen commit

### Stage 4 — Clean-machine install validation

Checks:

1. Clean Ubuntu lane: clone + dependency metadata validation
2. Clean Drupal 10 lane: module enables, updates run, caches rebuild
3. Clean Drupal 11 lane: same as Drupal 10

Expected:

- No fatal errors in supported install lanes

### Stage 5 — Functional/public-safety smoke

Checks:

1. Documented routes still exist and behave as documented
2. Authenticated flows work
3. Anonymous/protected route boundaries hold
4. Public config examples are sufficient to boot the module
5. No private HQ-only behavior remains in the public package

Expected:

- Frozen candidate matches public README/config story

## APPROVE criteria

- Freeze packet complete
- Security/boundary checks pass
- Required CI jobs are green
- Supported clean-machine install lanes pass
- Functional/public-safety smoke passes
- No secrets or org-only runtime coupling remain

## BLOCK criteria

- Candidate differs from what CI validated
- Missing freeze packet elements
- Private paths or credentials remain
- Candidate-local NO-GO findings remain
- Docs/config examples are incomplete
- Any required supported install lane fails

## Evidence to capture

- frozen path/archive
- frozen commit SHA
- CI run URL or artifact bundle
- install logs for Drupal 10 and 11
- route/auth smoke outputs
- boundary review notes
- final APPROVE/BLOCK report
