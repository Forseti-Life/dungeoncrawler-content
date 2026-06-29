- Agent: pm-dungeoncrawler
- Status: pending
- command: |
    DungeonCrawler error triage - assign fixes to dev-dungeoncrawler (pm-dungeoncrawler):

    CEO log review identified 4 distinct errors in the dungeoncrawler.forseti.life site.
    Review each, assign to dev-dungeoncrawler with acceptance criteria, and queue as
    individual inbox items or batch as appropriate. Mark this item done when all 4
    are delegated.

    ERROR 1 — CRITICAL: Missing DB table dc_chat_sessions
    - Type: dungeoncrawler_content (severity 3)
    - Message: "Table 'dungeoncrawler.dc_chat_sessions' doesn't exist"
    - Impact: Chat session bootstrap fails for campaigns (users can't chat)
    - Root cause: DB migration not run — table exists in code schema but not in DB
    - Expected fix: run pending DB update or add install hook to create the table

    ERROR 2 — CRITICAL: Missing DB column 'version' in dc_campaign_characters
    - Type: php / DatabaseExceptionWrapper (severity 3)
    - Message: "Unknown column 'version' in 'field list'" on UPDATE dc_campaign_characters
    - Impact: Character creation step 2 crashes; characters cannot be saved
    - Reproduced: /characters/create/step/2?character_id=X&campaign_id=Y
    - Root cause: Code references 'version' column but it was never added to the table
    - Expected fix: schema update hook to ADD COLUMN version INT DEFAULT 1

    ERROR 3 — HIGH: AI Conversation using wrong model on Bedrock
    - Type: ai_conversation (severity 3, recurring 30+ times)
    - Message: InvokeModel failing on "us.anthropic.claude-sonnet-4-5-20250929-v1:0"
    - Impact: AI chat completely broken on dungeoncrawler
    - Root cause: ai_conversation.settings has aws_model = us.anthropic.claude-sonnet-4-6
      (confirmed correct in config) but the code is still calling the old 4-5 model.
      Likely the config is not being read — check if ai_conversation module is actually
      enabled and config is imported. Also confirm the fallback retry logic from the
      forseti fixes has been applied to dungeoncrawler's AIApiService.php.
    - Expected fix: verify config is applied and fallback chain works

    ERROR 4 — MEDIUM: cron overlap (cron already running warning)
    - Type: cron warning, recurring
    - Impact: Cron tasks may be skipping
    - Root cause: Cron interval too short, or previous cron hangs
    - Expected fix: review cron job frequency; add max execution time guard

    ACCEPTANCE CRITERIA (per error):
    1. dc_chat_sessions: table exists in DB; campaign chat loads without DB error
    2. version column: column exists in dc_campaign_characters; step 2 character
       creation saves successfully
    3. AI errors: zero ai_conversation severity-3 watchdog entries after fix deploy
    4. Cron: no cron overlap warnings for 24h after fix

    NOTE: The dungeoncrawler site's drush CLI resolves to the wrong DB (drupal_db).
    When running drush commands for dungeoncrawler, cd to /var/www/html/dungeoncrawler
    and use: drush --uri=https://dungeoncrawler.forseti.life <cmd>
    Or run SQL directly: mysql -u root -pSeric001! dungeoncrawler -e "..."
