# Command

- created_at: 2026-06-16T01:09:36+00:00
- work_item: actor-action-availability-architecture
- topic: actor-action-availability
- requester: Board
- owner: architect-copilot

## Command text

Architect the actor action-availability subsystem for Dungeoncrawler so every actor can be given a clear authoritative set of currently legal actions and concrete options. The design must cover encounter AI prompts, freeform actor prompts, UI/action rails, and server execution with strict server authority. Account for spells, feats, consumables, item activations, hazard actions, and other branching actions that may require LLM interpretation to choose among legal options.

Board clarification:
- Build toward **one shared `getAvailableActions`-style resolver for all actors, including the GM**.
- The GM should be able to pass an actor identifier to the shared resolver/tool and receive that actor's authoritative current action availability on demand.
- NPCs, GM tooling, UI/action rails, and server validation should converge on the same canonical actor-scoped availability envelope rather than duplicating narrow builders.

## Required outcomes

- One canonical server-side action-availability resolver boundary.
- A normalized actor action envelope suitable for prompts, UI, and execution validation.
- A migration plan away from narrow/duplicated action builders.
- Explicit architectural treatment of high-option families (spells, feats, consumables).
