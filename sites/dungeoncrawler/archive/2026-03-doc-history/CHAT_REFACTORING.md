# Chat System Refactoring Summary

> **Status (2026-03+)**: Historical milestone document.
>
> This file captures the room-chat refactor completed in February 2026. It is useful for change history, but it is not the authoritative source for the current multi-session chat and narration architecture.
>
> For current architecture and behavior, refer to:
> - `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/CHAT_AND_NARRATION_ARCHITECTURE.md`
> - `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/README.md`

**Date**: February 19, 2026  
**Scope**: Room chat system architecture improvements

## Changes Made

### 1. New Service Layer

**Created**: `src/Service/RoomChatService.php`

Extracted all business logic from controller into dedicated service:
- **Input Validation**: Message length (max 2000 chars), type validation
- **Content Sanitization**: Removes control chars, normalizes whitespace
- **Storage Management**: Handles database operations with automatic pruning (500 msg limit)
- **Access Control**: Centralized campaign access verification
- **Logging**: Comprehensive audit trail via `dungeoncrawler_chat` logger channel

**Benefits**:
- Single responsibility principle (controller handles HTTP, service handles logic)
- Reusable in other contexts (CLI, cron, other controllers)
- Easier to test in isolation
- Follows existing Drupal service patterns

### 2. Refactored Controller

**Modified**: `src/Controller/RoomChatController.php`

Simplified to thin API layer:
- Delegates all logic to `RoomChatService`
- Handles HTTP request/response only
- Proper error handling without exposing internals
- Clean dependency injection

**Reduced from**: 256 lines → 155 lines (39% smaller)

### 3. Enhanced Frontend

**Modified**: `js/hexmap.js` (UIManager class)

**New Features**:
- **Loading States**: Disables submit button, shows "Sending..." during API call
- **Double-Submit Prevention**: Blocks concurrent submissions
- **Length Validation**: Client-side 2000 char limit (matches server)
- **Character ID**: Sends `character_id` with messages for proper attribution
- **Error Recovery**: Restores message to input on failure for retry
- **Better Error Messages**: Shows specific error from server
- **Confirmation Loading**: Reloads history after post to show server-confirmed message

### 4. Data Model Enhancement

**Added Fields** to message structure:
```json
{
  "character_id": 42,      // NEW: Links message to specific character
  "user_id": 7,            // NEW: Links message to user account
  "speaker": "Name",       // ENHANCED: Now sanitized (max 100 chars)
  "message": "...",        // ENHANCED: Now sanitized (max 2000 chars)
  "type": "player",
  "timestamp": "2026-02-19T12:34:56+00:00"
}
```

**Benefits**:
- Prevents name spoofing (character_id is authoritative)
- Audit trail with user_id
- LLM context can filter by character
- Moderation can trace messages to users

### 5. Security Improvements

**Input Sanitization**:
- Server-side removal of control characters
- Whitespace normalization
- Length enforcement (2000 msg, 100 speaker name)

**XSS Prevention**:
- Frontend uses `textContent` (not `innerHTML`)
- No HTML parsing of user content

**Access Control**:
- Service-level verification (reusable)
- Admin bypass with proper permission check

**Audit Logging**:
- Every message logged with:
  - Room ID
  - User ID
  - Message preview (100 chars)
  - Timestamp

### 6. Service Registration

**Added to**: `dungeoncrawler_content.services.yml`

```yaml
dungeoncrawler_content.room_chat_service:
  class: Drupal\dungeoncrawler_content\Service\RoomChatService
  arguments:
    - '@database'
    - '@dungeoncrawler_content.dungeon_state_service'
    - '@logger.factory'
    - '@current_user'
```

### 7. Documentation Updates

**Updated**: `CHAT_SYSTEM.md`
- Architecture section now describes service layer
- Security section added with validation/sanitization details
- Updated API examples with character_id
- Added service usage examples for LLM integration

**Updated**: `README.md` (dungeoncrawler_content module)
- Room Chat System section updated with refactoring notes

**Updated**: `test_room_chat.sh`
- Test script now includes character_id in POST requests

## Architecture Rationale

### Why Not Use DungeonStateService for Chat?

The current implementation uses direct database updates instead of `DungeonStateService` for pragmatic reasons:

**Pros of Direct DB**:
- Chat is append-only (no conflicts from concurrent writes)
- High write frequency (chat is conversational)
- No need for optimistic locking overhead
- Simpler code path

**Cons of Direct DB**:
- Bypasses state versioning system
- Could lose chat messages if dungeon state is rolled back
- Inconsistent with other dungeon state mutations

**Future Consideration**: If chat messages become critical to game state (e.g., dialogue choices affect quest outcomes), migrate to `DungeonStateService` or create dedicated `dc_room_chat` table.

## Testing

**Manual Test**:
1. Navigate to hexmap with campaign context
2. Enter a message in chat input
3. Observe "Sending..." button state
4. Verify message appears after confirmation
5. Refresh page - message persists
6. Try sending 2001+ char message - blocked client-side

**Automated Test**:
```bash
./testing/apitesting/test_room_chat.sh
```

## Performance Considerations

**Message Limits**:
- 500 messages per room (auto-pruned)
- 2000 chars per message

**Optimization Opportunities**:
1. **Dedicated Table**: Move chat to `dc_room_chat` table for better performance
2. **Caching**: Cache recent messages in Redis/Memcached
3. **Pagination**: Load older messages on demand
4. **Websockets**: Real-time updates without polling

## Migration Notes

**No Database Changes Required**: Refactoring is code-only, no schema changes.

**Backwards Compatibility**: 
- Existing messages without `character_id`/`user_id` still work
- Service handles missing fields gracefully
- Frontend falls back to campaign context if character data unavailable

## Future Enhancements

**Priority: High**
- [ ] Add typing indicators (WebSocket/polling)
- [ ] Message editing (within time window)
- [ ] NPC auto-responses via LLM integration

**Priority: Medium**
- [ ] Message search/filtering
- [ ] Chat export (PDF/TXT)
- [ ] Read receipts

**Priority: Low**
- [ ] Message reactions (emoji)
- [ ] Private whispers between characters
- [ ] Chat commands (`/roll`, `/emote`)

## Lessons Learned

1. **Service Layer First**: Should have created service from start, not controller-first
2. **Character ID Critical**: Name alone is insufficient for attribution
3. **Client Validation**: Frontend validation improves UX and reduces server load
4. **Loading States**: Essential for async operations to prevent user confusion
5. **Sanitization**: Always sanitize user input, even if frontend validates

## Related Files

**Modified**:
- `src/Service/RoomChatService.php` (NEW)
- `src/Controller/RoomChatController.php` (REFACTORED)
- `js/hexmap.js` (ENHANCED)
- `dungeoncrawler_content.services.yml` (UPDATED)
- `CHAT_SYSTEM.md` (UPDATED)
- `README.md` (UPDATED)
- `test_room_chat.sh` (UPDATED)

**No Changes**:
- Templates (hexmap-demo.html.twig)
- Styles (hexmap.css)
- Routing (dungeoncrawler_content.routing.yml)
- Database schema

## Verification

✅ No syntax errors  
✅ Drupal cache rebuilt successfully  
✅ Service registered correctly  
✅ Controller delegates to service  
✅ Frontend includes character_id  
✅ Documentation updated  
✅ Test script updated  

**Status**: Production-ready
