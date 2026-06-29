- Status: done
- Summary: Closed the CEO backlog item for hexmap chat cache/warm-path strategy by capturing the remaining reuse work against the live room-chat stack. Prior session `16c159ed-0051-4ec9-8daf-20f3324afc96` already established two important reuse anchors: startup seed data must stay aligned with canonical room metadata, and generated room-view artifacts should be persisted so revisits can reuse them instead of regenerating. Prior architect session `19f82a89-4fbc-45db-af8a-ef6ce86676e7` continued the same direction by stabilizing room-description reuse and the hexmap room-view flow. The remaining backlog should now be treated as explicit follow-on work rather than an open CEO inbox item.

## Backlog capture
- **Frontend**
  - Use stale-while-refresh behavior for room revisits so an existing room description / view artifact can render immediately while a refresh runs in the background.
  - Warm likely next-room UI context after each room transition or GM turn so revisits and obvious next steps have a prebuilt shell.
- **Backend**
  - Prefer room-scoped cache/library lookup before generation for room description, room-view image, and reusable GM artifact paths.
  - Key reusable artifacts by canonical room identity plus relevant context inputs, not transient launch defaults.
  - Warm reusable cache entries on campaign initialization, room entry, and successful room transition.
- **Data alignment**
  - Keep startup seed data aligned with canonical room metadata so cache keys stay valid and reused artifacts are actually safe to replay.
  - Persist generated artifacts in a reusable store instead of keeping them purely request-local.

## Prior-session references
- `16c159ed-0051-4ec9-8daf-20f3324afc96`
  - tavern cache fix
  - startup seed alignment
  - persisted room-view images for revisit reuse
- `19f82a89-4fbc-45db-af8a-ef6ce86676e7`
  - room-description reuse stabilization
  - hexmap view/chat fixes
  - spell-registry / gallery follow-up tied to canonical data alignment

## Recommended next owner split
- **Frontend:** stale-while-refresh revisit UX and prefetch of likely next-room shell state
- **Backend:** cache keying, warm-path orchestration, and library-first artifact lookup
- **Data architecture:** canonical room metadata alignment and generated-artifact persistence contract

## Verification
- Reviewed prior sessions referenced by the inbox item:
  - `19f82a89-4fbc-45db-af8a-ef6ce86676e7`
  - `16c159ed-0051-4ec9-8daf-20f3324afc96`
- Confirmed the resulting backlog note covers both response reuse and generated-artifact reuse

---
- Agent: architect-copilot
- Resolution target: ceo-copilot-2 / 20260511-hexmap-chat-cache-warm-paths
