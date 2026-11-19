# Room Editor GM: design-assistance tools and harness

Status on 2026-11-19: **plan only, nothing built.**

The goal is the Room Editor GM agent and its harness, not any one room. An
author should be able to say "a gathering ground for 40 to 50 goblins" and
have the agent reason about size, type, contents and placement with real
tools, then act through the commands it already has.

## Where the agent stands

The Room Editor GM is a surface of the shared editor GM harness
(`EditorGmHarnessService`, `RoomEditorGmSurface`). It has about 27 tools in
five families: context, validation, definition, planning, execution.

Every one of them reads, changes, validates or publishes. None advises.

| The author asks | The agent can today |
|---|---|
| How big should this be? | Nothing. The only size rule is "at least one hex". |
| What is this room type for? | Nothing. `room_type` is one of 32 labels with no meaning attached. |
| What belongs in here? | List the whole catalog. It cannot filter by level, theme or fit. |
| Where should it go? | Place at a hex the author names. |
| Is this a good room? | Report schema and rule errors only. |
| Make it bigger. | `expand_footprint`, if given every hex. |

Seen in the session that prompted this: asked about sizing, the agent said
correctly that it had no tool for it, then moved on to asking for a name.
An earlier reply failed outright (`editor_gm_response_not_json`).

## What we are building

A sixth tool family, **advice**, plus the harness changes that make the
agent use it. Four parts.

### Part A. Design knowledge (data the tools read)

The agent cannot advise from nothing. Three pieces of original project
content, each with one owning service:

1. **Space rules.** Feet per hex, hexes per creature size, and crowding
   levels (packed, standing, fighting room). One table.
2. **Size bands.** A hex range for each `size_category`, so the label means
   something. Set from the rooms we have published, then reviewed.
3. **Room type profiles.** For each of the 32 types: purpose, usual size
   band, terrain and lighting, port counts, the kinds of contents it holds,
   and what makes it play well.

Content selection (which actors, hazards, traps fit a level and theme) is
not new knowledge: the encounter generator already has it. It is moved
behind one service that both play-time generation and the editor call.

### Part B. Advice tools (read-only)

Each returns facts, a recommendation and its reasoning. None changes a draft.

| Tool | Answers | In | Out |
|---|---|---|---|
| `advise_room_size` | How big? | occupants (count, size), crowding, large features | hex range, matching size band, gap from the draft, the arithmetic |
| `describe_room_type` | What is this type for? | room type | its profile |
| `suggest_room_type` | What type is this? | the author's description | ranked types with reasons |
| `suggest_room_contents` | What belongs here? | type, level, theme, danger | library actors, hazards, traps, obstacles, items that fit, each with a reason; gaps where the library has nothing |
| `suggest_placement` | Where does it go? | things to place | proposed hexes with the rule behind each |
| `review_room_design` | Is it good? | the draft | advisory findings: too small, no exit, clustered, unreachable hazard |

When the library has nothing suitable, `suggest_room_contents` says so and
points at the existing `generate_canonical_definition`; it never invents an
entry.

### Part C. Acting on advice (existing command path)

Advice is only useful if the agent can carry it out in one step.

- `expand_footprint` and `shrink_footprint` gain a target form: "about N
  hexes, keeping the shape". Today they need every hex listed.
- `suggest_placement` returns a ready `place_objects` plan.
- `suggest_room_contents` output feeds `place_objects` directly.

Everything still goes through `plan_room_commands`, preview, and
`apply_room_commands`. No new write path.

### Part D. Harness changes (what makes it an agent)

Tools alone do not change behaviour. The harness needs to:

1. **Register the advice family** in the tool registry and manifest, marked
   read-only so it never needs approval and can run in a dry run.
2. **Put design state in the grounding context.** What the agent sees each
   turn gains: occupants the author has stated, the size band the hex count
   falls in, and open design findings. Today it sees counts and validity.
3. **Add a design-brief record to the draft conversation.** Purpose,
   expected occupants, level, theme and danger, captured once from what the
   author says and reused by every advice tool, so the agent stops asking
   again. Stored with the conversation, not in the room.
4. **Teach the routing.** A sizing, type, contents or placement question
   must reach an advice tool, not a "no such tool" answer. Covered by
   example intents in the surface's contract.
5. **Chain advice into action.** After advice, the next step offered is the
   plan that applies it ("Grow the room to about 100 hexes?"), not the next
   unrelated checklist item.
6. **Fail usefully.** A reply that is not valid JSON is retried once with
   the parse error, and if it fails again the author is told to resend in
   plain words, not shown an error code.

## Build order

Each slice ends with something an author can use.

| Slice | Delivers | Size |
|---|---|---|
| 1 | Space rules, size bands, `advise_room_size`, target-size footprint goal, advice family registered | small |
| 2 | Design brief in the conversation; grounding context shows size band; routing examples; JSON retry | small |
| 3 | Room type profiles, `describe_room_type`, `suggest_room_type` | medium (32 profiles to write) |
| 4 | Shared content-selection service; `suggest_room_contents` | medium |
| 5 | `suggest_placement` | medium |
| 6 | `review_room_design`; size-band warning in validation | small |

Slices 1 and 2 make the goblin question answerable and actionable: the agent
would say 45 Small creatures need about 45 hexes packed, 90 standing or 135
with fighting room, that the draft has 19, and offer to grow it.

## How we will know it works

A fixed set of author requests, run against the agent with the model stubbed
for tool choice and one live pass per slice:

- "A gathering ground for 40 to 50 goblins" leads to a size recommendation
  and an offer to resize.
- "What kind of room is a shrine?" returns the profile.
- "What should guard this at level 3?" returns library actors, not the whole
  catalog.
- "Put the guards somewhere sensible" returns a previewable plan.
- "Is this ready?" returns design advice as well as rule errors.

## Rules that apply

- One path: each rule set has one owning service, used by tools, validation
  and generation.
- Advice never writes, and advisory findings never block publishing.
- The agent is the interface; these tools are capabilities it selects, not
  buttons the author is expected to find.
- Profiles and guidance are original content.

## Open questions for the Board

1. Should the same advice family go on the Dungeon Editor GM (how many rooms,
   which types, how they connect) once it works here?
2. Should size bands be warnings forever, or become a publishing rule later?
3. Who writes the 32 room type profiles: authored by hand, or drafted by the
   model and reviewed?
