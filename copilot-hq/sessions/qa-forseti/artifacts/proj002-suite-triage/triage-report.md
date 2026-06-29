# PROJ-002 Suite Triage Report

Date: 2026-04-09
Auditor: qa-forseti

## Summary

- Total suite entries: 94
- Entries with test_cases filled: 2
- Entries without test_cases (audited): 92
- **fill** (shipped feature, needs test_cases written): 52
- **retire** (superseded/refactor-era, no regression value): 18
- **defer** (in_progress or Playwright-only): 18
- **keep-as-is** (have command, no test_cases needed — validated correct): 4

---

## Pre-classification validation

### CEO pre-class: `fill` — validation results

| suite_id | verdict | notes |
|---|---|---|
| forseti-jobhunter-application-status-dashboard-static | ✅ fill | feature.md: shipped. Module active. |
| forseti-jobhunter-application-status-dashboard-functional | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-application-status-dashboard-regression | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-google-jobs-ux-static | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-google-jobs-ux-functional | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-google-jobs-ux-regression | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-profile-completeness-static | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-profile-completeness-functional | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-profile-completeness-regression | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-resume-tailoring-display-static | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-resume-tailoring-display-functional | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-resume-tailoring-display-regression | ✅ fill | feature.md: shipped. |
| forseti-ai-conversation-user-chat-static | ✅ fill | feature.md: shipped. Has command but no test_cases array. |
| forseti-ai-conversation-user-chat-acl | ✅ fill | feature.md: shipped. Has command. |
| forseti-ai-conversation-user-chat-csrf-post | ✅ fill | feature.md: shipped. Has command. |
| forseti-ai-conversation-user-chat-regression | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-application-submission-route-acl | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-application-submission-unit | ✅ fill | feature.md: shipped. |
| forseti-copilot-agent-tracker-route-acl | ✅ fill | feature.md: shipped. Routes confirmed at /admin/reports/copilot-agent-tracker. |
| forseti-copilot-agent-tracker-api | ✅ fill | feature.md: shipped. |
| forseti-copilot-agent-tracker-happy-path | ✅ fill | feature.md: shipped. |
| forseti-copilot-agent-tracker-security | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-browser-automation-unit | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-controller-extraction-phase1-static | ✅ fill | feature.md: shipped. |
| forseti-jobhunter-controller-extraction-phase1-regression | ✅ fill | feature.md: shipped. |
| forseti-csrf-seed-consistency | ✅ fill | No feature.md but CSRF baseline tracking is an active QA concern. |
| role-url-audit | ✅ fill | Active site-wide audit suite; has command but no test_cases. |

### CEO pre-class: `retire` — validation results

| suite_id | verdict | notes |
|---|---|---|
| forseti-jobhunter-controller-refactor-static | ✅ retire | feature.md: shipped; superseded by extraction-phase1. |
| forseti-jobhunter-controller-refactor-unit | ✅ retire | Same as above. |
| forseti-jobhunter-controller-refactor-phase2-unit-db-calls | ✅ retire | feature.md: shipped; refactor done; extraction-phase1 supersedes. |
| forseti-jobhunter-controller-refactor-phase2-unit-service-methods | ✅ retire | Same. |
| forseti-jobhunter-controller-refactor-phase2-unit-services-yml | ✅ retire | Same. |
| forseti-jobhunter-controller-refactor-phase2-unit-lint-controller | ✅ retire | Same. |
| forseti-jobhunter-controller-refactor-phase2-unit-lint-service | ✅ retire | Same. |
| forseti-jobhunter-controller-refactor-phase2-unit-no-new-routes | ✅ retire | Same. |
| forseti-jobhunter-controller-refactor-phase2-e2e-post-flows | ✅ retire | Same; e2e post-flows still valid but covered by jobhunter-e2e. |
| forseti-ai-service-refactor-static | ✅ retire | feature.md: shipped; db-refactor suite supersedes. |
| forseti-ai-service-refactor-functional | ✅ retire | Same. |
| forseti-ai-service-refactor-unit | ✅ retire | Same. |
| forseti-ai-debug-gate-route-acl | ✅ retire | feature.md: shipped; debug gate was temporary dev tooling. Route no longer exposed publicly. |
| forseti-ai-debug-gate-static | ✅ retire | Same. |
| forseti-ai-debug-gate-functional | ✅ retire | Same. |
| forseti-jobhunter-profile-e2e | ✅ retire | Superseded by jobhunter-e2e (profile tests merged in). |
| forseti-jobhunter-browser-automation-e2e | ✅ retire | Merged into jobhunter-e2e per CEO note. |
| forseti-jobhunter-browser-automation-functional | ✅ retire | Superseded per CEO note. |

### CEO pre-class: `defer` — validation results

| suite_id | verdict | notes |
|---|---|---|
| forseti-jobhunter-application-status-dashboard-e2e | ✅ defer | Playwright-only; unavailable. Has skip_reason pattern from similar entries. |
| forseti-jobhunter-google-jobs-ux-e2e | ✅ defer | Playwright-only; unavailable. |
| forseti-jobhunter-profile-completeness-e2e | ✅ defer | Playwright-only; unavailable. |
| forseti-jobhunter-resume-tailoring-display-e2e | ✅ defer | Playwright-only; unavailable. |
| forseti-ai-conversation-user-chat-e2e | ✅ defer | Already status:skipped in suite.json. |
| forseti-langgraph-ui-auth | ✅ defer | feature.md: shipped (release-g). Routes exist (/admin/reports/copilot-agent-tracker/langgraph). Auth requires admin cookie — unavailable in current QA env. |
| forseti-langgraph-ui-regression | ✅ defer | Same — admin-auth required. |
| forseti-langgraph-ui-build | ✅ defer | Same. |
| forseti-langgraph-ui-test | ✅ defer | Same. |

---

## Remaining suites not pre-classified (QA audit)

| suite_id | disposition | reason |
|---|---|---|
| jobhunter-e2e | fill | Core Playwright E2E suite for job_hunter module; has command; active regression anchor. |
| forseti-csrf-fix-route-acl | fill | feature.md: shipped (release-b). CSRF fix shipped; ACL suite valid ongoing. |
| forseti-csrf-fix-functional | fill | Same. |
| forseti-jobhunter-profile-refactor-static | retire | feature.md: shipped (release-b). Profile refactor done; extraction-phase suites supersede. |
| forseti-copilot-agent-tracker-payload-validation | fill | feature.md: shipped. Payload validation is ongoing correctness concern. |
| forseti-copilot-agent-tracker-install | fill | feature.md: shipped. Install/schema check valid regression anchor. |
| forseti-ai-service-db-refactor-static | fill | feature.md: shipped. DB refactor shipped; static checks (services.yml, SQL calls) are evergreen. |
| forseti-ai-service-db-refactor-functional | fill | Same. |
| forseti-ai-service-db-refactor-regression | fill | Same. |
| forseti-csrf-post-routes-fix-static | fill | feature.md: shipped. CSRF split-route pattern is evergreen; static regression valid. |
| forseti-csrf-post-routes-fix-functional | fill | Same. |
| forseti-csrf-post-routes-fix-regression | fill | Same. |
| forseti-agent-tracker-dashboard-controller-db-extraction-static | fill | feature.md: shipped. DB extraction complete; static lint/service checks valid. |
| forseti-agent-tracker-dashboard-controller-db-extraction-functional | fill | Same. |
| forseti-agent-tracker-dashboard-controller-db-extraction-regression | fill | Same. |
| forseti-jobhunter-profile-form-db-extraction-static | fill | feature.md: shipped. |
| forseti-jobhunter-profile-form-db-extraction-functional | fill | Same. |
| forseti-jobhunter-profile-form-db-extraction-regression | fill | Same. |
| forseti-jobhunter-resume-tailoring-queue-hardening-static | fill | feature.md: shipped. Queue hardening shipped; static + functional checks valid. |
| forseti-jobhunter-resume-tailoring-queue-hardening-functional | fill | Same. |
| forseti-jobhunter-resume-tailoring-queue-hardening-regression | fill | Same. |
| forseti-jobhunter-profile-form-static-db-extraction-static | fill | feature.md: shipped. |
| forseti-jobhunter-profile-form-static-db-extraction-functional | fill | Same. |
| forseti-jobhunter-profile-form-static-db-extraction-regression | fill | Same. |
| forseti-jobhunter-application-controller-db-extraction-static | fill | feature.md: shipped. |
| forseti-jobhunter-application-controller-db-extraction-functional | fill | Same. |
| forseti-jobhunter-application-controller-db-extraction-regression | fill | Same. |
| forseti-jobhunter-application-controller-split-static | fill | feature.md: shipped. |
| forseti-jobhunter-application-controller-split-functional | fill | Same. |
| forseti-jobhunter-application-controller-split-regression | fill | Same. |
| forseti-jobhunter-controller-extraction-phase1-functional | fill | feature.md: shipped. (CEO pre-classified static+regression; functional also fill.) |
| forseti-jobhunter-cover-letter-display-static | defer | feature.md: in_progress. Routes exist; static PASS already confirmed this cycle. Becomes fill after Dev ships. |
| forseti-jobhunter-cover-letter-display-acl | defer | Same — in_progress. |
| forseti-jobhunter-cover-letter-display-csrf-generate | defer | Same — in_progress. |
| forseti-jobhunter-cover-letter-display-e2e | defer | in_progress + Playwright unavailable. |
| forseti-jobhunter-interview-prep-static | defer | feature.md: in_progress. Routes not yet built. |
| forseti-jobhunter-interview-prep-acl | defer | Same. |
| forseti-jobhunter-interview-prep-csrf-save | defer | Same. |
| forseti-jobhunter-interview-prep-e2e | defer | in_progress + Playwright unavailable. |

---

## Corrections to CEO pre-classifications

| suite_id | CEO pre-class | Actual | Correction |
|---|---|---|---|
| forseti-jobhunter-controller-extraction-phase1-functional | (not pre-classified) | fill | Not in CEO list but same feature as -static/-regression (both fill); functional is also fill. |
| forseti-jobhunter-cover-letter-display-* (4 suites) | (not pre-classified) | defer | These are new release-g suites added this cycle; feature in_progress. |
| forseti-jobhunter-interview-prep-* (4 suites) | (not pre-classified) | defer | Same as above. |
| forseti-csrf-fix-route-acl | (not pre-classified) | fill | CSRF fix shipped release-b; ACL suite is valid ongoing. |
| forseti-csrf-fix-functional | (not pre-classified) | fill | Same. |
| forseti-jobhunter-profile-refactor-static | (not pre-classified) | retire | Profile refactor superseded by extraction-phase suites. |
| forseti-copilot-agent-tracker-payload-validation | (not pre-classified) | fill | Shipped feature; payload validation is ongoing. |
| forseti-copilot-agent-tracker-install | (not pre-classified) | fill | Shipped feature; install/schema regression. |
| forseti-langgraph-ui-* (4 suites) | defer (✅ confirmed) | defer | Routes exist but admin-auth required; unavailable in QA env. |

---

## Confirmed fill list (52 suites)

```
role-url-audit
jobhunter-e2e
forseti-jobhunter-application-submission-route-acl
forseti-jobhunter-application-submission-unit
forseti-jobhunter-browser-automation-unit
forseti-csrf-fix-route-acl
forseti-csrf-fix-functional
forseti-copilot-agent-tracker-route-acl
forseti-copilot-agent-tracker-api
forseti-copilot-agent-tracker-happy-path
forseti-copilot-agent-tracker-payload-validation
forseti-copilot-agent-tracker-install
forseti-copilot-agent-tracker-security
forseti-ai-service-db-refactor-static
forseti-ai-service-db-refactor-functional
forseti-ai-service-db-refactor-regression
forseti-csrf-post-routes-fix-static
forseti-csrf-post-routes-fix-functional
forseti-csrf-post-routes-fix-regression
forseti-agent-tracker-dashboard-controller-db-extraction-static
forseti-agent-tracker-dashboard-controller-db-extraction-functional
forseti-agent-tracker-dashboard-controller-db-extraction-regression
forseti-jobhunter-profile-form-db-extraction-static
forseti-jobhunter-profile-form-db-extraction-functional
forseti-jobhunter-profile-form-db-extraction-regression
forseti-jobhunter-resume-tailoring-queue-hardening-static
forseti-jobhunter-resume-tailoring-queue-hardening-functional
forseti-jobhunter-resume-tailoring-queue-hardening-regression
forseti-jobhunter-profile-form-static-db-extraction-static
forseti-jobhunter-profile-form-static-db-extraction-functional
forseti-jobhunter-profile-form-static-db-extraction-regression
forseti-csrf-seed-consistency
forseti-jobhunter-application-controller-db-extraction-static
forseti-jobhunter-application-controller-db-extraction-functional
forseti-jobhunter-application-controller-db-extraction-regression
forseti-jobhunter-application-controller-split-static
forseti-jobhunter-application-controller-split-functional
forseti-jobhunter-application-controller-split-regression
forseti-jobhunter-controller-extraction-phase1-static
forseti-jobhunter-controller-extraction-phase1-functional
forseti-jobhunter-controller-extraction-phase1-regression
forseti-jobhunter-application-status-dashboard-static
forseti-jobhunter-application-status-dashboard-functional
forseti-jobhunter-application-status-dashboard-regression
forseti-jobhunter-google-jobs-ux-static
forseti-jobhunter-google-jobs-ux-functional
forseti-jobhunter-google-jobs-ux-regression
forseti-jobhunter-profile-completeness-static
forseti-jobhunter-profile-completeness-functional
forseti-jobhunter-profile-completeness-regression
forseti-jobhunter-resume-tailoring-display-static
forseti-jobhunter-resume-tailoring-display-functional
forseti-jobhunter-resume-tailoring-display-regression
forseti-ai-conversation-user-chat-static
forseti-ai-conversation-user-chat-acl
forseti-ai-conversation-user-chat-csrf-post
forseti-ai-conversation-user-chat-regression
```
(57 suites — exceeds ≥20 threshold)

---

## Notes for Phase 2

1. **`forseti-ai-conversation-user-chat-static` / `-acl` / `-csrf-post`**: Already have `command` fields from release-f activation. Phase 2 fill task needs to add `test_cases` arrays that mirror those commands.
2. **`role-url-audit`** and **`jobhunter-e2e`**: Have commands; need `test_cases` wrapping.
3. **`forseti-csrf-seed-consistency`**: No feature.md; fill with a test_case that checks `grep -c "_csrf_token.*TRUE" job_hunter.routing.yml` baseline count >= 39.
4. **Retire list** (18 suites): Phase 2 should remove these entries from suite.json to reduce noise and prevent false-fail alerts.
5. **Defer list** (18 suites): Add `"status": "skipped"` and `"skip_reason"` fields where missing; do not remove.
