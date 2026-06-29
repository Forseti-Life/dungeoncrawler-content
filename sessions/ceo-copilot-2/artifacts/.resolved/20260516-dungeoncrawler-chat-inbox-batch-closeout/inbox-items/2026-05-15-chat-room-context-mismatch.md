# Chat room-context mismatch

## Objective
Investigate whether room-chat prompt assembly is sometimes using a mismatched active room versus the room pinned in the current URL/chat context, and fix any context-selection drift.

## Source
- Live campaign review: `campaign_id=28`
- URL room: `7f2f1051-5f88-45a2-a66a-0f7063900001` (`The Gilded Tankard`)

## Evidence from live state
- Latest dungeon snapshot for campaign 28 shows:
  - URL room under review: `7f2f1051-5f88-45a2-a66a-0f7063900001`
  - `active_room_id`: `3767e16c-37b3-489e-a4fc-f39bed7b87da`
- This may be benign, but it is a plausible source of context drift if some prompt builders use `active_room_id` while others use the explicit room/chat target.

## Why this is a problem
- Can cause the prompt to pull roster, state, or narrative context from the wrong room.
- Could explain generic or ungrounded replies and target instability.
- Creates hard-to-debug live inconsistencies between UI state and model state.

## Required work
- Audit all room-chat prompt builders and helpers for whether they use:
  - explicit requested `room_id`
  - current active room
  - latest persisted room state
- Confirm the same room is used consistently across:
  - chat history lookup
  - prompt artifact assembly
  - NPC roster lookup
  - session context
  - response persistence

## Verification required
- Confirm the live chat for a pinned room never pulls context from a different room unless the user intentionally transitions rooms.
