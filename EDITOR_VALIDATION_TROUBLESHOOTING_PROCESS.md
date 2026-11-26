# Editor Validation Troubleshooting Process

## Common envelope

Every editor agent uses the same evidence and authorization envelope:

1. select the first blocking error, then the first warning;
2. scope to one owning object and its required dependency;
3. identify the native editor tool and the fields it may change;
4. ask no question unless one creative/data fact is genuinely missing;
5. apply one authorized native operation;
6. revalidate the exact finding;
7. only then move to the next finding.

`describe_validation_troubleshooting` exposes this packet to the Room and
Dungeon GM agents. The Storyline GM receives the same packet through its
context assembler. The packet is guidance; validation remains the source of
truth.

## Storyline workflow

The Storyline GM resolves one authored row or canonical reference. It never
creates a room or edits a dungeon. When the owning object is another editor's
authority, it routes or delegates there.

## Site Guide boundary

The Site Guide is the read-only discovery layer above the editor harnesses.
It may explain an editor finding, identify the owning agent, and provide a
permission-filtered route or scoped handoff receipt. It must not call editor
tools, change draft data, or imply that a delegated mutation was completed.

The MCP gateway exposes only discovery resources and handoff receipts. An
editor handoff must include a draft scope; a runtime handoff must include an
authorized campaign scope. The target editor or GM remains responsible for
revalidating identity, object access, revision, and mutation authority.

## Dungeon workflow

The Dungeon GM owns district/dungeon topology:

1. A district must have one ground/street room before any building is
   placed.
2. Ground creation sizes from the required building count and is published
   before buildings.
3. If the ground cannot hold the buildings with walkway clearance, resize
   within the room contract, repin its placement, then reflow buildings.
4. Missing canonical rooms are delegated to the Room Editor GM. Placement
   waits for publication.
5. Published buildings use `plan_district_building`; ordinary dungeon rooms
   use port-sealed or explicit placement tools.
6. The Dungeon GM never authors room geometry.

## Room workflow

The Room GM owns one room only:

1. Load the Storyline brief when the room is delegated by a Dungeon/Storyline
   finding.
2. Change identity, footprint, ports, objects and publication through
   RoomEditorService commands.
3. Revalidate and publish when publication is part of the delegated task.
4. Return the published room/version to the Dungeon GM.
5. Never mutate a dungeon or storyline draft.

Delegated creation may reserve the exact room ID supplied by the Storyline
binding. Delegated edits resume or create the active room draft, apply native
commands, and publish under the Room Editor authority.

## Impasses and defects

If the required resolver does not exist, a schema cannot store the requested
field, or a native tool cannot represent the operation, the agent must:

1. stop data mutation;
2. tell the user the exact blocked finding and owning editor;
3. call `submit_backlog_suggestion` with the smallest required code/config
   change and its verification;
4. not invent a workaround or edit the codebase.

This boundary lets the same troubleshooting discipline apply to different
problem types without forcing them through one creative or technical flow.
