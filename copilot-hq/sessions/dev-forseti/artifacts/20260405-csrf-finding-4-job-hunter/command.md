- command: |
    CSRF FINDING-4 patch execution (dev-forseti):

    Apply CSRF protection to job_hunter application submission routes missed by the
    GAP-002 patch (694fc424f). FINDING-4a/4b/4c: add _csrf_token: 'TRUE' under
    requirements: for step3/4/5 routes (and their _short variants).
    FINDING-4d (addposting): GET/POST combo — prior fix was reverted due to GET 403
    regression. Fix via split route or controller-level CSRF (dev judgment required).

    File: sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml
    Patches: sessions/sec-analyst-infra/artifacts/20260322-improvement-round-20260322-forseti-release-b/gap-review.md

    See README.md for full route table, fix patterns, and acceptance criteria.

    REQUIRED: write sessions/dev-forseti/artifacts/csrf-finding-4-applied.txt
    with commit hash + verification output (PASS).

    ROI: 12 — step5 (submit-application) is the highest-risk route;
    CSRF could force job application submission for a logged-in user.
