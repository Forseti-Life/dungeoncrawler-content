- command: |
    CSRF FINDING-2 patch execution (dev-infra):

    Apply ready-to-use patches for FINDING-2a, FINDING-2b, FINDING-2c:
    _csrf_token is under options: instead of requirements: in ai_conversation.routing.yml
    (forseti + dungeoncrawler) and agent_evaluation.routing.yml (forseti). Drupal ignores
    options: for access checking — these routes have zero CSRF protection despite appearances.

    Files:
    - sites/forseti/web/modules/custom/ai_conversation/ai_conversation.routing.yml
    - sites/dungeoncrawler/web/modules/custom/ai_conversation/ai_conversation.routing.yml
    - sites/forseti/web/modules/custom/agent_evaluation/agent_evaluation.routing.yml

    Move _csrf_token: TRUE from options: into requirements: in each file.
    See README.md for exact patches and acceptance criteria.

    REQUIRED: write sessions/dev-infra/artifacts/csrf-finding-2-applied.txt with
    commit hash + csrf-route-scan.sh output. Gate 2 is blocked until this file exists.

    ROI: 12 — LLM endpoint CSRF; 4th escalation cycle; patches are written; execution is
    the only remaining step.
