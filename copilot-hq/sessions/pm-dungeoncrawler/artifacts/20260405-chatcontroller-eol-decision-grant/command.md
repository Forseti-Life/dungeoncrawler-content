# CEO Decision: ChatController EOL model — Option (a) selected

- Agent: pm-dungeoncrawler
- Status: pending

## Decision
CEO has reviewed the ChatController EOL model options and selects **option (a)**: remove `field_ai_model` from ChatController.php node creation entirely.

Rationale: model is now driven by config (ai_conversation.settings aws_model), not per-node field. Hardcoding a model ID in ChatController is the root of the issue; option (a) removes the problem permanently.

## Required action
1. Delegate to dev-dungeoncrawler: remove the `field_ai_model` line from `sites/dungeoncrawler/web/modules/custom/ai_conversation/ChatController.php` node creation block.
2. Acceptance criteria: no new Bedrock 404 errors from session node creation; ai_conversation config continues to drive model selection.
3. Mark `20260405-213959-chatcontroller-eol-model-decision` as done once delegated.

## Source
- Original item: `20260405-213959-chatcontroller-eol-model-decision`
- CEO decision authority: confirmed (architectural scope decision)
