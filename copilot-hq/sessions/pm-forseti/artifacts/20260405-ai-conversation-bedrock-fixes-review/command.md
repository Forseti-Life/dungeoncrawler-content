- Agent: pm-forseti
- Status: pending
- command: |
    AI Conversation module Bedrock fix verification and release prep (pm-forseti):

    CONTEXT: The ai_conversation Drupal module on forseti.life was broken due to an
    AWS Bedrock EOL model (anthropic.claude-3-5-sonnet-20240620-v1:0 returned 404).
    The CEO applied emergency fixes directly to production. These fixes need proper
    review, testing, and a release signoff before the next scheduled push.

    Fixes applied by CEO (this session):
    1) AIApiService.php — model now reads from system config (aws_model in
       ai_conversation.settings) instead of deleted field_ai_model node field
    2) Added buildBedrockClient() helper that reads region/credentials from config
    3) Added getModelFallbacks() with chain:
       us.anthropic.claude-sonnet-4-6 → us.anthropic.claude-haiku-4-5 → us.anthropic.claude-3-5-haiku-20241022-v1:0
    4) sendMessage() has retry loop across fallback chain
    5) generateSummary() fixed — was using undefined $config and hardcoded us-west-2
    6) ChatController.php — removed field_ai_model from node creation
    7) Same fixes applied to stlouisintegration, theoryofconspiracies, dungeoncrawler
    8) Shared module structure created: shared/modules/ai_conversation symlinks to
       sites/forseti/web/modules/custom/ai_conversation (canonical source)
    9) stlouisintegration and theoryofconspiracies production dirs replaced with symlinks
    10) dungeoncrawler NOT yet symlinked (has diverged context-building logic)

    Production config (confirmed working):
    - aws_model: us.anthropic.claude-sonnet-4-6
    - aws_region: us-east-1
    - AWS key: AKIAZNT6OWPDWAFUJAHA (IAM user forseti, account 647731524551)

    YOUR TASKS:
    1) Verify chat works on forseti.life — go to https://forseti.life/node/1544/chat
       and confirm no errors
    2) Delegate to qa-forseti: run the ai_conversation test suite and confirm the
       Bedrock integration tests pass with the new model IDs
    3) Delegate to dev-forseti: audit dungeoncrawler's ai_conversation for
       DungeonCrawler-specific context logic. If it can be moved to Drupal config or
       a hook, do so and create a symlink to shared/modules/. Document what's unique.
    4) Delegate to dev-forseti: verify stlouisintegration and theoryofconspiracies
       symlinks work — confirm /var/www/html/stlouisintegration/web/modules/custom/ai_conversation
       and /var/www/html/theoryofconspiracies/web/modules/custom/ai_conversation both
       resolve correctly and chat works on those sites.
    5) Create a release signoff artifact for the current forseti release cycle once
       QA confirms pass: sessions/pm-forseti/artifacts/release-signoffs/20260322-forseti-release-next.md

    ACCEPTANCE CRITERIA:
    - Chat works on forseti.life with no Bedrock errors
    - QA confirms ai_conversation tests pass
    - dungeoncrawler audit complete with decision: symlink or maintain separately
    - stlouisintegration + theoryofconspiracies confirmed working
    - Release signoff artifact created

    ROI: Critical — this was a production outage. Confirm fix, close the loop.
