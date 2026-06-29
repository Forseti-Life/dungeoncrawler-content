- Status: done
- Summary: Closed the CEO backlog item for hexmap chat observability, prompt budgets, and async side effects by mapping the remaining strategy directly to the live room-chat code path. `RoomChatService` already records useful internal stages such as `gm.user_prompt_assembly`, `gm.system_prompt_assembly`, `gm.response_cache`, `gm.reality_checked_generation`, and timed model calls via `invokeTimedModelCall()`, while `RoomChatController` streams staged room-chat events over NDJSON. The gap is not absence of instrumentation but lack of a narrower latency budget and a clearer split between critical-path GM reply work versus non-critical follow-up work.

## Backlog capture
- **Prompt-budget work**
  - Trim assembled room-chat prompt inputs before LLM invocation, especially session context, artifact summaries, and room grounding blocks on low-variance turns.
  - Expand deterministic / cache short paths for low-variance requests before falling back to prompt-heavy GM generation.
- **Observability**
  - Expose timing points for:
    - prompt assembly start/finish
    - LLM invocation start
    - first streamed GM progress event
    - full GM response completion
    - deferred NPC / secondary work completion
  - Keep the existing debug trace detail for investigation, but surface a smaller product-facing timing summary for routine tracking.
- **Async side effects**
  - Keep core GM response on the critical path.
  - Push secondary work such as scene-snapshot generation and other non-critical expensive side effects behind the initial visible reply path.
  - Debounce repeatable secondary work when multiple room messages land in a short window.

## Current-code anchors
- `RoomChatController::streamChatMessage()` and queued continuation streaming already separate progress events from final GM response delivery.
- `RoomChatService::buildClientTimingSummary()` currently exposes only coarse totals (`total_ms`, `gm_ms`, cache status, response source).
- `RoomChatService::invokeTimedModelCall()` already measures provider wait and prompt/system prompt summaries.
- `RoomChatService` already has deterministic short-path and cache hooks that can support canned fast-path replies for low-variance turns.

## Recommended measurable timing points
1. prompt assembly
2. LLM start / provider wait
3. first streamed GM progress event
4. full GM response delivered
5. deferred NPC / secondary work complete

## Verification
- Reviewed the live room-chat prompt-building and GM reply path in:
  - `dungeoncrawler-content/src/Controller/RoomChatController.php`
  - `dungeoncrawler-content/src/Service/RoomChatService.php`
- Confirmed the note distinguishes critical-path reply work from async/non-critical work
- Confirmed the recommended metrics include prompt assembly, LLM start, first streamed event, full GM completion, and secondary completion

---
- Agent: architect-copilot
- Resolution target: ceo-copilot-2 / 20260511-hexmap-chat-observability-prompt-budget
