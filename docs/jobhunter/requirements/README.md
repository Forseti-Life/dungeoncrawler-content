# JobHunter Requirements — Index

**Module:** `job_hunter` (forseti.life)  
**Module path:** `sites/forseti/web/modules/custom/job_hunter/`  
**Last updated:** 2026-02-27

## Overview

The JobHunter module is a Drupal 10 custom module that automates the end-to-end job-search workflow: discovering job postings, tailoring resumes and cover letters via generative AI, tracking applications, and managing the underlying queue infrastructure. The documents below capture the system's requirements as of the current implementation state.

## Subsystem Documents

| # | File | Subsystem | Overall Status |
|---|------|-----------|----------------|
| JH-01 | [jh-01-user-profile-resume.md](jh-01-user-profile-resume.md) | User Profile & Resume Pipeline | PARTIAL |
| JH-02 | [jh-02-job-discovery.md](jh-02-job-discovery.md) | Job Discovery & Multi-Source Search | PARTIAL |
| JH-03 | [jh-03-resume-tailoring.md](jh-03-resume-tailoring.md) | AI Resume & Cover Letter Tailoring | COMPLETED |
| JH-04 | [jh-04-application-tracking.md](jh-04-application-tracking.md) | Application Tracking & Lifecycle | PARTIAL |
| JH-05 | [jh-05-company-management.md](jh-05-company-management.md) | Company Management & Research Automation | GAP |
| JH-06 | [jh-06-queue-management.md](jh-06-queue-management.md) | Queue Management & Worker Infrastructure | COMPLETED |
| JH-07 | [jh-07-error-management.md](jh-07-error-management.md) | Error Queue & Admin Management | COMPLETED |
| JH-08 | [jh-08-security-permissions.md](jh-08-security-permissions.md) | Security, Permissions & RBAC | GAP |
| JH-09 | [jh-09-configuration-apis.md](jh-09-configuration-apis.md) | Configuration & API Key Management | COMPLETED |

## Status Key

| Status | Meaning |
|--------|---------|
| **COMPLETED** | All requirements implemented and functional. |
| **PARTIAL** | Core implementation exists; one or more requirements are incomplete or unverified. |
| **GAP** | Requirement is designed or documented but code has not been written, or a critical defect exists. |

## Overall Gap Summary

- **Critical security gaps:** 3 (CSRF missing on 5 AJAX routes; SQL injection in `UserProfileController`; path traversal in `DocumentationController`)
- **Unimplemented subsystems:** Company research automation (3 services designed, no code written); user registration/onboarding flow; full RBAC enforcement
- **Partial subsystems:** Application submission automation (service exists, portal integrations incomplete); search history persistence; job deduplication
- **Test coverage:** ~20% (3 unit test files exist; most service-layer logic is untested)

## Related Internal Docs

- `docs/ARCHITECTURE.md` — high-level system design
- `docs/PERMISSIONS.md` — role/permission design (not yet fully enforced)
- `docs/PROCESS_FLOW.md` — end-to-end workflow diagrams
- `docs/COMPANY_RESEARCH_PATH_DESIGN.md` — design for unimplemented company research automation
- `docs/JOB_TAILORING_DESIGN.md` — AI tailoring design
- `docs/ERROR_QUEUE_IMPLEMENTATION.md` — error queue design notes
