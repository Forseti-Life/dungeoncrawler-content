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
- branch decisions with one or more choices (one choice is a fixed outcome),
  each containing:
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

## Stage 3: Institutions

Institutions come before the cast: the clans, families, settlements,
factions, guards and guilds the story is about.

### Objects produced

Each row of `objects.institutions`:

- `domain` (state, place, kin, organization, faith) and `name`
- `institution_id`, derived by the save from the domain and name and never
  typed; renaming an institution changes its ID everywhere in the same save
- description, and purpose in this story
- `seat_id` (a room or site of this storyline) and `leader_actor_id` (a cast
  member who belongs to it)
- `parent_institution_id` and `stances` toward other institutions (attitude
  and reason)

A cast member is tied to an institution by an entry in its `affiliations`
(`institution_id` and a standing: member, leader, exile or ally). The builder
edits institutions in Step 3 and each actor's ties in Step 4. A new
institution can be chosen as a parent, in a stance or on a cast member once
the draft has been saved and its ID exists.

### Completion check

When the cast has two or more members:

- at least one institution is defined;
- every cast member new to the story is tied to at least one institution (a
  character that was in the library before the draft is exempt).

Always: every institution has at least one cast member tied to it.

The status card reports institutions, cast members tied, and stances. The
next step asks the GM to define the story's institutions, to tie a cast
member to each institution, or to tie the cast to the story's institutions.
A leader outside the cast or outside the institution, and a seat that is not
a place of this storyline, are blocking validation findings.

The saved draft's institutions, with the cast tied to each grouped by
standing and the cast tied to none, are listed read-only at
`/game-master/storyline-builder/institutions?draft_id=<draft id>`, linked
from the builder under "In this storyline".

## Stage 4: Actors and Relationships

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

### Library characters first

Before a character is added, the GM searches the character library with
`find_library_characters` (full characters and the placed town contacts,
usable in any storyline) and offers what fits. A library character is
bound by its `actor_id`; nothing is copied. This applies most of all to
the quest giver or hook.

- A character new to the story must be placed: `location_id` names a room
  or site of this storyline. Missing or unknown placement is a blocking
  finding (`actor_placement_missing`, `actor_placement_unknown`). A
  character that was in the library before the draft was started is exempt;
  it has a home already.
- When the story's quest giver (`entry_actor_id`) is new to the story,
  validation warns and says how many library characters give quests.
- The Storyline GM cannot create an actor definition directly; the sheet
  build is the only way.

### Character sheets

Every roster actor needs a complete character sheet. Sheets are built on
request ("build the character sheets for the roster"), by the single tool
`build_storyline_actor_sheets`. It creates a character for each actor,
derives class, ancestry and background from the authored role, and records
the link on the actor row. This is the only way a storyline actor is
created; remediation refuses an actor (`storyline_remediation_actor_not_supported`).

Validation reports one blocking finding per actor in the stage
`actor_character_sheets`: `actor_sheet_missing` when no sheet is linked,
`actor_sheet_outdated` when the actor was edited after its sheet was built.
Stage 4 is not complete, and the draft cannot be published, while any
remain. The next step names it.

An actor whose name matches a character already in the library under a
different ID is reported as `actor_duplicates_library_actor`, whether or
not its sheet is built, and the sheet build refuses it: the storyline must use the existing actor's
ID, or the actor must be renamed. Character names may contain letters,
spaces, hyphens and apostrophes.

Changing a roster actor's `actor_id` or `contact_id` is an identity change,
and the save applies it everywhere: every scene, choice, objective,
conflict and relationship that named the old ID names the new one in the
same revision. Linking an actor to an existing library character is
therefore one patch to the roster row.

### Completion check

Cast coverage, motivations, knowledge boundaries, relationship arcs, and
canonical actor selections contribute completion and continuity warnings; they
do not gate publication.
Character sheets are the exception: an actor without a
current sheet is a blocking finding.

### Future Enhancement Seam: Party-Relative Actor Level & Retinue Scaling

Party-relative actor level scaling (for example, scaling a named boss/leader to
`highest_party_level` or `average_party_level`) and dynamic retinue/minion
count scaling (`X` companion actors based on PF2E encounter XP budget remainder
or party HD difference) are pinned as a future storyline mechanic enhancement.
See [`docs/dungeoncrawler/STORYLINE_ACTOR_LEVEL_AND_RETINUE_SCALING_DESIGN.md`](docs/dungeoncrawler/STORYLINE_ACTOR_LEVEL_AND_RETINUE_SCALING_DESIGN.md)
for the schema contracts (`level_scaling`, `retinue_scaling`), deterministic
PF2E resolution math, and codebase integration seams.

## Stage 5: Dungeons, Rooms, and Traversal

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

## Stage 6: Items, Clues, Rewards, and Prerequisites

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

## Stage 7: Composition and Validation

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

## Stage 8: Canonical Review and Creation

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

Trigger types describe when a choice is eligible. A choice without a
story-state condition is recognised and submitted by the gameplay or GM
caller. A choice whose trigger carries `when` is resolved by the engine
itself (see "Story-state conditions" below).

### Optional paths

There are three ways a story can go more than one way. Pick by what
differs:

- **Different ways to reach the same goal:** approaches of one task. The
  task has a `goal_text`; each choice carries an `approach` naming the
  objectives it uses. Finishing any one finishes the task.
- **Different directions for the story:** choices that lead to different
  tasks. Routes may rejoin.
- **Something that can happen at any time:** one storyline-wide choice (no
  source task), authored once.

There is no optional objective. Every objective row is a required step of
its task.

### What opens next

One rule decides it, `StorylineManagerService::questNodeBlocked`, used by
the quest log, by scene progression and by what is offered:

- A task without choices leads to the next task in the list, unless it
  names its own `unlocks_to`. List order is story order; the first task is
  where the story starts.
- A task that is the target of a choice opens when that choice is the one
  resolved and its source task succeeded. A storyline-wide choice opens
  its target at once.
- A task several routes lead to opens when any one of them succeeds. A
  task may ask for all of them instead (`prerequisite_mode: all`).
- A task reached both by order and by a choice opens either way.
- Only success opens what follows. A failed quest has status `failed`, a
  quest the story moved past has status `superseded`; neither opens
  anything. A story that must continue after a failure needs a choice that
  says where it goes.

The storyline stands in the scene of the quest that is open. Only quests
the rule has opened are offered. The storyline is complete when nothing is
open and a task that leads nowhere further has succeeded.

The quest outline in the builder states, for each quest, what it comes
after, what it leads to and why (its choices, a link set on the quest, or
the next quest in the list), and which storyline-wide choices can open it.

Validation warns (`task_failure_strands_story`) about an escort task that
has something after it and no choice saying where the story goes if it
fails.

### Story-state conditions

A choice's trigger may carry `when`, a state the engine can measure:

- `{subject_type: institution, subject_id, measure: members_lost,
  at_least}`: that share of the institution's members are dead or have
  fled the room an encounter was fought in.
- `{subject_type: actor, subject_id, measure: dead | fled | lost}`.
- `{subject_type: task, subject_id, measure: completed | failed |
  superseded}`.

The engine evaluates these when an encounter ends and when a quest changes
state, and resolves the choice once. Encounter end records each
non-player actor's fate on the campaign actor (`state_data.fate`).

A resolved choice acts on quests through its storyline effects: `lock` or
`resolve` on a task closes that quest as superseded; `redirect` on the
storyline closes every open quest except the target. Superseded pays
nothing and costs no standing. The target scene's quests are then
offered.

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

## GM Agent Request Flow

This is how the Game Master Agent handles one author message in the
Storyline Builder. The stages above say what a storyline needs; this section
says how a request moves through the agent.

### One turn

1. **Ground.** The harness assembles a snapshot of the saved draft: content,
   completion state, validation results by stage, any saved work, and the
   tools available.
2. **Route.** The request is classified as one of four kinds. A few phrases
   are routed without a model call: status questions, reviews of current
   findings, resume / stop / skip of saved work, and "Review and create".
   Everything else is classified by the model.
   - *Clarification*: intent is unclear, so the agent asks one question.
   - *Direct answer*: status, validation review, or discussion. Nothing is
     saved.
   - *Tool call*: one read or one edit.
   - *Task queue*: several ordered tool calls, run one after another within
     a time budget.
3. **Act.** Edits take one of three paths (see below).
4. **Validate.** Validation re-runs against the saved revision.
5. **Reply.** The agent explains what the evidence means and offers exactly
   one next step.

### Three ways an edit is made

- **Small edit.** The agent plans native draft operations and applies them
  in the same turn. The save is revision-checked and replay-safe.
- **Large edit** (many scenes, branching, endings). The agent first writes a
  work plan of short units with no operation payloads. Each unit is then one
  model call and one saved batch of at most ten operations. The editor
  continues the units automatically.
- **Missing canonical content** (items, creatures, obstacles, hazards and
  traps that the draft references but the library lacks). The agent plans a remediation
  and runs it only after the author explicitly approves. Quest records are
  not part of this: they are written when the storyline is published.
- **Character sheets.** Roster actors get their sheets from the sheet
  build (Stage 4), on request. It is separate from remediation.

Every draft edit goes through the same save path,
`StorylineAuthoringDraftService::applyDraftOperations()`.

### What completes an objective in play

- collect: the items are obtained. kill: the target is defeated.
  investigate: the lead is found. explore: the party reaches the location.
  escort: the party arrives at the destination.
- interact: the players talk with or use the target. It does not judge how
  the exchange turned out.
- interact with `requires_check: request`: a player's Request against the
  target succeeds. Request is the Pathfinder Diplomacy action; its DC rises
  or falls with the target's attitude. Talking alone does not complete it.
  Use it when a task is done only once an actor is persuaded, for example
  to join the party. The target must be an actor of the storyline.
- Actors who travel with the party are added by the players in the camp and
  marching-order setup. Any actor in the party's room who is not hostile can
  be added. A storyline does not add them.

### When a work unit fails

- A rejected unit saves nothing. The work pauses and records why.
- The author may resume (the model retries with the rejection in view),
  skip the unit, or stop the work. Completed units stay saved in all cases.
- After three failed attempts on one unit, only skip and stop are offered.
- If the draft was edited elsewhere while work was saved, the work must be
  stopped before new work is planned.
- After each saved unit the agent reports how many validation errors it
  cleared, how many it introduced, and how many remain.

### How the next step is chosen

On status questions, validation reviews and after every saved edit, the
next step comes from saved state and is not written by the model. In order:

1. unfinished saved work: resume it;
2. missing minimum content: the next missing story element;
3. validation errors: one named finding with its options;
4. validation warnings: one named warning with its options;
5. otherwise: review the storyline.

On discussion answers and read-only lookups the model proposes the step.

Each validation stage declares how its findings are fixed. A finding may
carry its own route, which overrides its stage:

- `draft_operation`: fixable by editing the draft. Offered first.
- `dungeon_editor`: needs a room or site built. The Storyline GM does not
  build them: it sends the author to the Dungeon Editor on that site, whose
  GM builds it from the story brief (`build_site_from_story_brief`).
  Offered when no draft-fixable error remains.
- `actor_sheet_build`: an actor needs its character sheet built.
- `unclassified`: reported as warnings.

Work units are planned only for `draft_operation` findings.

### Tasks, objectives, goals and choices

- Every task is published as its own quest record. Authors work with tasks.
  The record is written when the storyline is published, from the task's
  own rows; nothing in it is generated in their place.
- Every task needs at least one objective. An objective names its task, a
  type (interact, escort, explore, investigate, kill, collect), the target
  that type needs, and what the players do next. A task's objectives are
  required steps, completed in row order.
- An aim that can be achieved in more than one way is one task with a goal
  and one choice per way, each marked as an approach. Objectives on
  different tasks that track the same result are rejected, and an approach
  may only name objectives of its own task.
- A decision has one or more choices. One choice is a fixed outcome with
  its trigger and effects; a second is never invented to pad it.
- A task with no choices continues to the next task. A task with choices
  continues only where its choices lead.
- A condition that can redirect or end the story from any task is one
  storyline-wide choice: a choice with no source task. It can fire from any
  task, does not replace any task's continuation, and is authored once. The
  same choice repeated on several tasks is rejected.

### Model output contract

Every model reply is one required function call. The function it calls is
the reply's shape, so a shape cannot be misnamed:

- Classifying a request: `tool_call`, `task_queue`, `direct_response`, or
  `clarification`. A saved work unit may only call `tool_call`.
- Writing the answer: `answer`.

Each function's schema is sent to the provider and checked again when the
reply arrives. A reply outside its schema is rejected and logged with the
function and the raw reply. It is never mapped to a nearby shape,
completed, or truncated. The provider enforces the function name and the
listed values; it does not prevent an undeclared extra field or a missing
required field, which are caught on receipt.

Each registered tool is its own function, taking the tool's declared
arguments beside the reasoning. There is no generic tool call with an
untyped arguments object. A queued step names its tool and carries that
tool's typed arguments.

An intent carries a one-sentence reasoning and never a next step. The next
step is stated after the work has run. Work-plan units that break their
limits fail with the unit number and the measured value.

`tests/editor_gm_intent_contract_check.php` replays a fixed set of author
messages against a draft, read-only, and reports accepted and rejected
replies. It takes a surface (storyline, dungeon or room) and a draft ID.

### Rules the agent holds to

- It never asks the author to supply or choose internal IDs.
- It never splits technical work onto the author.
- It never re-proposes work that is already saved.
- It asks for one creative decision at a time.

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

## Tasks that share an ID with a published quest

A task is published as the quest with the same ID, written from the
objective rows in the draft. When a task's ID already exists as a
published quest:

- `load_existing_quests` shows the GM that quest (name, giver, starting
  place, owning storyline, objectives) so earlier goals can be read and
  rewritten as objective rows. Text in `{braces}` there is an unfilled
  placeholder.
- Objective playability and entity linkage judge the draft's own rows, not
  the published quest. A task without rows is reported once, as missing
  objectives.
- "Linked quest ... has canonical storyline_template_id ..." means
  publishing would take that quest over from another storyline.

## Several requests in one message

The model may answer with several tool calls. They run in order as one
queue, the same as `task_queue`. A tool call mixed with an answer or a
question is refused (`editor_gm_intent_parallel_call_not_a_tool`).

### Institutions: identity, reuse and play

- There are five institution types: state, place, kin, organization and
  faith. Finer grain is nesting: an institution names its parent, to any
  depth. A state sits only inside a state; a place inside a place or
  state; kin inside kin, a place or a state; an organization or faith
  inside anything. Institutions with the same parent are peers.
- A cast member is tied to its innermost institution; it is a member of
  every institution above by inheritance.
- Member and leader ties are stored on the cast member's library
  character, so the character brings them to every storyline and
  campaign. A storyline shows them and may add its own.
- Validation warns when a cast member lacks a critical tie: ancestry,
  class, alignment, or a home (kin or place).
- An institution's ID is the institution engine's subject ID for its
  domain and name (`institution_kin_blackroot-clan`). The same domain
  and name is the same institution in every storyline and campaign, so
  give story-specific groups story-specific names.
- Every institution a draft defines is registered in the canonical
  institution library (`InstitutionLibraryService`) when the draft is saved.
  `find_existing_institutions` searches that library. To reuse one, define
  it with the same domain and name. One name is one institution: the same
  name under a second domain is refused.
- Each cast member has an `alignment` (LG to CE). It is carried onto the
  character sheet and makes the actor a member of the alignment
  institutions (Lawful or Chaotic, Good or Evil, Neutral), which are never
  added to a storyline. A cast member without one counts as Neutral and is
  listed in a warning.
- When a storyline is attached to a campaign,
  `StorylineInstitutionRealizationService` creates the institutions that
  have a tied cast member, their hierarchy, members' memberships, stances
  as institution matrix edges, and exile and ally ties as sentiment, all
  through the existing institution authorities. See
  `INSTITUTIONAL_DISPOSITION_TARGET_ARCHITECTURE_AND_IMPLEMENTATION_PLAN.md`.
- A character sheet is built from the actor's name, role, motivation,
  attitude, knowledge, class, ancestry and level. Placing an actor or
  tying it to an institution does not make its sheet out of date.

## GM agents and their scopes

Each editor page has its own GM agent, working on one draft at a time.

- Storyline Builder owns the story: tasks, scenes, cast, institutions,
  relationships, and publishing the storyline. It never changes a site or
  a room, and it does not delegate to another agent.
- Dungeon Editor owns a site: its rooms, how they connect, and publishing
  it. Room Editor owns one room. Each reads what the story needs from the
  story brief.
- Definition Editor and Character Creation own object definitions and
  character sheets. Some overlap is accepted: the Storyline, Room and
  Dungeon agents may also create or update object definitions, and the
  Storyline agent builds its cast's character sheets.
- Handoff is always to the author. When the story needs a site or room
  built or changed, the Storyline agent answers with a shortcut into that
  editor. The same shortcuts are listed beside the draft under "In this
  storyline": each site with its rooms beneath it.

## Page format

The builder page is laid out by templates/editor-page-shell.html.twig, the
one template the Storyline Builder, the Dungeon Editor and the Room Editor
all embed: header, left column (stages, current state, quick links) and
workspace. This page fills the template's blocks with its own stage
buttons, state card, "In this storyline" links and wizard.

The read-only pages about a draft (scenes in order, actors and
relationships, institutions, and the page of one planned actor) use the
same page through Render/StorylineReviewPage and
templates/storyline-review-page.html.twig: the four storyline pages on the
left, the draft's counts as its current state, the content on the right.
A library actor's sheet opened outside a campaign sits in the Definition
Editor page with the agent scoped to that actor; in a campaign the sheet
is shown plain, as players see it.
