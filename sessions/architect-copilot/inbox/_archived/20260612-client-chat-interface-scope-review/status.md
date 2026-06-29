# Status

- status: completed
- created_at: 2026-06-12T15:17:00+00:00
- current_phase: complete

## Notes

### 2026-06-12 — Kickoff
- Created from direct user request to formalize subsystem boundaries.
- Initial focus: confirm client chat acts only as submission/display and cannot own encounter authority.

### 2026-06-12 — Initial inventory + boundary hardening
- Confirmed client submission path: `ChatPanel` submit -> `submitRoomChatMessage` -> `postChatMessage` -> POST `/api/campaign/{campaign_id}/room/{room_id}/chat`.
- Confirmed client render path consumes server response envelopes/stream lines; local-only notices stay non-authoritative UI.
- Applied explicit top-level client scope commentary in `js/v2/panels/ChatPanel.js` documenting submission/display-only responsibilities.

### 2026-06-12 — Explicit inventory completed
- **Submission entry points (client -> server):**
  - `setupChatLog()` submit handler dispatches by active view.
  - Room submit: `submitRoomChatMessage()` -> `postChatMessage()` -> `/api/campaign/{campaign_id}/room/{room_id}/chat`.
  - Deferred room replay: `flushDeferredRoomMessages()` -> `postChatMessage()`.
  - Session-view submit: `postSessionViewMessage()` -> `ChatSessionApi` (`postPartyChat`, `postGmPrivate`, and GM generation endpoints).
- **Rendering entry points (server/event -> client transcript):**
  - Bus-driven: `handleBusChatMessageReceived()`, `handleBusSystemMessage()`.
  - History/session hydration: `renderRoomChatHistory()`, `renderSessionViewData()`.
  - Stream-driven: `consumeStreamedChatResponse()` NDJSON handlers.
  - Encounter event rendering: `handleGameEvents()`.
  - Transcript insertion sink: `appendChatLineToTarget()` / `appendChatLine()`.

### 2026-06-12 — Authority boundary verification
- Verified `ChatPanel` does not advance encounter turns/rounds or decide action legality; those remain server/coordinator authority.
- Verified turn/round/current-actor values rendered by chat are read from server payloads/events.
- Verified local system notices are tagged as local (`authority: local`, `messageClass: local_ui_notice`) and are not treated as authoritative transcript state.

### 2026-06-12 — Mixed-responsibility findings (client-side)
- **1) GM command parsing and intent routing in client**
  - Current location: `parseGm*` helpers + `postSessionViewMessage` branching.
  - Risk: workflow policy split across UI and server.
- **2) Client-side room chat workflow orchestration**
  - Current location: `roomChatDeferredMessages` + `flushDeferredRoomMessages`.
  - Risk: submission serialization policy anchored to one UI runtime.
- **3) Client-side domain action branching by feature type**
  - Current location: `postSessionViewMessage` dispatch to room/quest/dungeon/location generation endpoints.
  - Risk: contract drift as server intent surface evolves.
- **4) Optimistic/local transcript insertion**
  - Current location: pending/progress insertion via `appendChatLine*`.
  - Risk: local lines can appear before final server ordering is known.

### 2026-06-12 — Final recommendation + follow-ons
- Keep client scope as transport + renderer.
- Move command parsing/branching policy to server intent contracts.
- Keep only non-authoritative local placeholders for responsiveness.
- Concrete follow-ons:
  1. Add one server-side GM/private intent endpoint that accepts raw message + context and performs canonical routing.
  2. Repoint `postSessionViewMessage(gm-private)` to that single endpoint; remove `parseGm*` client branching.
  3. Replace client queue semantics with server-managed continuation contract for room-chat turn serialization.
  4. Require final transcript ordering to come from server event/message IDs; keep local pending lines explicitly non-authoritative.
