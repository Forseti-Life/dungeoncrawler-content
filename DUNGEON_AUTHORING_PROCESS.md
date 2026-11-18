# Dungeon Authoring Process

How a dungeon (a site) is built in the Dungeon Editor, and how its GM agent
handles requests. The Storyline Builder's equivalent is
`STORYLINE_AUTHORING_PROCESS.md`; the Room Editor's is
`ROOM_AUTHORING_PROCESS.md`.

## What a dungeon is

A dungeon is a set of published rooms placed on a level, linked port to
port, with one level entrance. It is edited as a draft through commands and
becomes usable when a version is published. `DungeonEditorService` is the
only writer.

## Stages

1. Identity and purpose: name, description, theme.
2. Rooms placed: at least one, each a published room version.
3. Links and entrances: exactly one level entrance, every room reachable
   from it, every exit linked.
4. Contents and encounters: read from the placed room versions.
5. Validation.
6. Published.

The status card in the GM panel shows the percentage, the current stage and
what is still missing. It is computed from the saved draft and its
validation by `DungeonEditorService::summarizeDraftState()`.

## Validation findings

Every finding has a severity, a code, a message and a route that says how
it is fixed:

- `draft_command`: an edit in this editor.
- `room_editor`: a room must be built, fixed or republished.
- `definition_editor`: a placed object's definition is missing or invalid.
- `outside_editor`: no edit here changes it (for example a dungeon in use
  by a running campaign).

A code with no declared route is a programming error and throws.

## Built for a storyline

A site a storyline names is built from that storyline's brief.

- The Dungeon Manager (`/dungeons`) lists the library, working drafts with
  the status this editor reports, and the storylines each dungeon serves.
- `list_unattached_dungeons` is the standing check: every published
  dungeon no storyline names, with the campaigns using it.
- Validation warns when the build differs from a storyline that names the
  dungeon: a room the story lists is not placed (`story_room_not_placed`),
  or the level entrance is not the story's entrance room
  (`story_entrance_differs`). The status card lists the storylines.
- The first room a story site lists is its entrance and the room without
  an exit is its end; the layout planner fixes both. A through-room built
  for a site has its exit across the room from its entrance.
- `load_story_brief` returns, for each storyline draft that names the
  site: its purpose, its entrance, its rooms in story order with each
  room's scene, tasks, objectives and starting cast. It reads the storyline
  drafts on every call; nothing is copied into the dungeon.
- `build_site_from_story_brief` builds the site in steps. Each call builds
  the rooms the story names that the room library lacks, within a time
  budget; once they all exist it places and links them in this draft.
  Saved state is the cursor, so calling it again continues. It never
  publishes.
- The site is laid out as a path from its entrance. The entrance comes
  first; the last room in story order is the end of the path and is built
  with no exit. Every other room is built with one.
- One site holds at most 30 rooms
  (`CanonicalDungeonLayoutPlanService::DUNGEON_MAX_PLACEMENTS`). A larger site is refused before
  anything is built; split it in the storyline.
- Opening the Dungeon Editor on a site that does not exist yet starts a
  draft for it under that ID.

The Storyline GM does not build rooms or dungeons. A storyline finding
that needs a site sends the author here.

## GM Agent Request Flow

Same harness as the Storyline Builder.

1. Status and "what's next" are answered from saved state. The harness
   states the single next step; the model is not asked for one. Order:
   missing content, first finding fixable here, first finding needing
   another editor, first warning.
2. Other requests go to the model, which must call one function. Each tool
   is its own function with its declared arguments; a reply outside its
   schema is rejected and logged with the raw reply.
3. A tool that changes the draft is a proposal the author approves.

`tests/editor_gm_intent_contract_check.php` replays fixed author messages
against a draft, read-only: `-- dungeon <draft_id>`.

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
