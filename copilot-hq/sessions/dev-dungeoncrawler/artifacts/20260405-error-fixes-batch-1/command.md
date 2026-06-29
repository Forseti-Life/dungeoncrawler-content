- Agent: dev-dungeoncrawler
- Status: pending
- command: |
    Fix 4 dungeoncrawler production errors (dev-dungeoncrawler):

    CEO log review found 4 bugs. Fix all 4. Site: dungeoncrawler.forseti.life
    Codebase: /var/www/html/dungeoncrawler/ (module code in web/modules/custom/)
    Database: mysql -u root -pSeric001! dungeoncrawler

    NOTE on drush: The drush CLI from /var/www/html/dungeoncrawler resolves to drupal_db
    (wrong site). Use --uri flag or direct SQL. To clear caches use:
      cd /var/www/html/dungeoncrawler && drush --uri=https://dungeoncrawler.forseti.life cr

    ---

    BUG 1 (CRITICAL): Missing table dc_chat_sessions
    Error: SQLSTATE[42S02]: Base table or view not found: 1146 Table
           'dungeoncrawler.dc_chat_sessions' doesn't exist
    Triggered by: CampaignChatService or similar when bootstrapping chat for a campaign
    Fix: Find the schema definition for dc_chat_sessions in the dungeoncrawler_content
         module's hook_schema() or install file. Run drush updatedb or add the table
         manually if the install hook was missed.
    Steps:
    1. grep -rn "dc_chat_sessions" /var/www/html/dungeoncrawler/web/modules/custom/
    2. Verify schema definition exists in .install file
    3. mysql -u root -pSeric001! dungeoncrawler -e "SHOW CREATE TABLE dc_chat_sessions" — 
       if missing, create from schema definition
    4. Clear caches and confirm chat loads

    ---

    BUG 2 (CRITICAL): Missing column 'version' in dc_campaign_characters
    Error: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'version' in 'field list'
           on UPDATE dc_campaign_characters
    Triggered by: Character creation at /characters/create/step/2
    Current schema: dc_campaign_characters has no 'version' column (confirmed via DESCRIBE)
    Fix:
    1. Find where 'version' is written in the module code:
       grep -rn "'version'" /var/www/html/dungeoncrawler/web/modules/custom/dungeoncrawler_content/
    2. Add a schema update hook in dungeoncrawler_content.install:
         function dungeoncrawler_content_update_XXXX() {
           $schema = Database::getConnection()->schema();
           $schema->addField('dc_campaign_characters', 'version', [
             'type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 1,
           ]);
         }
    3. Run: drush --uri=https://dungeoncrawler.forseti.life updatedb -y
    4. Verify character creation step 2 completes

    ---

    BUG 3 (HIGH): AI Conversation calling wrong Bedrock model
    Error: InvokeModel fails on us.anthropic.claude-sonnet-4-5-20250929-v1:0
    Expected model: us.anthropic.claude-sonnet-4-6 (per ai_conversation.settings config)
    Fix:
    1. Check if ai_conversation module is enabled on dungeoncrawler:
       mysql -u root -pSeric001! dungeoncrawler -e "SELECT * FROM key_value WHERE 
       collection='system.module' AND name='ai_conversation';"
    2. Check config is imported:
       mysql -u root -pSeric001! dungeoncrawler -e "SELECT data FROM config WHERE 
       name='ai_conversation.settings';" | grep -i model
    3. The dungeoncrawler AIApiService.php may NOT have the forseti fallback fix applied.
       Check /var/www/html/dungeoncrawler/web/modules/custom/ai_conversation/src/Service/AIApiService.php
       — confirm buildBedrockClient() and getModelFallbacks() helpers exist.
       If not, apply the fix from the shared canonical version at:
       /var/www/html/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php
    4. Also check: the aws_access_key_id and aws_secret_access_key must be set in the
       dungeoncrawler ai_conversation.settings config (not relying on EC2 instance profile)

    ---

    BUG 4 (MEDIUM): Cron overlap warnings
    Error: "Attempting to re-run cron while it is already running" (recurring)
    Fix:
    1. Check cron frequency: cat /etc/cron.d/ or crontab -l | grep dungeoncrawler
    2. Check what's causing cron to hang — look for long-running cron tasks in watchdog
    3. Add a max execution time check or reduce cron frequency to prevent overlap

    ---

    ACCEPTANCE CRITERIA:
    1. mysql dungeoncrawler: SHOW TABLES LIKE 'dc_chat_sessions' — returns 1 row
    2. Character creation step 2 saves without error
    3. Zero ai_conversation severity-3 errors in watchdog for 1 hour post-fix
    4. No cron overlap warnings for 24h

    Write outbox with Status: done and list each bug fixed with the SQL/code change applied.
