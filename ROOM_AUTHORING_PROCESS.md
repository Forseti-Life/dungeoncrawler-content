# Room Authoring Process

How a room is built in the Room Editor, and how its GM agent handles
requests. See also `DUNGEON_AUTHORING_PROCESS.md` and
`STORYLINE_AUTHORING_PROCESS.md`.

## What a room is

A room is a hex footprint with entry and exit ports and optional placed
objects. It is edited as a draft through commands and becomes placeable in
a dungeon when a version is published. `RoomEditorService` is the only
writer.

## Stages

1. Identity: name and description.
2. Layout: at least one floor hex.
3. Ports: at least one entrance, exactly one default entrance.
4. Contents: whatever is placed must exist in the library. Placing objects
   is optional; an empty room (a road, a clearing) is a finished room.
5. Validation.
6. Published.

The status card in the GM panel shows the percentage, the current stage and
what is still missing, from `RoomEditorService::summarizeDraftState()`.

## Validation findings

Every finding has a severity, a code, a message and a route:
`draft_command` (an edit here) or `definition_editor` (a placed object's
definition is missing or invalid). A code with no declared route throws.

## Built for a storyline

- `load_story_brief` returns what each storyline that names this room
  needs from it: its narrative, scene, tasks, the objectives that name it
  and the cast that starts there. Read it before generating a layout or
  placing contents.
- A storyline's rooms are normally built from the Dungeon Editor, by
  `build_site_from_story_brief`, through the same room build
  (`CanonicalRoomBuildService`). A room's exits follow its place in the
  site: the last room of a site has none.
- Who starts in a room is set by the storyline. The Room Editor shows it
  through the brief; it does not own it. Once a campaign is running, where
  an actor is belongs to the campaign.

## GM Agent Request Flow

Same harness and rules as the Dungeon Editor. Replay check:
`tests/editor_gm_intent_contract_check.php -- room <draft_id>`.
