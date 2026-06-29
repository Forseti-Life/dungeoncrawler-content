Now I have enough context to implement the full feature. Let me make the changes:

Now I have everything I need. Let me implement the full feature:

Now add calls to the new methods at end of syncEntityToSprite (after the direction indicator block):

Now refactor `updateHealthBar` for dynamic sizing, and add the 4 new methods after it:

Now update `removeSprite` to clean up the new overlay objects:

Now add `syncTokenBadgeState` to hexmap.js and wire it up. First, find a good place to add it (near `selectEntity`):

Now I have all the context. Let me make the three edits to hexmap.js:

Now add `syncTokenBadgeState()` call to `onTurnChange`:

Now add the `syncTokenBadgeState` method. Find a good insertion point:

Now verify syntax on both files:

Both pass. Commit:

Write artifact and outbox:

- Status: done
- Summary: Implemented all dc-ui-token-readability acceptance criteria. Refactored `updateHealthBar` to a single dynamically-sized PIXI.Graphics (zoom-aware width/offset). Added `updateTeamRing` (color-coded allegiance ring behind sprite), `updateSelectionRing` (blue=selected, gold double-ring=active turn), `updateConditionBadges` (compact labeled dots, hidden at low zoom, wired for future server hydration), and `updateInteractMarker` (exclamation badge, hidden at low zoom). Added `syncTokenBadgeState()` to hexmap.js to iterate all entities and set `_isSelected`, `_isCurrentTurn`, `_conditions`, and `_isInteractable` flags on RenderComponent; called from `selectEntity`, `deselectEntity`, and `onTurnChange`. Removed the broken sprite tint selection hack (was a no-op — RenderSystem overwrote it each frame). All new overlays respect the hidden-entity early-return path (AC-004). Commit: `5ce9655e2`.

## Next actions
- QA can verify via the hexmap demo page: entity selection should show blue ring, active-turn entity should show gold double-ring, team allegiance rings should be visible on all entities with CombatComponent, interactable items/NPCs show `!` badge, condition badges appear when `render._conditions` is populated (currently empty — future server hydration)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 25
- Rationale: Token readability is the core in-session usability feature for the map; players can now identify allegiance, selection state, and turn order at a glance without inspecting the sidebar. Unblocks QA Gate 2 for this feature.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260418-133559-impl-dc-ui-token-readability
- Generated: 2026-04-18T13:44:02+00:00
