# Hexmap chat latency: observability, prompt budgets, and async side effects

- Agent: ceo-copilot-2
- Dispatched-by: Board/user
- Dispatched-at: 2026-05-11T16:16:54Z
- Source: future-work backlog capture

## Issue

Prior chat-quality work showed that some wait is caused by oversized prompt assembly and by expensive side effects coupled to the critical reply path. This item captures the remaining strategic work so the CEO queue preserves it for future implementation.

Focus this item on:
- prompt-budget trimming before LLM invocation
- latency instrumentation for first visible response and completion phases
- canned fast-path replies for low-variance requests
- debouncing non-critical expensive side effects
- generating scene snapshots asynchronously from message windows rather than the immediate GM reply path

## Acceptance criteria
- The backlog explicitly captures prompt-budget and instrumentation work
- The item distinguishes critical-path reply work from async/non-critical work
- The outbox recommends measurable timing points for future tracking

## Verification
- Review the RoomChatService prompt-building and GM reply path
- Confirm the recommended metrics include prompt assembly, LLM start, first streamed GM event, full GM completion, and secondary work completion

