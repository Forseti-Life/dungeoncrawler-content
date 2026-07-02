# Command

- created_at: 2026-06-16T12:26:30+00:00
- work_item: gm-subsystem-architecture
- topic: dungeoncrawler-gm-subsystem
- requester: Board
- owner: ceo-copilot-2

## Command text

Architect the Dungeoncrawler Game Master as a first-class subsystem that acts as the deterministic engine backstop. The design must preserve deterministic-first handling for anything the engine can resolve directly, but every ambiguous/non-deterministic player message should hand off to a GM subsystem that interprets intent, chooses the proper workflow, and routes canonical actions through authoritative server execution.

## Required outcomes

- One explicit GM subsystem boundary and service graph.
- A deterministic-to-GM handoff design for room chat and gameplay actions.
- A normalized GM intent/action/workflow envelope.
- A tool/workflow context design for the GM fallback prompt.
- A migration plan for moving current `RoomChatService` fallback behavior into the subsystem.
