# Security Remediation Plan (Phase 2)

Date: 2026-02-18  
Related assessment: `SECURITY_REVIEW_2026-02-18.md`  
Tracking source: `Issues.md` (`DCC-0331` through `DCC-0337`)

## Objective

Convert identified security findings into executable remediation tasks with explicit validation criteria and completion evidence.

## Execution Model

Each issue follows this structure:
- Scope and blast radius
- Remediation tasks
- Validation criteria (pass/fail)
- Completion evidence

---

## DCC-0331 — Hardcoded credentials in tracked runtime settings and env/config files

### Remediation tasks
1. Remove hardcoded credentials from tracked runtime files and replace with environment or external secret source lookups.
2. Ensure production and development secret sources are separated.
3. Add safe fallback behavior that fails closed when required secrets are missing.

### Validation criteria
- No live credentials or default passwords remain in tracked runtime config files.
- Application boot succeeds with secrets loaded from externalized source.
- Application boot fails with explicit non-secret error if secret is absent.

### Completion evidence
- Redacted configuration diff.
- Startup/log evidence showing secret source path used.

---

## DCC-0332 — Re-enable CSRF protection on job_hunter POST endpoints

### Remediation tasks
1. Remove `_csrf_token: FALSE` from the 5 affected POST routes.
2. Apply route-level CSRF requirements and endpoint token validation where required by custom AJAX payloads.
3. Align front-end requests to send expected CSRF token format.

### Validation criteria
- POST requests without valid CSRF tokens are rejected.
- Valid user-initiated requests with tokens succeed.
- No state-changing endpoint remains with disabled CSRF unless formally justified and documented.

### Completion evidence
- Route diff and request/response validation output for valid/invalid token cases.

---

## DCC-0333 — Remove debug/development settings from default Drupal settings paths

### Remediation tasks
1. Move development-only toggles to local-only override files.
2. Ensure default settings paths contain production-safe defaults.
3. Confirm verbose error output is disabled outside local/dev override.

### Validation criteria
- Default settings files do not force debug/verbose/dev-only behavior.
- Development behavior is only enabled from local override path.
- Error output and logging levels match environment policy.

### Completion evidence
- Config diff showing relocation of dev settings.
- Runtime confirmation of effective settings per environment.

---

## DCC-0334 — Update repository guardrails for secrets (.gitignore + CI scanning)

### Remediation tasks
1. Update `.gitignore` to cover active sensitive patterns/paths used in this repository.
2. Add CI secret scanning for push/PR gates.
3. Add baseline documentation for false-positive handling.

### Validation criteria
- New sensitive local files are blocked from tracking by default.
- CI flags high-confidence secret signatures.
- Team has documented workflow for triage/remediation of findings.

### Completion evidence
- `.gitignore` update diff.
- CI workflow and sample scan run output.

---

## DCC-0335 — Replace hardcoded DB passwords in scripts and test utilities

### Remediation tasks
1. Replace in-script credentials with env-based inputs.
2. Add required-variable checks and safe usage/help text.
3. Remove hardcoded defaults that represent valid credentials.

### Validation criteria
- Scripts do not contain embedded live credential strings.
- Scripts fail fast with clear message when env vars are missing.
- Script functionality preserved when env vars are provided.

### Completion evidence
- Script diffs and execution output with env-provided secrets.

---

## DCC-0336 — Remove embedded test credentials from NFR docs and helper code

### Remediation tasks
1. Replace static credential examples with generation/placeholder patterns.
2. Refactor helper code to use generated or injected test secrets.
3. Ensure documentation states credentials are non-static and environment-specific.

### Validation criteria
- No reusable static password values remain in active docs/code paths.
- Test helper flows still create usable accounts through non-static credentials.

### Completion evidence
- Documentation/code diff and test helper run summary.

---

## DCC-0337 — Credential rotation and history cleanup plan after secret exposure

### Remediation tasks
1. Inventory exposed secrets by system/environment.
2. Rotate credentials in prioritized order (highest privilege first).
3. Evaluate repository-history cleanup requirements and operational impact.
4. Record post-rotation verification and revoke superseded credentials.

### Validation criteria
- All identified exposed credentials are rotated/revoked.
- Systems operate with rotated credentials.
- Historical risk acceptance/cleanup decision is documented.

### Completion evidence
- Rotation checklist with timestamps and verification status.
- Post-rotation connectivity/health evidence.

---

## Gate Criteria for Phase Completion

Phase 2 is complete when:
1. `DCC-0331` through `DCC-0337` each meet all validation criteria.
2. Evidence artifacts exist for every issue.
3. Residual risks are explicitly documented and accepted by owner.
