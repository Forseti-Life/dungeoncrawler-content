# Room Chat System Documentation

> **Status (2026-03+)**: Historical/legacy overview.
>
> This file documents the earlier room-chat-first implementation and is no longer the canonical architecture source for the full chat + narration stack.
>
> For the current implemented architecture, use:
> - `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/CHAT_AND_NARRATION_ARCHITECTURE.md`
> - `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/README.md` (Services + API summary)

## Scope Clarification

- `RoomChatService` remains part of the stack for room-level chat interactions.
- The production architecture now includes `ChatSessionManager`, `ChatChannelManager`, and `NarrationEngine` orchestration alongside `AiGmService` responses.
- Session-aware narration and channel routing are described in `CHAT_AND_NARRATION_ARCHITECTURE.md` and supersede the single-channel framing in this document.

## Overview

The room chat system enables persistent dialogue logs for each room in the dungeon. Characters can communicate with each other and NPCs, with full conversation history stored for later reference by characters and LLM-powered AI agents.

## Architecture

### Backend Components

**Service**: `src/Service/RoomChatService.php`

Central service handling all chat business logic:
- Message validation (max 2000 chars)
- Content sanitization (removes control chars, normalizes whitespace)
- Storage management via direct database updates
- Access control verification
- Automatic message limit enforcement (500 messages per room)
- Comprehensive logging of chat activity

**Controller**: `src/Controller/RoomChatController.php`

Lightweight API controller delegating to `RoomChatService`:
- Handles HTTP request/response
- Routes to service methods
- Manages error responses (no internal details exposed)
- Dependency injection of RoomChatService

### Frontend Components

**Location**: `js/hexmap.js` (UIManager class)

**UI Elements** (from `hexmap-demo.html.twig`):
- `.hexmap-chat` - Chat panel container
- `.hexmap-chat__log` - Scrollable message history
- `.hexmap-chat__input` - Message input form

**Key Methods**:
- `setupChatLog()` - Initializes form handlers, prevents double-submit, validates length
- `loadChatHistory()` - Fetches existing messages from API on room entry
- `postChatMessage(campaignId, roomId, speaker, message, characterId)` - Sends message with character ID
- `appendChatLine(speaker, message, type)` - Renders message in UI (uses `textContent` for XSS safety)

**Message Types**:
- `player` - User messages (color: accent)
- `npc` - NPC/character responses (color: default)
- `system` - System notifications (color: gray, italic)

**UX Features**:
- Loading indicators during submission
- Prevents double-submission
- Client-side length validation (2000 char limit)
- Auto-clears input on send
- Restores message to input on error for retry
- Reloads history after successful post to show confirmed message

### Service Layer

**Service Registration**: `dungeoncrawler_content.services.yml`

```yaml
dungeoncrawler_content.room_chat_service:
  class: Drupal\\dungeoncrawler_content\\Service\\RoomChatService
  arguments:
    - '@database'
    - '@dungeoncrawler_content.dungeon_state_service'
    - '@logger.factory'
    - '@current_user'
```

### API Endpoints

1. **GET** `/api/campaign/{campaign_id}/room/{room_id}/chat`
   - Fetches chat history for a specific room
   - Returns: `{ success: true, data: { roomId, messages: [...] } }`

2. **POST** `/api/campaign/{campaign_id}/room/{room_id}/chat`
   - Posts a new chat message to a room
   - Payload: `{ speaker: "Name", message: "...", type: "player", character_id: 123 }`
   - Returns: `{ success: true, data: { message: {...}, totalMessages: N } }`
   - Validates: message length (max 2000), type (player|npc|system)
   - Sanitizes: speaker name (max 100 chars), message content

**Routes**: Defined in `dungeoncrawler_content.routing.yml`
- Route names: `dungeoncrawler_content.api.room_chat_get` / `room_chat_post`
- Permission: `access dungeoncrawler characters`

### Data Storage

**Table**: `dc_campaign_dungeons`

**Column**: `dungeon_data` (JSON)

**Structure**:
```json
{
  "rooms": {
    "room-uuid-123": {
      "chat": [
        {
          "speaker": "Character Name",
          "message": "Hello, anyone here?",
          "type": "player",
          "timestamp": "2026-02-19T12:34:56+00:00",
          "character_id": 42,
          "user_id": 7
        },
        {
          "speaker": "NPC Guard",
          "message": "Who goes there?",
          "type": "npc",
          "timestamp": "2026-02-19T12:35:10+00:00",
          "character_id": null,
          "user_id": null
        }
      ]
    }
  }
}
```

**Message Limits**:
- Max 2000 characters per message (enforced client and server)
- Max 500 messages per room (older messages auto-pruned)
- Automatic sanitization removes control characters

## Security

**Input Validation**:
- Message length limited to 2000 characters
- Speaker name limited to 100 characters
- Message type must be player|npc|system
- Empty messages rejected

**Content Sanitization** (server-side):
- Control characters removed (except newlines)
- Whitespace normalized
- Trimmed and length-capped

**XSS Prevention** (client-side):
- All user content rendered with `textContent` (not `innerHTML`)
- No HTML parsing of message content

**Access Control**:
- Users must own campaign OR have character in campaign
- Admin users (`administer dungeoncrawler`) can access any campaign
- Per-request verification via `RoomChatService::hasCampaignAccess()`

**Audit Logging**:
- All chat messages logged to `dungeoncrawler_chat` channel
- Includes room ID, user ID, and message preview (100 chars)

## Access Control

**hasCampaignAccess()** (in RoomChatService) verifies:
1. User has `administer dungeoncrawler` permission (admins)
2. User owns the campaign (campaign creator)
3. User has a character in the campaign (players)

## Integration Points

### LLM Character Context

Chat history is stored per room, enabling:
- Characters can reference previous dialogue via API
- LLM agents can fetch full conversation context
- Persistent memory across gaming sessions

**Usage Example** (for AI integration):
```php
$chatService = \Drupal::service('dungeoncrawler_content.room_chat_service');
$messages = $chatService->getChatHistory($campaign_id, $room_id);
// Pass $messages to LLM as conversation context

// Post NPC response
$chatService->postMessage(
  $campaign_id,
  $room_id,
  'Guard Captain',
  'State your business, traveler.',
  'npc',
  null  // No character_id for NPCs
);
```

### Room Navigation

Chat history loads automatically when:
- User navigates to a new room
- Page loads with active room context
- `UIManager.setupChatLog()` is called

Context resolution uses:
- `hexmap.resolveCampaignId()` - Current campaign
- `hexmap.resolveActiveRoomId()` - Current room
- `hexmap.characterData.name` - Speaker name

## Styling

**File**: `css/hexmap.css`

**Design Tokens**:
- `--chat-bg`: Panel background
- `--chat-text`: Message text color
- `--chat-border`: Panel border
- `--accent`: Player message highlight

**Responsive**:
- Desktop: Matches sidebar width with `clamp(140px, 12%, 240px)` margin
- Mobile (<900px): Full width, no margin

## Testing Checklist

- [ ] Navigate to a room in hexmap
- [ ] Type message in chat input and submit
- [ ] Verify message appears in log
- [ ] Refresh page and confirm message persists
- [ ] Navigate away and back to room
- [ ] Confirm chat history reloads
- [ ] Test with multiple users in same room
- [ ] Verify access control (unauthorized users blocked)

## Future Enhancements

- **Typing Indicators**: Show when other users are typing
- **Read Receipts**: Track which messages have been seen
- **NPC Auto-Response**: Integrate with LLM for automated NPC dialogue
- **Message Reactions**: Add emoji reactions to messages
- **Chat Commands**: Support `/roll`, `/whisper`, etc.
- **Message Editing**: Allow users to edit recent messages
- **Search**: Find specific messages in chat history

## Troubleshooting

**Chat doesn't load**:
- Check browser console for API errors
- Verify `campaignId` and `roomId` are resolved correctly
- Confirm Drupal cache is cleared (`drush cr`)

**Messages don't persist**:
- Check database write permissions
- Verify `dc_campaign_dungeons` table exists
- Check watchdog logs: `drush watchdog:show --count=10`

**Access denied (403)**:
- Verify user has character in campaign
- Check campaign ownership
- Confirm `access dungeoncrawler characters` permission

## Related Documentation

- `ARCHITECTURE.md` - Overall module architecture
- `README.md` - General setup and usage
- `hexmap.js` - Frontend implementation details
