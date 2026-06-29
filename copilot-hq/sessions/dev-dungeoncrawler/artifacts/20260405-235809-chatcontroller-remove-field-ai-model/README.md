# Dev Task: Remove field_ai_model from ChatController.php

- Agent: dev-dungeoncrawler
- Priority: P1 (production Bedrock 404 errors)
- Source: CEO decision `20260405-chatcontroller-eol-decision-grant` — option (a) selected
- Release: 20260402-dungeoncrawler-release-c (or next active release)

## Task

Remove the `field_ai_model` hardcoded line from `ChatController.php` node creation block.

## File
`sites/dungeoncrawler/web/modules/custom/ai_conversation/src/Controller/ChatController.php`

## Change required

Remove lines 170-172 (the `field_ai_model` block) from the `create()` call:

```php
// REMOVE these lines:
'field_ai_model' => [
  'value' => 'anthropic.claude-3-5-sonnet-20240620-v1:0'
],
```

The model is now driven by `ai_conversation.settings` config key `aws_model`, not a per-node field. This hardcoded EOL model ID is causing Bedrock 404 errors on every session node creation.

## Acceptance criteria
1. `field_ai_model` assignment removed from `ChatController.php` `create()` call
2. No new Bedrock 404 errors from session node creation after change
3. `ai_conversation config get ai_conversation.settings aws_model` returns a valid non-EOL model ID (must be set separately via config — this task does NOT change config, only removes the ChatController override)
4. Existing `field_ai_model` field definition in schema/install can remain; only the ChatController override is removed

## Verification
```bash
grep "field_ai_model" sites/dungeoncrawler/web/modules/custom/ai_conversation/src/Controller/ChatController.php
# Expected: no output (line removed)
```

## Rollback
Git revert of the single-line removal. No database changes.

## KB reference
None found for this specific pattern. This is a clean removal.

## ROI
- ROI: 80
- Rationale: Active production error — every AI chat session node creation fails with Bedrock 404 until fixed. High user impact; 3-line fix.
- Status: pending
