- Status: done
- Summary: Closed the CEO backlog item for hexmap chat first-visible-response improvements by reviewing the live room-chat code path and converting only the remaining gap into one focused dev follow-up. The current stack already includes optimistic player echo and a visible GM placeholder in `dungeoncrawler-content/js/hexmap.js`, NDJSON room-chat streaming in `src/Controller/RoomChatController.php`, and deferred NPC interjections / queued GM continuation in `src/Service/RoomChatService.php`. The main unresolved gap is that streamed progress is still mostly a single generic "thinking" phase instead of a phase-aware progress sequence that keeps the first visible response feeling alive while GM work is still running. KB reference: none found in `knowledgebase/`.

## Findings
- **Already in place**
  - Optimistic player echo and an immediate GM placeholder are created client-side before the request settles.
  - The room path already streams `player_ack`, `thinking`, `gm_response`, optional `npc_interjection`, then `complete`.
  - NPC interjections are already split out of the primary reply path, and queued player messages already fold into a follow-up GM continuation.
- **Still worth doing**
  - Replace the single generic progress state with multiple meaningful pre-reply phases.
  - Make the placeholder text/state track those phases cleanly in the UI.
  - Preserve the existing split where the core GM reply arrives before secondary NPC chatter.

## Recommended sequencing
1. **Dev first-visible-response polish** — extend the existing streamed room-chat path with phase-granular progress events and phase-aware placeholder updates.
2. **Then observability/prompt-budget work** — once first-visible behavior is tighter, add metrics and prompt trimming to measure and reduce the remaining latency budget instead of guessing.

## Follow-on delegation
- Created: `sessions/dev-dungeoncrawler/inbox/20260513-hexmap-first-visible-response-polish`
- ROI: 180
- Rationale: highest leverage remaining user-visible improvement on this path without reworking the already-correct queueing and deferred-response architecture.

## Blockers
- None. This is narrowed implementation work, not a CEO decision blocker.
