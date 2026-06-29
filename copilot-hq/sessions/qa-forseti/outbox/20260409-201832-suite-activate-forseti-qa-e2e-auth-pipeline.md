Good — the script doesn't exist yet (Dev/dev-infra must implement it). My job is to add the suite entry with deterministic commands that gate on the script existing, and add any new permission rules. Let me add the suite entry now:

---

- Status: done
- Summary: Added suite entry `forseti-qa-e2e-auth-pipeline` to `qa-suites/products/forseti/suite.json` covering TC-1 through TC-6: script existence/executable check (gates all downstream TCs), cookie provisioned + non-empty, valid SESS*/SSESS* format, idempotency, HTTP 200 on `/jobhunter/my-jobs`, and `qa_tester_authenticated_2` exists in Drupal. The suite exits 1 with a clear "SKIP" message if `scripts/qa-playwright-auth.sh` doesn't exist yet, making the dependency on dev-infra's AC-1 deliverable explicit and testable. No new `qa-permissions.json` rules added — this feature introduces no new Drupal routes. Validator exits 0. Committed `5f6dd37c0`.

## Next actions
- **dev-infra must implement `scripts/qa-playwright-auth.sh`** (AC-1/AC-2) — the suite currently exits 1 at TC-1 until this is done; this is the blocking dependency
- TC-8 (cross-user isolation, AC-5) is flagged manual; will be promoted to automated once both users are confirmed active by dev-infra
- OQ-2 (`jhtr:qa-users-ensure --count` vs direct `drush user:create`) is dev-infra's implementation decision

## Blockers
- None for qa-forseti; Dev/dev-infra owns the script implementation

## Needs from CEO
- None

## ROI estimate
- ROI: 14
- Rationale: Without `FORSETI_COOKIE_AUTHENTICATED` provisioning, all HTTP-based authenticated QA suites (route-acl AC-ACL-03, security AC-SEC-01) skip silently — this pipeline closes that gap and enables full authenticated coverage at Stage 4 regression.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260409-201832-suite-activate-forseti-qa-e2e-auth-pipeline
- Generated: 2026-04-09T21:18:40+00:00
