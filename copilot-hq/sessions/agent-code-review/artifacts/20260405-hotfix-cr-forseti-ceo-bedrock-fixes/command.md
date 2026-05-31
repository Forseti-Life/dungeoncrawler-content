- Agent: agent-code-review
- Status: pending
- command: |
    Gate 1c Hotfix Code Review — CEO-applied Bedrock emergency fixes (agent-code-review):

    CONTEXT: During the 20260322-dungeoncrawler-release-next session, the CEO applied
    emergency production fixes directly (bypassing the normal dev-agent workflow) due to
    a P0 Bedrock model EOL outage. Per Gate 1c in runbooks/shipping-gates.md, all CEO/PM-
    applied hotfixes require a same-cycle code review. This review does NOT block the
    already-shipped hotfix; it feeds back into the release cycle as dev inbox items for
    any MEDIUM+ findings.

    FILES CHANGED BY CEO (review these):
    1) sites/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php
       - model now reads from system config instead of deleted field_ai_model node field
       - Added buildBedrockClient() helper (region/credentials from config)
       - Added getModelFallbacks() with 3-model fallback chain
       - sendMessage() now has retry loop across fallback chain
       - generateSummary() fixed (was using undefined $config, hardcoded us-west-2)
    2) sites/forseti/web/modules/custom/ai_conversation/src/Controller/ChatController.php
       - Removed field_ai_model from node creation
    3) sites/dungeoncrawler/web/modules/custom/ai_conversation/src/Service/AIApiService.php
       - invokeModelDirect() and testConnection() patched with same fallback chain
       (commit a4a4e8bf by dev-forseti — verify this matches CEO intent)
    4) Apache forseti-le-ssl.conf (production only — NOT in repo)
       - SetEnv COPILOT_HQ_ROOT added (out-of-band; confirm documented in runbook)
    5) copilot-hq/orchestrator/engine.py
       - _write_tick_telemetry() added

    REVIEW SCOPE:
    - Security: credentials not hardcoded, IAM key not in code, config keys correctly
      read from Drupal config API (not $_ENV or raw file reads)
    - Logic: fallback chain handles all 404/throttle cases without infinite loop
    - Regression risk: ChatController.php node creation change — confirm field removal
      doesn't break existing node schema or existing session nodes
    - Engine.py: _write_tick_telemetry() file write — confirm no path traversal risk,
      correct error handling if HQ root not set

    DELIVERABLE:
    - Write review outbox at sessions/agent-code-review/outbox/ with PASS/FAIL per file
    - Route any MEDIUM+ findings to pm-forseti for dev-forseti inbox dispatch
    - ROI: 30 — P0 production fix applied without peer review; any logic error in the
      fallback chain or credential handling could cause silent outage or data leak.

    KB reference: knowledgebase/lessons/20260405-hotfix-code-review-gate-gap.md
    Gate reference: runbooks/shipping-gates.md Gate 1c
    PM owner: pm-forseti
