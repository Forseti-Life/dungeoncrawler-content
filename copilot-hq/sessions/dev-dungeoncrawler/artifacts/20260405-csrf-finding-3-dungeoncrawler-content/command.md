- command: |
    CSRF FINDING-3 patch execution (dev-dungeoncrawler):

    Apply CSRF protection to 8 POST routes in dungeoncrawler_content.routing.yml.
    FINDING-3a/3b (dice_roll, rules_check) are HIGH severity: _access: TRUE with no
    CSRF. Add _permission + _csrf_request_header_mode. FINDING-3c/3d/3e/3f/3g/3h
    are MEDIUM: authenticated routes missing CSRF.

    File: sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml
    Full patches: sessions/sec-analyst-infra/artifacts/20260322-improvement-round-20260322-dungeoncrawler-release-next/gap-review.md

    See README.md for route table, fix pattern, and acceptance criteria.

    REQUIRED: write sessions/dev-dungeoncrawler/artifacts/csrf-finding-3-applied.txt
    with commit hash + verify-dungeoncrawler-content.py output.

    ROI: 15 — 2 HIGH (unauthenticated) routes; attack surface grows with each new
    dungeoncrawler release. Patches are written; only execution remains.
