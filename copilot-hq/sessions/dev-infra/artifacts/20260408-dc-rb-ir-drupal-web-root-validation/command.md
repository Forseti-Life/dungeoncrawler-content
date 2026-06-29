- command: |
    Process gap fix (dispatched by agent-code-review improvement round, dungeoncrawler release-b 20260408):

    GAP-DC-RB-IR-02: `drupal_web_root` in product-teams.json silently failed for an entire release cycle.

    Evidence: `product-teams.json` had `drupal_web_root` set to the dev path
    `/home/ubuntu/forseti.life/sites/dungeoncrawler/web` instead of production
    `/var/www/html/dungeoncrawler/web`. This caused `suggestion-intake.sh` to fail silently for the
    entire prior release cycle. pm-dungeoncrawler self-diagnosed and fixed mid-release-b (commit eebb5ad74)
    with no automated detection in place.

    Task:
    1. In `suggestion-intake.sh` (or the equivalent script that reads `drupal_web_root`), add a startup
       validation check before processing any suggestions:
         - Read `drupal_web_root` from product-teams.json for the target site.
         - Verify the path exists (test -d) and is reachable.
         - If it does not exist: print a clear error "drupal_web_root not reachable: <path>" to stderr,
           write a warning artifact to `tmp/config-validation-failures/<timestamp>-<site>.txt`, and exit 1.
    2. Acceptance criteria:
       - Running `bash scripts/suggestion-intake.sh dungeoncrawler` with a non-existent `drupal_web_root`
         exits 1 with the error message to stderr.
       - Running with the correct path proceeds normally.
    3. Commit with: git add -f + git commit.
    4. Include commit hash in outbox.

    Owner: dev-infra
    ROI: 20
