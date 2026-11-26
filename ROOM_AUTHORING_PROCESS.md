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

## Placing objects in bulk

Bulk commands: `place_objects_bulk`, `move_objects_bulk`,
`remove_objects_bulk`, `replace_objects_bulk` and
`dismiss_placement_gaps`; at most 5000 hexes per command, 2500 placements
per room. Shapes place obstacles and items only. Ports and the hex outside
each stay clear unless `keep_ports_clear` is false.

A hex that cannot take an object (outside the room, kept clear, `void` or
`wall` terrain, or already holding an actor or obstacle) is skipped, never
fatal. The skip is a gap in `metadata.placement_gaps` (`gap_id`,
`operation_id`, `reason`, `blocker`, `hex`). Open gaps give the
`placement_gap_open` warning while editing and an error at publication;
published rooms carry none. Re-running an item with its `gap_id` updates or
closes that gap instead of adding another.

GM tools (planning tools return a command plan for `apply_room_commands`):
- Shapes: `place_line`, `place_ring`, `place_fill`, `place_scatter`,
  `place_grid`, `place_along_edge`. A `seed` makes scatter and weighted
  `choices` repeatable.
- Gaps: `list_placement_gaps`, `retry_placement_gaps`,
  `nudge_placement_gaps`, `resolve_placement_gaps` (dismiss with a reason,
  or remove the blocker and place).
- Selection edits (by `instance_ids` or a filter; obstacles and items only):
  `move_selection`, `rotate_selection`, `mirror_selection`,
  `copy_selection`, `remove_selection`, `replace_selection`.
- Inspection: `list_placements`, `inspect_blocking`, `analyze_navigation`
  (flood fill from the first entry port; lists unreachable hexes and ports,
  and gaps whose blocker would reopen a route).

## Street rooms

A room with `room_type: street` is the walkable background of a district
dungeon. It needs `metadata.background` (`url`, optional integer `width` and
`height`, `alt`) and exit ports for its doors; an exit port hex is the door
hex a building shares. Findings: `room_background_invalid`,
`street_background_missing` (a warning while editing, an error at
publication) and `street_has_no_doors`. GM: `plan_room_commands` goal
`set_metadata` takes `room_type: street` and `background`; `add_exit_ports`
adds the doors.

## Built for a storyline

- The Room Manager (`/rooms`) lists the library, working drafts with the
  status this editor reports, and the dungeons and storylines using each
  room.
- `CanonicalRoomBuildService::generateAndPublish` is the one sequence that
  turns a generated layout into a published room; the site build and
  play-time generation both run it.
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

## Page format

The editor uses the page format of the Storyline Builder
(templates/editor-page-shell.html.twig, templates/editor-gm-agent.html.twig):

- Header: title, what the page is for, back to the manager.
- Left column: the process stages with their state (done, now, open), the
  Current state card (percent complete, validity, current stage, minimum
  content still needed), then quick links: the manager pages, what this
  links to (a dungeon lists its rooms; a room lists the dungeons that use
  it) and the storylines that use it.
- Workspace: the Game Master Agent on top (suggested requests,
  conversation, composer), then the toolbar and the map at full width.
  Author Tools still opens as a drawer beside the map.

The left column is drawn from the status projection (summarizeDraftState)
by js/v2/editor/EditorDraftStatusCard.js; nothing is computed in the
browser. Stages show status only; they do not switch tools yet.

Every editor's agent conversation is rendered by one client,
js/v2/editor/EditorGmChatClient.js, with one set of styles
(css/gm-storyline-builder.css): Storyline, Dungeon, Room, Definition and
Character pages.

The same page format is used by every authoring page: Storyline Builder,
Dungeon Editor, Room Editor, Definition Editor (library, family lists and
the definition form, laid out by Render/DefinitionEditorPage and
templates/definition-editor-page.html.twig) and Character Setup
(templates/character-setup-page.html.twig).
