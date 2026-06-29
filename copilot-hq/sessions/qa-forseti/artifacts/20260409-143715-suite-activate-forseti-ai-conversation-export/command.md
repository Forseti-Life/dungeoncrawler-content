# Suite Activation: forseti-ai-conversation-export

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T14:37:15+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-ai-conversation-export"`**  
   This links the test to the living requirements doc at `features/forseti-ai-conversation-export/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-ai-conversation-export-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-ai-conversation-export",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-ai-conversation-export"`**  
   Example:
   ```json
   {
     "id": "forseti-ai-conversation-export-<route-slug>",
     "feature_id": "forseti-ai-conversation-export",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-ai-conversation-export",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-ai-conversation-export

- Feature: forseti-ai-conversation-export
- Module: ai_conversation
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify plain text file download, correct format, export button on chat and history pages, access control, and empty conversation handling.

## Test cases

### TC-1: Anonymous access → 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/forseti/conversation/1/export`
- Expected: `403`

### TC-2: Authenticated export — file download response
- Steps: As authenticated user with a conversation, GET `/forseti/conversation/{own_id}/export`.
- Expected: HTTP 200; `Content-Disposition: attachment; filename="conversation-{id}-{date}.txt"`; `Content-Type: text/plain`.

### TC-3: File content format
- Steps: Download a conversation with 2+ message turns; inspect file.
- Expected: Line 1 = "Conversation: {title}"; Line 2 = "Date: {ISO date}"; blank line; then alternating `[User]:` and `[Assistant]:` lines.

### TC-4: Export another user's conversation → 403
- Steps: As User A, GET `/forseti/conversation/{id_owned_by_B}/export`.
- Expected: 403. No content returned.

### TC-5: Non-integer conversation_id → 404
- Steps: GET `/forseti/conversation/notanid/export`
- Expected: 404.

### TC-6: Empty conversation
- Steps: Export a conversation with 0 messages.
- Expected: 404 or minimal header-only file. No error. No other user's data.

### TC-7: Export button on chat page
- Steps: Open `/forseti/chat` with an active conversation (≥1 message).
- Expected: "Download conversation" link visible.

### TC-8: No export button — empty chat
- Steps: Open `/forseti/chat` with a new conversation (0 messages).
- Expected: No export link shown.

### TC-9: Export link on history browser
- Steps: View `/forseti/conversations` list.
- Expected: Each conversation entry has a "Download" link.

### TC-10: Route registered
- Steps: `./vendor/bin/drush router:debug | grep conversation_export`
- Expected: Route listed.

## Regression notes
- Chat and conversation history pages must remain functional after export link is added.
- Existing `ai_conversation` API routes must not be broken.

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-ai-conversation-export

- Feature: forseti-ai-conversation-export
- Module: ai_conversation
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Export any conversation as a plain text file. Export button on `/forseti/chat` (active conversation) and `/forseti/conversations` (history list). GET endpoint returns a file download. No state mutation — pure read.

## Acceptance criteria

### AC-1: Export route
- `GET /forseti/conversation/{conversation_id}/export` returns a file download response.
- Route name: `forseti.conversation_export`.
- Anonymous access → 403.
- `{conversation_id}` must be an integer; non-integer → 404.
- Controller verifies `conversation.uid == current_user->id()`; otherwise → 403.

### AC-2: Response format
- `Content-Type: text/plain; charset=UTF-8`
- `Content-Disposition: attachment; filename="conversation-{id}-{YYYYMMDD}.txt"`
- Body format:
  ```
  Conversation: {title or "Untitled"}
  Date: {created date, ISO 8601}

  [User]: {message text}
  [Assistant]: {response text}
  [User]: ...
  ```

### AC-3: Export button on chat page
- `/forseti/chat` renders a "Download conversation" link when the current conversation has at least 1 message.
- Link href: `/forseti/conversation/{conversation_id}/export`.
- No button shown when conversation has 0 messages.

### AC-4: Export link on history browser
- Each entry on `/forseti/conversations` has a "Download" link → `/forseti/conversation/{id}/export`.

### AC-5: Empty conversation
- Conversation with 0 messages: export returns 404 or a file with only the header lines (no blank record of another user's data).

### AC-6: Access control enforced
- Attempting to download another user's conversation: 403. No partial data returned.

## Security acceptance criteria

### Authentication/permission surface
- Route requires `_permission: 'use ai conversation'` and `_user_is_logged_in: 'TRUE'`.
- Controller checks `conversation.uid == current_user->id()` before generating any output.
- Verify: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/forseti/conversation/1/export` → 403 (anonymous).

### CSRF expectations
- Export is a GET (file download) — no CSRF token required. No state is mutated.

### Input validation requirements
- `{conversation_id}`: integer only; non-integer → 404.
- No user input is included in the exported file beyond the conversation's own stored content.
- Message content is written as plain text (no HTML rendering); special characters are output as-is.

### PII/logging constraints
- Conversation message content must NOT be written to watchdog during export generation.
- Error events (invalid ID, access denied): log `conversation_id` + error code only.

## Verification commands
```bash
# Anonymous access
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/forseti/conversation/1/export
# Expected: 403

# Route registered
cd /home/ubuntu/forseti.life/sites/forseti && ./vendor/bin/drush router:debug | grep conversation_export

# PHP lint on controller
php -l /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/src/Controller/ChatController.php
```
- Agent: qa-forseti
- Status: pending
