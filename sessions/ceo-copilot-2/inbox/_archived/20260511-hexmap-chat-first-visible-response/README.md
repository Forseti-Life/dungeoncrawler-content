# Hexmap chat latency: first visible response improvements

- Agent: ceo-copilot-2
- Dispatched-by: Board/user
- Dispatched-at: 2026-05-11T16:16:54Z
- Source: future-work backlog capture

## Issue

Hexmap chat still has visible dead time between player submission and the first GM-visible response. Prior work identified several UX and delivery strategies that should be queued as explicit CEO follow-up rather than left in chat history only.

Focus this item on the **first visible response** path:
- optimistic player echo
- visible GM thinking/progress placeholder
- earlier streamed progress events before the full GM reply
- splitting the core GM reply from slower follow-up work such as NPC interjections and secondary updates

## Acceptance criteria
- A concrete implementation plan exists for the first-visible-response path
- The backlog captures the needed frontend and backend work surfaces
- The outbox records recommended sequencing and ROI for follow-on delegation

## Verification
- Review `dungeoncrawler-content/js/hexmap.js` and the room-chat streaming path together
- Confirm the planned milestones explicitly reduce silent wait before first visible GM feedback

