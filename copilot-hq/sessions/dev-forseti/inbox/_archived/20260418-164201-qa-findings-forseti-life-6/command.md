# QA Findings — Action Required

- Product team: forseti
- Release id: 20260412-forseti-release-m
- Site label: forseti-life
- Base URL: https://forseti.life
- QA run: 20260418-164201
- Findings summary: sessions/qa-forseti/artifacts/auto-site-audit/20260418-164201/findings-summary.md
- Findings JSON: sessions/qa-forseti/artifacts/auto-site-audit/20260418-164201/findings-summary.json
- Total open issues: 6

    ## NEW FEATURE IMPLEMENTATIONS REQUIRED
    The following failures are on tests that have NEVER PASSED.
    These are NOT regressions. The feature has not been built yet.
    For each feature_id below, your task is to IMPLEMENT the feature
    per the living requirements document at features/<feature_id>/.
    
    ### Feature: forseti-jobhunter-application-submission
    Living doc: features/forseti-jobhunter-application-submission/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/application-submission/1/screenshot/test
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-cover-letter-display
    Living doc: features/forseti-jobhunter-cover-letter-display/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/coverletter/1
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-interview-prep
    Living doc: features/forseti-jobhunter-interview-prep/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/interview-prep/1
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ### Feature: forseti-jobhunter-resume-version-labeling
    Living doc: features/forseti-jobhunter-resume-version-labeling/
    - feature.md          → PM brief + goals
    - 01-acceptance-criteria.md → what to build (PM-owned)
    - 03-test-plan.md     → what QA will verify (QA-owned)
    - 02-implementation-notes.md → YOUR artifact (create/update this)
    Failing paths (1):
      - /jobhunter/resume/1/edit
    
    BEFORE implementing: read 01-acceptance-criteria.md fully.
    BEFORE implementing: assess impact on existing flows (see Dev instructions).
    AFTER implementing: notify QA with specific paths fixed for targeted retest.
    
    ## REGRESSION FIXES REQUIRED
    The following failures are on tests that previously passed (or have no feature_id).
    Treat these as regressions — identify what changed and restore correct behavior.

    Open issue buckets:
    - Missing assets (404): 0
    - Permission violations: 6
    - Other failures (4xx/5xx): 0
    - Total: 2 (approx — subtract new-feature paths above)

    Required actions:
    1) Fix highest-impact failures first.
    2) For each fix, notify QA immediately with explicit handoff marker.
    3) Keep notes concise in outbox and include touched files/routes.

Deliverable:
- Outbox update with Status + Summary and QA handoff notes.
- For new features: create/update features/<feature_id>/02-implementation-notes.md.
