- Agent: dev-dungeoncrawler
- Status: pending
- command: |
    Verify production Drupal error reporting is suppressed on dungeoncrawler (dev-dungeoncrawler):

    Security gap identified (GAP-SEC-02): The 20260322-dungeoncrawler-release-next cycle shipped
    with CRITICAL production bugs caused by missing DB schema (dc_chat_sessions table, version column).
    These caused PHP exceptions on production. If Drupal error reporting or PHP display_errors is
    configured verbosely, exceptions expose internal stack traces, DB table names, module paths, and
    file system paths to unauthenticated users.

    Verify and harden production error reporting config on dungeoncrawler.forseti.life.

    Scope:
    - Production Drupal root: /var/www/html/dungeoncrawler
    - Settings file: /var/www/html/dungeoncrawler/web/sites/default/settings.php (or settings.local.php)

    Steps:
    1. Confirm PHP display_errors is off in the production PHP config or in settings.php:
       grep -n "display_errors\|error_level" /var/www/html/dungeoncrawler/web/sites/default/settings.php
    2. Confirm Drupal error_level is 0 (suppress all PHP messages):
       cd /var/www/html/dungeoncrawler && drush --uri=https://dungeoncrawler.forseti.life config:get system.logging
    3. If error_level is NOT 0, set it:
       cd /var/www/html/dungeoncrawler && drush --uri=https://dungeoncrawler.forseti.life config:set system.logging error_level 0
    4. Confirm Apache/PHP error log is writing to file (not stdout):
       grep -n "log_errors\|error_log" /etc/php/8.*/apache2/php.ini || grep -n "log_errors\|error_log" /etc/php/8.*/fpm/php.ini

    Acceptance criteria (ALL required):
    - system.logging error_level = 0 (no errors displayed to users) confirmed via drush config:get
    - display_errors = Off confirmed in PHP config
    - Output must be included in outbox under "## Verification evidence"

    If error_level is already 0 and display_errors is already Off: fast-exit with "PASS — already
    suppressed" and paste the verification output. No code change needed.

    If misconfigured: apply the config:set fix, paste before/after output, and commit the
    settings.php change (if hardcoded) with rollback note.

    Assign: dev-dungeoncrawler
    Owner of gap definition: sec-analyst-dungeoncrawler (WATCHDOG)
    Source gap: sessions/sec-analyst-dungeoncrawler/outbox/20260405-improvement-round-20260322-dungeoncrawler-release-next.md
