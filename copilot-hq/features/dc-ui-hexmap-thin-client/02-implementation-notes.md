# Implementation Notes: dc-ui-hexmap-thin-client

## Responsibilities that can stay in the client

- Pixi scene rendering
- Camera pan/zoom
- Selection/hover state
- Drawer/chat visibility state
- Transient animation after server-confirmed results

## Responsibilities that should move or stay authoritative on the backend

- Combat turn progression, attack results, NPC turns, and action economy
- Movement legality and destination validation
- Interaction legality and world mutation (`open_passage`, `open_door`, `move_object`, quest item collection)
- Room navigation/materialization
- Canonical character-state hydration for the active player

## Backend coverage already present

- Combat lifecycle and current-state sync via `CombatEncounterApiController`
- Room chat via `RoomChatController` and `RoomChatService`
- Session-view chat via `ChatSessionManager`
- Inventory via `InventoryManagementController`
- Quests/confirmations via quest services/controllers
- Entity movement persistence via `CampaignEntityController::moveEntity()`

## Architectural gotchas

- `hexmap.js` currently mixes UI, orchestration, and engine-style logic; split it by seams instead of attempting a one-shot rewrite
- `MovementSystem`, `CombatSystem`, and `TurnManagementSystem` are still instantiated client-side; even when the server is authoritative, the client continues to project behavior locally
- `handleNavigationResult()` mutates local dungeon state and injects rooms/entities/connections on the client; that is a backend-authority smell
- `showLaunchCharacter()` and `showEntityInfo()` represent different ownership models for state; thin-client cleanup should define one canonical backend-fed view model for player state vs inspected entity state
- Polling current-state every 3 seconds is workable, but the thin client needs a deliberate optimistic/reconcile policy to avoid UI drift

## Recommended execution order

1. Split `hexmap.js` into display/ui/api-adapter modules without changing behavior
2. Introduce authoritative backend scene/participant contracts for movement/interactions/navigation
3. Remove client-side authority from combat/movement/world mutation paths
4. Shrink the remaining client to render + selection + chat + shell concerns

## Execution policy

- `dev-dungeoncrawler` should work through architectural issues that are solvable inside the scoped feature instead of stopping for approval on first contact with complexity.
- `dev-dungeoncrawler` should send a PM update the same cycle when an architectural issue changes **scope, sequence, dependency assumptions, backend contract shape, or release sizing**.
- `pm-dungeoncrawler` should treat those updates as roadmap inputs: re-baseline dependent backlog items, split follow-on work if needed, and adjust release targeting without blocking execution unnecessarily.
