# Storyline Authoring Process

## Purpose

The Storyline Builder is an author-directed workflow assisted by the Game
Master Agent. The GM analyzes, asks questions when intent is unclear, presents
alternatives, applies clear requests directly to the draft, and validates the
resulting storyline. It does not silently choose among ambiguous creative
directions.

The process produces one connected, reviewable
`storyline-authoring-draft-v1` object and then one canonical storyline
definition. Stages organize the work and report completion; they do not block
navigation or require separate approval actions.

## Decision Authority

The author owns:

- creative direction and tradeoff decisions;
- selection among alternatives;
- acceptance of each completed stage;
- acceptance of unresolved warnings;
- final canonical creation or publication.

The GM may:

- identify missing decisions and dependencies;
- inspect existing canonical content;
- present alternatives and explain their tradeoffs;
- draft or revise data after the author chooses a direction;
- identify inconsistencies, disconnected objects, and weak progression;
- apply revision-safe structured operations that implement clear author
  requests;
- run read-only previews, analysis, and validation.

The GM must not:

- select one of its own alternatives;
- invent a canonical object ID;
- create a vaguely requested object without clarification;
- conceal a failed validation or unresolved decision;
- publish a storyline without the author’s final action.

## Three-Option Rule

When the author asks to create an object but has not supplied enough direction
to distinguish a clear result, the GM must stop and present exactly three
options. It must not create a draft object or preselect an option.

This rule applies to every storyline-owned or linked object, including:

- storyline premise and identity;
- chapter, scene, task, objective, and conflict;
- relationship change;
- actor role or actor concept;
- dungeon or room requirement;
- item, clue, reward, or prerequisite;
- entry point, branch, consequence, resolution, and ending.

A request is vague when it omits the object’s narrative purpose, defining
constraints, important relationships, or intended effect on progression.
Examples include “add a villain,” “make a dungeon,” “give them an artifact,”
or “build the next scene.”

Each option must contain:

1. a short name;
2. the concept and narrative purpose;
3. the important objects or relationships it introduces;
4. its effect on player choices and progression;
5. its principal benefit and tradeoff.

The three options should be meaningfully different:

1. **Direct** — the clearest interpretation with low structural risk.
2. **Complicating** — introduces tension, cost, or a harder decision.
3. **Transformative** — changes an assumption or creates a less conventional
   direction while remaining compatible with stated constraints.

The author may select an option, combine named parts of options, or reject all
three and provide new direction. A selection must restate enough of the chosen
direction to remain meaningful without relying on hidden conversation state.

The rule does not apply when the author:

- provides a sufficiently detailed object specification;
- names the exact canonical object to link;
- edits an existing object without changing its purpose;
- selects one of the three previously presented options;
- requests deterministic formatting, normalization, preview, or validation.

## Object Ownership

The Storyline Builder owns the narrative definition and associations. It does
not introduce competing creation paths for canonical content.

### Storyline-owned records

These records are authored and saved in the storyline draft:

- identity, premise, hook, entry point, level range, tags, and ending;
- chapters and scenes represented by journal entries;
- tasks, objectives, dependencies, and unlock conditions;
- conflict arcs and their introduction, escalation, and resolution;
- scene-level relationship changes;
- actor, dungeon, room, and item bindings;
- generation and validation orchestration;
- narrative annotations and analysis inputs.

### Linked canonical objects

Actors, items, dungeons, and rooms remain owned by their existing canonical
definition or editor authority.

For each linked object, the Storyline Builder follows one path:

1. define the narrative need;
2. search existing canonical content;
3. select an existing object, or define a new-object specification;
4. route new-object creation to the owning editor;
5. return the resulting canonical ID to the storyline draft;
6. bind the ID to the appropriate chapter, scene, task, objective, actor, or
   prerequisite.

The storyline may not treat a proposed name as a canonical ID. Unresolved
references remain visible continuity warnings and do not block publication.

## Object Development Cycle

Every object passes through the same development cycle:

1. **Need** — State why the storyline needs the object and what player-facing
   function it serves.
2. **Constraints** — Record the stated tone, level, setting, continuity,
   relationship, and progression constraints.
3. **Options** — If the direction is vague, present exactly three alternatives
   and wait for the author.
4. **Selection** — Record the author’s chosen or combined direction.
5. **Skeleton** — Assign a stable draft ID, object type, purpose, and required
   links.
6. **Detail** — Flesh out the object-specific fields needed for play and
   validation.
7. **Connection** — Link it to every relevant scene, task, objective, conflict,
   actor, location, item, prerequisite, and consequence.
8. **Validation** — Check schema, canonical references, continuity,
   progression, and narrative function.
9. **Review and revision** — Show the completed object and its effects so the
   author can revise it directly or ask the GM for another change.

## Stage 1: Identity and Narrative Contract

### Decisions

- What is the premise?
- What experience and tone should players receive?
- What creates the initial player hook?
- Where and through whom does the storyline begin?
- What level range and scope does it support?
- What ending state should the storyline earn?
- Which themes and constraints must later stages preserve?

### Objects produced

- `storyline_id`
- `title`
- `description`
- `premise`
- `level_range`
- `tags`
- `player_hook`
- `entry_actor_id`
- `entry_location_id`
- `ending`

### Required detail

The premise identifies the central situation, affected parties, pressure, and
reason the players must act. The hook gives players an actionable reason to
engage. The ending describes a resulting world or relationship state, not
merely “the final encounter ends.”

### Completion check

Completion reports whether the narrative contract is populated. The author may
revise the premise, hook, or ending at any time; dependent completion and
validation results update from the new draft revision.

## Stage 2: Progression, Scenes, and Conflict

### Decisions

- What exact event disrupts the status quo, and what makes answering it
  necessary?
- How do the players cross into the adventure, and what creates the point of
  no return?
- Which escalating trials test them, and what midpoint revelation, reversal,
  or false victory changes the quest?
- What failure creates the all-hope-is-lost crisis, and what psychological
  pivot lets the players continue?
- What central confrontation forms the climax, and how does it answer the
  dramatic question established by the premise?
- What new equilibrium follows, and how are the protagonists permanently
  changed?
- What physical journey, antagonistic force, mentor or guide, and tangible
  boon or MacGuffin make the storyline an adventure?
- What are the major chapters and scenes?
- What changes in each scene?
- What is the central conflict and dramatic function of each scene?
- Which tasks and objectives express player progress?
- What unlocks, blocks, branches, or follows from player actions?
- Which conflicts are introduced, escalated, resolved, or intentionally left
  open?
- How do relationships change because of scene events?
- What is foreshadowed, and where is it paid off?

### Objects produced

- one `process_flow.narrative_architecture` object containing six ordered
  narrative beats:
  - `inciting_incident`: disruption and stakes;
  - `threshold_of_no_return`: crossing and point of no return;
  - `progressive_complications`: road of trials and midpoint shift;
  - `crisis`: dark night or all-hope-is-lost event and psychological pivot;
  - `climax`: central confrontation and resolution of the premise;
  - `denouement`: new equilibrium and internal shift;
- four adventure requirements within that same object:
  - defined physical quest;
  - antagonistic force;
  - mentor or guide;
  - boon or MacGuffin;
- journal entries representing ordered chapters and scenes;
- tasks and their progression dependencies;
- branch decisions with at least two choices, each containing:
  - a typed trigger, explicit condition, and affected source;
  - an interaction classification for action triggers;
  - actor effects, reactions, and deterministic state changes;
  - environment effects and deterministic state changes;
  - called-out storyline implications such as unlock, lock, reveal, redirect,
    escalation, or resolution;
  - a destination task and scene, or an explicit terminal outcome;
- objective completion criteria;
- conflict arcs;
- scene relationship changes;
- room, actor, and theme references needed by later stages.

### Required detail

Narrative architecture is process data, not decorative synopsis text. Each
beat states both what happens and why that event changes the direction,
stakes, options, or internal condition of the protagonists. The adventure
requirements name concrete story elements, not generic roles.

Each beat is implemented through the existing canonical objects rather than a
parallel plot model. Journal scenes establish its chronology and dramatic
function. Conflicts provide opposition and escalation. Tasks and objectives
make player action measurable. Actors embody protagonists, antagonists, and
guides. Dungeons and rooms realize the physical journey. Items realize a boon
or MacGuffin when the story uses one. The GM must call out any architecture
statement that has no supporting scene or object.

Every scene has a stable chapter and scene ID, title, summary, central
conflict, dramatic function, trigger, and intended change. Every required task
has a measurable completion condition. Unlock edges form an understandable
progression graph. Authored choice destinations are the authority for
conditional outgoing edges; `unlocks_to` and `unlocks_after` are their
canonical graph projection rather than a competing path definition. When
multiple mutually exclusive branches converge, selecting any valid predecessor
can unlock the convergence node.

Actor and environment effects cannot remain isolated notes. Each such effect
must include a storyline implication describing what the changed state means
for subsequent scenes, tasks, conflicts, reveals, or resolutions. Runtime
selection records `decision_id -> choice_id`, applies all declared state
changes atomically, and exposes only the selected path. Every active conflict
has an introduction and at least one planned escalation or intentional
open-thread decision.

Action decisions classify actor interactions as `other`, `attacked`, or
`killed`. An attacked or killed actor must have an explicit reaction. Killing
an actor must also have a storyline implication whose `source_actor_id`
identifies that actor and whose effect spells out what changes in the remaining
storyline.

### Completion check

The author reviews all six complete narrative beats, all four adventure
requirements, the ordered narrative spine, conflict lifecycle, major choices,
consequences, and intended progression. The stage remains incomplete while any
beat, requirement, scene, task, objective, or conflict is absent, but the
author can continue working in any stage.

## Stage 3: Actors and Relationships

### Decisions

- Which actor roles are required by each defined scene and conflict?
- Can an existing canonical actor fill each role?
- What does each actor want, know, fear, conceal, and risk?
- How does each actor communicate?
- Where and when is the actor available?
- Which relationships change, and why?

### Objects produced

For each storyline actor binding:

- `contact_id`
- canonical `actor_id`
- role and display name
- motivation
- dialogue guidance
- knowledge
- attitude and availability
- chapter, scene, and location bindings

### Required detail

An actor exists because it performs a necessary narrative or player-facing
function. Motivation and knowledge must support what the actor does in linked
scenes. Dialogue guidance differentiates the actor’s voice. Relationship
changes identify both actors, before and after states, and an on-screen reason.

Every participant is an actor, including people, creatures, monsters, groups,
and environmental agents. If a clear author request or accepted concept
requires a missing actor, the GM creates it through the canonical actor
authority and links it back by ID.

### Completion check

Cast coverage, motivations, knowledge boundaries, relationship arcs, and
canonical actor selections contribute completion and continuity warnings; they
do not gate publication.

## Stage 4: Dungeons, Rooms, and Traversal

### Decisions

- Which defined scenes require a dungeon or room?
- What narrative and gameplay purpose does each location serve?
- Can an existing canonical dungeon or room satisfy the need?
- How do players enter, traverse, leave, and revisit the space?
- Which room contains each scene, actor appearance, task, clue, or item?
- Which location constraints affect progression or conflict?

### Objects produced

For each dungeon binding:

- canonical `dungeon_id`
- chapter
- narrative purpose
- entrance room

For each room binding:

- canonical `room_id`
- parent `dungeon_id`
- chapter and scene
- narrative function

### Required detail

Locations support defined narrative beats rather than dictating them. Each
room binding explains why the scene occurs there. Dungeon and room identities
remain independent from chapter and scene identities. A missing canonical
location is specified here but created only through the Dungeon or Room
Editor.

When a location need is vague, the GM presents three alternatives with
different traversal, conflict, and consequence profiles before routing any
creation work.

### Completion check

Completion requires a coherent location plan, traversal logic, scene-to-room
bindings, and canonical IDs. Every required scene has an intentional location
or an explicit location-independent decision.

## Stage 5: Items, Clues, Rewards, and Prerequisites

### Decisions

- Which items or clues are necessary to understand or complete the storyline?
- What is each item’s narrative and gameplay purpose?
- How is it acquired, missed, lost, consumed, or rewarded?
- Which scene, task, actor, or location introduces it?
- Which prerequisites does it require or satisfy?
- Can an existing canonical item fulfill the need?

### Objects produced

For each item binding:

- canonical `item_id`
- purpose and acquisition method
- chapter and scene
- quest/task and actor associations
- prerequisite IDs
- reward or consequence

### Required detail

Every item changes information, capability, access, relationship, or reward.
It is connected to an acquisition event and to the progression it affects.
Decorative objects need not become storyline items unless they carry a
storyline responsibility.

If a new item is needed but underspecified, the GM presents exactly three item
concepts before the author selects one for canonical creation.

### Completion check

Completion requires every necessary item, clue, reward, acquisition path,
prerequisite, and canonical ID. No critical progression edge may depend on an
uncreated or unplaced item.

## Stage 6: Composition and Validation

### Decisions

- Does the assembled object still match the current narrative contract?
- Are all records connected and all canonical references resolvable?
- Are choices, consequences, conflicts, relationships, chronology, and
  foreshadowing coherent?
- Which warnings are design choices, and which require revisions?

### Operations

1. assemble the exact canonical preview without publishing;
2. validate schema and data contracts;
3. validate canonical actor, dungeon, room, and item references;
4. validate chapter, scene, task, objective, and unlock linkage;
5. analyze chronology, conflict progression, relationships, foreshadowing,
   orphan records, and unreachable progression;
6. show findings with their affected fields and objects;
7. for each finding requiring a creative decision, present three remediation
   options and wait for the author;
8. rerun validation after revisions.

### Completion check

Malformed schema and data contracts are blocking errors. Narrative
completeness, progression, continuity, and unresolved canonical references are
warnings shown to the author. The generated definition and validation results
are tied to the current saved draft revision.

## Stage 7: Canonical Review and Creation

### Review package

The author receives:

- the complete canonical storyline object;
- a summary of the premise, progression, cast, locations, items, and ending;
- the object and relationship graph;
- validation and narrative-analysis results;
- unresolved warnings;
- the exact draft revision and payload hash;
- a summary of recent changes.

### Canonical creation

Canonical creation requires a saved draft revision and successful canonical
validation. The author performs the final create or publish action.

## Revision behavior

A change creates a new draft revision. Completion and validation are
recalculated from that revision without requiring approval invalidation.

Each draft has exactly one owner. Storyline editors may load and inspect drafts
owned by other users, but every mutation path remains owner-only. Saving changes
while viewing another user's draft first creates an owner-isolated derivative
with a new canonical storyline identity and a `source_draft_id` lineage link;
the source draft and its revision history are never changed.

Every saved revision is retained as an immutable payload snapshot. The draft
library shows the total number of drafts, separates owned and shared drafts,
identifies derivative sources, and exposes a revision selector for each draft.
Historical revisions are read-only; saving from one creates a new owned draft
whose `source_draft_id` and `source_revision` identify the exact source version.
Only the owner may list or load those historical snapshots. Other Storyline
editors can load and copy only the latest saved revision.

Owners may permanently delete their own draft and its private revision and
operation history. Deleting a draft does not delete a canonical storyline that
was previously published from it. The `/storylines` library reports canonical,
owned-draft, shared-latest-draft, in-progress, and owner-visible version counts.
It also lists owned drafts with open/delete actions and shared drafts with only
their latest saved revision available.

Selecting a canonical storyline from `/storylines` opens its most recently
edited related draft at the latest saved revision. When no related draft exists,
the builder starts from the canonical template instead.

The builder selector represents storyline families, not raw draft rows. It
shows one entry per canonical storyline and one entry for each genuinely
unbound draft. Opening a canonical template reuses the owner's existing active
draft; repeated page loads never create competing drafts. Saved changes remain
revisions of that draft rather than additional selector entries.

Retired duplicate draft IDs resolve to the retained active draft so bookmarks
and open browser tabs recover without exposing deleted records.

### Campaign attachment

Publishing a storyline does not silently insert it into campaigns. The
`/storylines` page owns canonical content discovery and authoring only.
Campaign Settings owns storyline selection, conflict planning, gateway
decisions, attachment, activation, and primary-storyline selection.
Campaign creation redirects a selected starting storyline into that Campaign
Settings flow.

1. reconcile `generated_outline` with canonical storyline, chapter, and scene
   asset references;
2. resolve exact published dungeon and room versions;
3. compare those versions with the selected campaign snapshot;
4. preserve the authored storyline hook room and select a separate campaign
   gateway room and exit;
5. classify exact reuse, blocking lineage conflicts, and required actor
   mappings;
6. produce a deterministic plan bound to the storyline contract hash and
   campaign state hash;
7. re-plan immediately before apply and reject stale plan IDs;
8. apply the unchanged plan atomically through the existing storyline,
   published-dungeon projection, canonical-room projection, actor, and
   relationship authorities;
9. create the cross-dungeon gateway through `ConnectorDefinitionService` and
   activate the storyline. Making it primary remains an explicit option.

Authored room references must resolve to exactly one declared dungeon.
Contradictory outline/reference assignments hard-fail. Every referenced
dungeon and room must have a current published canonical version. Missing room
geometry remains a visible blocker. Canonical room projection derives required
H3 indexes deterministically from the published dungeon and placement; it does
not invent rooms or connector topology.

The authored hook room is immutable storyline content. The campaign owner
selects the existing campaign gateway room from which that hook becomes
discoverable. A uniquely marked default gateway and its recommended
campaign-only boundary are selected automatically; otherwise Campaign Settings
requires an explicit room or exit choice. Actor ID collisions require an
explicit choice to map the storyline role to an existing campaign actor or
create a separate deterministic runtime copy. Room and item lineage conflicts
remain blocking until resolved through their canonical owner.

Published dungeon room placements define the canonical-room-to-runtime-room
mapping. Dungeon connector H3 endpoints are derived from transformed
dungeon-level axial coordinates, preserving room-template reuse while keeping
the projected navigation graph spatially authoritative.

### Runtime branch execution

Player decisions publish as choices on runtime questline nodes. Each choice
declares a typed eligibility trigger, a target task or scene (or a terminal
outcome), and deterministic actor, environment, and storyline state changes.
During gameplay a GM or runtime caller submits `decision_id` and `choice_id`
through storyline advancement. The Storyline Manager records the selection in
`variables.branch_choices`, applies the declared state mutations, advances the
runtime pointers, and logs the result.

Trigger types describe when a choice is eligible; they are not autonomous
polling jobs. The gameplay or GM caller remains responsible for recognizing the
trigger and submitting the selected branch.

The gameplay GM receives current pending decisions and their authored trigger
contracts in its prompt context. Its standard `inspect_storyline_branches` tool
returns the same canonical runtime view, and `resolve_storyline_branch` submits
the selected outcome through the Storyline Manager rather than mutating tables
directly. Resolved actor effects and actor-scoped state are projected into that
actor's narrative context so subsequent actor turns portray the consequence
without exposing pending or unrelated branch information.

Published templates are shared inputs. Loading a template authored by another
user creates a derivative identity before editing so publication cannot
overwrite the other author's canonical storyline.

Revision compare data and operation receipts retain the exact changes applied
by the GM. Revision conflicts hard-fail so an update can never silently
overwrite newer author work.

## Implementation Acceptance Criteria

The system implementation is complete only when:

- every stage remains freely navigable and editable;
- clear author requests are applied immediately through the draft authority;
- manual and GM changes use the same revision-safe payload;
- completion and validation recalculate after each saved revision;
- canonical creation checks canonical validation server-side;
- a vague object-creation request returns exactly three structured,
  selectable options;
- selecting an option applies the selected direction to the draft immediately;
- linked canonical objects are created only by their existing owner/editor;
- malformed schema/data contracts remain visible and block creation;
- completeness and continuity findings remain visible as non-blocking warnings;
- the final author action is required to create or publish the storyline.
