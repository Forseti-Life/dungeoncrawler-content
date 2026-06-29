# QA Findings — Action Required

- Product team: forseti
- Release id: 20260412-forseti-release-s
- Site label: forseti-life
- Base URL: https://forseti.life
- QA run: 20260506-103423
- Findings summary: sessions/qa-forseti/artifacts/auto-site-audit/20260506-103423/findings-summary.md
- Findings JSON: sessions/qa-forseti/artifacts/auto-site-audit/20260506-103423/findings-summary.json
- Total open issues: 61

    ## NEW FEATURE IMPLEMENTATIONS REQUIRED
    The following failures are on tests that have NEVER PASSED.
    These are NOT regressions. The feature has not been built yet.
    For each feature_id below, your task is to IMPLEMENT the feature
    per the living requirements document at features/<feature_id>/.
    
    ### Feature: forseti-jobhunter-profile
    Living doc: features/forseti-jobhunter-profile/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (6):
      - /jobhunter/profile
      - /jobhunter/profile/dashboard
      - /jobhunter/profile/summary
      - /jobhunter/profile/edit
      - /jobhunter/my-profile
      - /jobhunter/my-profile/edit
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-application-status-dashboard
    Living doc: features/forseti-jobhunter-application-status-dashboard/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/my-jobs
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-follow-up-reminders
    Living doc: features/forseti-jobhunter-follow-up-reminders/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/my-jobs
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-application-submission
    Living doc: features/forseti-jobhunter-application-submission/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/application-submission
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-application-analytics
    Living doc: features/forseti-jobhunter-application-analytics/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/analytics
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-saved-search
    Living doc: features/forseti-jobhunter-saved-search/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/googlejobssearch
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-browser-automation
    Living doc: features/forseti-jobhunter-browser-automation/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/settings/credentials
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-application-deadline-tracker
    Living doc: features/forseti-jobhunter-application-deadline-tracker/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/deadlines
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-bulk-status-update
    Living doc: features/forseti-jobhunter-bulk-status-update/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/applications
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-company-interest-tracker
    Living doc: features/forseti-jobhunter-company-interest-tracker/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/companies/my-list
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-company-research-tracker
    Living doc: features/forseti-jobhunter-company-research-tracker/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/companies
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-contact-referral-tracker
    Living doc: features/forseti-jobhunter-contact-referral-tracker/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (2):
      - /jobhunter/contacts
      - /jobhunter/contacts/add
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-contact-tracker
    Living doc: features/forseti-jobhunter-contact-tracker/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/contacts/add
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-job-board-preferences
    Living doc: features/forseti-jobhunter-job-board-preferences/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/preferences/sources
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-offer-tracker
    Living doc: features/forseti-jobhunter-offer-tracker/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/offers
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ## REGRESSION FIXES REQUIRED
    The following failures are on tests that previously passed (or have no feature_id).
    Treat these as regressions — identify what changed and restore correct behavior.

    Open issue buckets:
    - Missing assets (404): 0
    - Permission violations: 2
    - Other failures (4xx/5xx): 59
    - Total: 40 (approx — subtract new-feature paths above)

    Required actions:
    1) Fix highest-impact failures first.
    2) For each fix, notify QA immediately with explicit handoff marker.
    3) Keep notes concise in outbox and include touched files/routes.

Deliverable:
- Outbox update with Status + Summary and QA handoff notes.
- For new features: create/update features/<feature_id>/02-implementation-notes.md.
