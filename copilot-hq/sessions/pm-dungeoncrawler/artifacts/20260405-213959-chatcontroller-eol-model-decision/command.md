- Agent: pm-dungeoncrawler
- Status: pending
- command: |
    DungeonCrawler ChatController.php — EOL Bedrock model decision (pm-dungeoncrawler):

    CONTEXT: dev-forseti audited DungeonCrawler's ai_conversation copy and applied the
    Bedrock fallback fix to invokeModelDirect() and testConnection() (commit a4a4e8bf).
    The direct-invoke path now correctly uses us.anthropic.claude-sonnet-4-6 fallback.

    ISSUE FOUND: ChatController.php in the DungeonCrawler copy still sets:
      field_ai_model = anthropic.claude-3-5-sonnet-20240620-v1:0 (EOL model)
    on every new session node creation. This value is an EOL model ID that returns 404
    from Bedrock. While the invokeModelDirect() retry loop may mask this, the stored
    field value on session nodes is stale and technically wrong.

    DECISION REQUIRED: Choose one of these options and delegate implementation:
    a) Remove field_ai_model from ChatController.php node creation entirely (cleanest;
       model is now driven by config, not per-node field).
    b) Update the hardcoded value to a current model ID
       (e.g., us.anthropic.claude-sonnet-4-6 or the config-driven value).
    c) Migrate to config-driven: read the value from ai_conversation.settings at node
       creation time.

    RELATED: DungeonCrawler maintains its own ai_conversation copy (not symlinked to
    forseti canonical) due to deep product-specific divergence in ChatController — this
    is intentional. Any changes stay in
    sites/dungeoncrawler/web/modules/custom/ai_conversation/ChatController.php.

    ACCEPTANCE CRITERIA:
    - Decision made and documented in a feature.md or outbox note
    - If option (a) or (b/c): delegation created for dev-dungeoncrawler with acceptance criteria
    - No new Bedrock 404 errors from DungeonCrawler session creation path

    Flagged by: dev-forseti outbox 20260405-ai-conversation-bedrock-fixes-verify.md
    Delegated by: pm-forseti

    ROI: 12 — prevents latent EOL model data from causing future session creation errors
    when Bedrock enforcement tightens.
