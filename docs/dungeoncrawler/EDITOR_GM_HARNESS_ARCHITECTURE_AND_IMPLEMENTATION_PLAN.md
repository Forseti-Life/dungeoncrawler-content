# Editor GM Harness: Architecture and Implementation Plan

**Status:** Proposed  
**Scope:** Dungeoncrawler editor-tool architecture  
**Primary target:** Room Editor  
**Expansion targets:** Canonical object editor, future content authoring tools  
**Compatibility policy:** **No backward compatibility layer** for editor embedding

## Executive decision

We should build a **separate editor-scoped GM harness** instead of extending
player room-chat orchestration directly into authoring tools.

The existing GM stack is optimized for:

1. live campaign runtime state;
2. actor-scoped turn handling;
3. room-chat narration + deterministic action routing;
4. campaign authority mutation.

The Room Editor is optimized for:

1. canonical draft authority;
2. revision-checked command application;
3. publication validation;
4. non-campaign authoring workflows.

Those are different authority models. Reusing names and patterns is good;
sharing runtime mutation paths is not. The right move is to create an
**editor-native harness** that borrows the GM framework's good architecture
(route envelopes, tool registries, deterministic-first execution, strict
contracts), while keeping editor authority separate from campaign authority.

## Non-negotiable decisions

### 1. No backward compatibility

The editor harness should **not** depend on:

- `LegacyRoomChatCompatibilityAdapter`
- room-chat response overlays
- campaign runtime snapshots as the primary payload
- any compatibility response format tailored to player chat

This should be a clean editor contract from day one.

### 2. No direct table mutation from the harness

The editor harness should never write canonical rows directly.

All mutations must flow through native editor services:

- `RoomEditorService::applyCommand()`
- `RoomEditorService::validateDraft()`
- `RoomEditorService::publish()`

That keeps the existing draft/revision/publication model authoritative.

### 3. Toolset must be explicit

The harness should expose a **named tool registry** with clear tool families,
inputs, outputs, and authority boundaries. Natural-language assistance can sit
on top later, but the harness itself must speak in explicit tool contracts.

## Problem statement

Today the GM framework is strong at runtime orchestration, but editor tools are
still isolated CRUD-style systems. The missing layer is a **shared authoring
harness** that can:

1. load deterministic editor context;
2. expose an explicit tool vocabulary;
3. route editor intents to deterministic helpers first;
4. optionally hand off to GM/LLM assistance only after context grounding;
5. return editor-scoped results that can be converted into native editor
   commands for approval/execution.

Without that harness, every editor will build its own ad hoc AI surface,
duplicating context assembly, validation explanation, diffing, planning, and
execution semantics.

## Target architecture

### Top-level model

```text
Editor UI
  -> Editor GM Controller
    -> Editor GM Harness
      -> Context Assembler
      -> Tool Registry
      -> Intent Router
      -> Deterministic Tool Executor
      -> Assist Planner / Backstop Generator
      -> Editor Command Plan Projector
      -> Native Editor Service
```

### Separation of concerns

| Layer | Responsibility | Must not do |
|---|---|---|
| Editor controller | HTTP/JSON transport, auth, CSRF, request decoding | Business logic |
| Harness | Envelope, routing, orchestration, response shape | Raw DB mutation |
| Context assembler | Build editor-scoped grounded context | Interpret freeform intent |
| Tool registry | Declare tools and contracts | Execute routing policy |
| Intent router | Choose deterministic vs assist path | Perform writes |
| Deterministic executor | Run pure, grounded tool operations | Generate speculative content |
| Assist planner | Convert grounded context into suggestions/plans | Bypass validation |
| Command projector | Convert approved assist output into editor commands | Publish directly |
| Native editor service | Draft mutation, validation, publish | GM orchestration |

## Recommended code structure

Create a new editor-scoped subtree instead of spreading this across room-chat
services:

```text
src/Service/EditorGm/
  EditorGmHarnessService.php
  EditorGmToolRegistry.php
  EditorGmIntentRouter.php
  EditorGmResponseProjector.php
  EditorGmExecutionPipeline.php
  EditorGmCommandPlanProjector.php
  EditorGmToolContextAssemblerInterface.php
  EditorGmToolDefinition.php
  RoomEditorGmContextAssembler.php
  Tool/
    LoadDraftSnapshotTool.php
    ValidateDraftTool.php
    DiffDraftAgainstPublishedTool.php
    InspectCatalogEntryTool.php
    ExplainValidationTool.php
    PlanRoomCommandsTool.php
    PreviewPublicationTool.php

src/Controller/
  EditorGmController.php

config/schemas/
  editor_gm_request.schema.json
  editor_gm_response.schema.json
  editor_gm_tool_definition.schema.json
  editor_gm_command_plan.schema.json
```

The current one-off `EditorToolGmHarnessService.php` should be treated only as
temporary scaffolding. The durable architecture should move to the
`EditorGm/` namespace above so the structure stays readable as more tools are
added.

## Fit into the current architecture

This plan fits cleanly into the current stack because the codebase already has
the two subsystem shapes we need:

1. a **runtime GM subsystem boundary** for campaign/room-chat orchestration;
2. a **canonical editor authority boundary** for draft/validate/publish flows.

The editor GM harness should sit **between** those two worlds as an
editor-scoped sibling subsystem, not as an extension of the runtime GM path.

### Existing runtime GM seam

The current runtime GM architecture already establishes the pattern:

```text
transport/controller
  -> GameMasterSubsystemService
    -> GmActorHarnessService
      -> GmToolExecutionService / GM runtime services
```

That path is correct for:

- campaign runtime bootstrap;
- actor-scoped turn context;
- room-chat routing;
- `dc_campaign_*` authority mutation;
- legacy chat compatibility overlays where needed.

It is **not** the right insertion point for editor embedding because its tool
surface and authority model are campaign-runtime-first.

### Existing Room Editor seam

The Room Editor already has the editor-side pattern we need:

```text
RoomEditorController
  -> RoomEditorService
    -> draft tables / validation / publication / catalog authority
```

That path is already authoritative for:

- draft creation;
- revision-checked commands;
- deterministic validation;
- immutable version publication;
- Room Editor shell bootstrapping through `drupalSettings`.

That means the new harness should plug in here:

```text
RoomEditorShell
  -> EditorGmController
    -> EditorGmHarnessService
      -> RoomEditorGmContextAssembler
      -> EditorGmToolRegistry
      -> RoomEditorService
```

### Concrete integration points

| Current component | Role today | Editor harness fit |
|---|---|---|
| `src/Controller/RoomEditorController.php` | Room Editor page + JSON transport | Add GM endpoint URL into `drupalSettings`; keep existing draft/command/validate/publish endpoints authoritative |
| `js/room-editor.js` | Boots the editor shell from Drupal behavior | No architectural change; continue using as the single module entrypoint |
| `js/v2/editor/RoomEditorShell.js` | Owns editor UI state and server calls | Add GM Assist panel, manifest fetch, tool execution requests, and command-plan preview/apply UX |
| `src/Service/RoomEditorService.php` | Canonical draft/validate/publish authority | Remains the only mutation/publish authority behind execution tools |
| `config/schemas/contract_registry.json` | Canonical contract registry | Register editor GM request/response/tool/plan contracts here |
| `tests/src/Unit/Schema/RoomEditorContractTest.php` | Enforces route/contract/editor-boundary rules | Extend with editor GM schema/route/authority assertions |
| `src/Service/GameMasterSubsystemService.php` | Player room-chat subsystem facade | Reuse the subsystem pattern only; do not route editor requests through it |
| `src/Service/GmActorHarnessService.php` | Runtime harness and tool listing | Source of naming/pattern inspiration, not the execution owner for editor tools |
| `src/Service/GmToolExecutionService.php` | Campaign GM privileged tool execution | Explicit non-fit for editor execution because it owns `dc_campaign_*` mutation authority |

### Service registration fit

The new harness should be registered in `dungeoncrawler_content.services.yml`
as a **new sibling service family** next to:

- `dungeoncrawler_content.game_master_subsystem`
- `dungeoncrawler_content.gm_actor_harness`
- `dungeoncrawler_content.room_editor`

Recommended service IDs:

- `dungeoncrawler_content.editor_gm_tool_registry`
- `dungeoncrawler_content.room_editor_gm_context_assembler`
- `dungeoncrawler_content.editor_gm_harness`

This keeps the dependency direction clean:

1. `EditorGmHarnessService` may depend on `RoomEditorService`;
2. `RoomEditorService` must **not** depend on editor GM services;
3. runtime GM services must **not** become a hidden dependency of editor
   mutation flows.

### Routing fit

The current Room Editor routes already group cleanly under
`/api/room-editor/drafts/{draft_id}/...`.

The harness should extend that family with:

- `GET /api/room-editor/drafts/{draft_id}/gm`
- `POST /api/room-editor/drafts/{draft_id}/gm`

That keeps the GM embedding transport visibly attached to the editor draft
resource rather than inventing a separate campaign-like route family.

### Front-end fit

The Room Editor shell already consumes a URL bundle from
`drupalSettings.dungeoncrawlerContent.roomEditor.urls`. The harness should fit
there by adding:

- `gm` for the GET/POST harness endpoint
- optional static `gmToolManifest` only if we intentionally want bootstrap
  hydration in the page payload

No separate front-end bootstrap system is needed.

### Important transport rule

We want **tool parity**, not uncontrolled API sprawl.

That means:

1. the assistant must be able to do everything the editor can do;
2. it does **not** mean every capability needs its own new public JSON route;
3. the preferred implementation is:
   `RoomEditorShell -> /gm endpoint -> EditorGmHarnessService -> RoomEditorService`.

Only add new public editor routes when there is a clear non-harness client need.
For canonical definition editing in particular, the assistant can use harness
tools backed directly by `RoomEditorService::loadCanonicalEntry()` and
`RoomEditorService::saveCanonicalEntry()` without first creating a separate
browser-facing CRUD API.

### Contract/test fit

This module already treats schemas and route boundaries as first-class
architecture. The editor harness should follow that same pattern:

1. add schemas under `config/schemas/`;
2. register them in `contract_registry.json`;
3. add/extend unit tests that prove strict top-level fields and route
   permissions;
4. assert that editor GM services do not mutate `dc_campaign_*` tables.

### Scaffold disposition

`src/Service/EditorToolGmHarnessService.php` is in the right *problem space*
but the wrong final *shape*.

Keep it only as temporary evidence of the contract direction; do not build the
final implementation around that flat top-level service. Phase 1 should either:

1. replace it with the `src/Service/EditorGm/` subtree; or
2. move its useful payload ideas into `EditorGmHarnessService` and delete the
   original scaffold.

## Harness contract

Each request should be editor-scoped, not campaign-scoped.

### Request envelope

```json
{
  "schema_version": "editor-gm-request-v1",
  "tool_context": {
    "tool_id": "room_editor",
    "draft_id": "uuid",
    "validation_profile": "editing"
  },
  "intent": {
    "type": "tool_call",
    "tool_name": "validate_draft",
    "arguments": {}
  },
  "options": {
    "dry_run": true
  }
}
```

### Response envelope

```json
{
  "schema_version": "editor-gm-response-v1",
  "tool_id": "room_editor",
  "route_family": "deterministic_editor_tool",
  "context_snapshot": {},
  "tool_result": {},
  "command_plan": [],
  "validation": {},
  "messages": [],
  "errors": []
}
```

## Toolset structure

The toolset should be declared in the harness as a registry, grouped into four
families.

### 1. Context tools

These return grounded state with no planning or mutation.

| Tool | Purpose | Authority |
|---|---|---|
| `load_draft_snapshot` | Load active editor draft + metadata | Draft tables only |
| `load_published_snapshot` | Load currently published canonical version | Canonical version store |
| `inspect_catalog_entry` | Inspect one placeable/canonical object definition | Registry/object authority |
| `summarize_room_topology` | Report hex/ports/placements/tags summary | Draft payload only |

### 2. Validation tools

These explain and repackage deterministic checks.

| Tool | Purpose | Authority |
|---|---|---|
| `validate_draft` | Run editor validation profile | `RoomEditorService::validateDraft()` |
| `explain_validation_findings` | Group and humanize findings | Validation result only |
| `check_publication_readiness` | Summarize blockers for publish | Draft + publication validation |
| `diff_draft_vs_published` | Surface meaningful changes | Draft + published version |

### 3. Planning tools

These convert editor goals into proposed native commands.

| Tool | Purpose | Authority |
|---|---|---|
| `plan_room_commands` | Convert a requested edit into Room Editor commands | No mutation |
| `plan_port_rewiring` | Build command set for entry/exit changes | No mutation |
| `plan_layout_fill` | Suggest hex additions/removals/terrain changes | No mutation |
| `plan_object_placement` | Suggest placement commands from selected definitions | No mutation |

### 4. Execution tools

These call native editor services and return receipts.

| Tool | Purpose | Authority |
|---|---|---|
| `apply_room_commands` | Apply approved command list | Draft command bus |
| `preview_publication_payload` | Show canonical projection before publish | Draft + projection only |
| `publish_room_version` | Publish immutable version | Publication authority |

## What the harness must not expose

The editor harness should not expose campaign GM tools such as:

- `modify_dungeon_state`
- `modify_room_state`
- `modify_actor_state`
- `modify_inventory`
- `modify_quest_state`
- `modify_campaign_*`

Those belong to runtime campaign authority and would blur the editor boundary.

## Room Editor embedding design

### Phase-1 Room Editor embedding

Room Editor should be the first harness client because it already has:

1. a revisioned draft model;
2. a deterministic command bus;
3. strict validation profiles;
4. publish semantics;
5. admin-only access control.

### Editor-specific context assembler inputs

`RoomEditorGmContextAssembler` should assemble:

- current draft payload;
- current draft revision/status;
- selected room ID;
- validation results for requested profile;
- published version metadata;
- diff summary between draft and published;
- catalog context for selected placement/definition, when present;
- authority boundary metadata;
- supported editor tool definitions.

### UI embedding points

The Room Editor shell should gain a dedicated **GM Assist** panel with:

1. context summary;
2. tool results;
3. command-plan preview;
4. apply-plan button;
5. publication readiness summary.

It should not masquerade as player chat. It is an editor workbench surface.

## Editor-page embedding plan

### Current page structure

Today the page is laid out as:

```text
header
  workspace
    left rail: catalog
    center: toolbar + canvas
    right rail: inspector
  footer: validation
```

That is effective for manual authoring, but it makes the assistant secondary.

### Target page structure

The GM assistant should become the **primary secondary workspace** on the Room
Editor page.

```text
header
  workspace
    collapsible author-tools drawer
    canvas workspace
    persistent GM Assist panel
```

### Layout decision

1. Keep the canvas as the primary center surface.
2. Replace the permanently exposed manual tool rails with a **collapsible
   Author Tools drawer**.
3. Use the reclaimed rail space for a **persistent GM Assist panel** that is
   always visible on desktop.
4. Keep manual editing available, but **collapsed by default** so the editor
   opens into assistant-first mode.

### What moves into the collapsible Author Tools drawer

The drawer should consolidate all manual authoring controls now spread across
the page:

1. **Catalog** — family filter, search, placement source list
2. **Editing tools** — select, add hex, remove hex, terrain, elevation, place,
   entry port, exit port
3. **Inspector** — room/hex/placement/port detail editors
4. **Validation** — deterministic findings list and publish blockers

This keeps every existing manual affordance available while removing the
always-open left/right rails that currently dominate the page width.

### GM Assist panel contents

The persistent assistant panel should contain:

1. conversation thread;
2. grounded context summary for the active draft;
3. explicit tool-call/result trace;
4. draft command-plan preview;
5. object-definition preview/editor results;
6. approval/apply controls;
7. publication readiness summary.

This makes the assistant a real authoring copilot rather than a detached chat
box.

### Reuse, not duplicate, current editor mechanics

The current shell already has stable mechanics for:

- draft load/create;
- command dispatch;
- undo/redo;
- catalog loading;
- inspector rendering;
- validation rendering;
- publish flow.

The implementation should **reuse those mechanics** instead of introducing
parallel ones for the assistant.

Concretely:

1. GM plan execution should flow into the same command authority path as manual
   editing.
2. Manual edits and assistant-applied edits must both end by updating the same
   draft state in the shell.
3. Validation and inspector data should be rendered from shared draft state,
   not duplicated assistant-only shadow state.

### Front-end implementation fit

This should be implemented by evolving the existing Room Editor shell, not by
introducing a second front-end app.

#### Template changes

`templates/room-editor.html.twig` should be refactored so the workspace has:

- a drawer toggle in the header or workspace chrome;
- one collapsible `room-editor__author-drawer`;
- one persistent `room-editor__gm-panel`;
- the existing canvas stage in the center.

Inside the author drawer, keep the current page capabilities but regroup them
into labeled sections:

1. Catalog
2. Manual tools
3. Inspector
4. Validation

That makes the UI smaller without deleting capability.

#### CSS changes

`css/room-editor.css` should evolve from a 3-column rail layout to:

- `drawer | canvas | gm panel` on desktop;
- `canvas -> gm panel -> drawer` stacking on smaller widths;
- collapsed drawer state by default;
- wider right-side panel sizing, roughly `420px-560px`, for usable tool/chat
  transcripts.

#### Shell changes

`js/v2/editor/RoomEditorShell.js` should become the orchestration owner for:

- drawer open/close state;
- GM snapshot fetch on draft load;
- GM tool manifest rendering;
- chat/tool execution requests;
- command-plan approval/apply flow;
- surfacing validation and canonical object results inline in the assistant.

### Explicit RoomEditorShell hook points

To keep implementation tight, Codex should treat these existing shell seams as
the integration hooks instead of inventing new ones:

| Existing shell seam | New responsibility |
|---|---|
| `loadRoom()` / `createNewRoom()` | Fetch initial GM harness snapshot after draft resolution |
| `_setDraft()` | Refresh grounded GM context whenever the active draft changes |
| `_sendCommand()` | Remains the single client-side command application path for manual and assistant-approved room commands |
| `undo()` / `redo()` | Remain the single history path; assistant wrappers call through here or the same underlying command dispatch |
| `_loadCatalog()` / `_fetchCatalogEntry()` | Continue to back manual catalog UX; GM harness gets equivalent data through server-side tools |
| `_renderInspector()` | Renders inside the collapsed Author Tools drawer instead of a persistent right rail |
| `_renderValidation()` | Renders inside the collapsed Author Tools drawer and also feeds assistant summaries |

### State model on the page

There should be **one active draft state** in the shell, not separate human and
assistant copies.

Recommended top-level client state buckets:

1. `draft` — existing authoritative client copy of the active draft
2. `assistantSession` — chat transcript, route/tool trace, pending state
3. `assistantContext` — latest grounded snapshot returned by `/gm`
4. `assistantPlan` — currently proposed command plan or definition patch
5. `authorDrawer` — open/closed state plus active section

The assistant may cache summaries, but it must never become a second draft
store.

## Tool parity requirement

The GM assistant must have access to **every meaningful authoring capability**
already available to the human editor on this page.

That means the harness cannot stop at validation summaries. It needs explicit
tools that cover the current manual API + service surface.

### Current editor capability surface

| Human editor capability | Current backing surface | GM harness requirement |
|---|---|---|
| Create/load draft | `RoomEditorController::createDraft()` / `getDraft()` | `load_draft_snapshot` / draft bootstrap context |
| Apply room commands | `RoomEditorController::command()` -> `RoomEditorService::applyCommand()` | `apply_room_commands` plus planning tools that emit the same command types |
| Undo/redo | `applyCommand()` with `undo` / `redo` | `undo_room_command` / `redo_room_command` tools or equivalent command wrappers |
| Validate draft | `validateDraft()` | `validate_draft` + `explain_validation_findings` |
| Publish room | `publish()` | `preview_publication_payload` + `publish_room_version` |
| Browse catalog | `catalog()` | `list_catalog_definitions` |
| Inspect definition | `catalogEntry()` | `inspect_catalog_entry` |
| Edit canonical object definition | `loadCanonicalEntry()` / `saveCanonicalEntry()` via `CanonicalObjectEditForm` | `load_canonical_definition` + `update_canonical_definition` |

### Important architecture gap

The current Room Editor page exposes canonical definition editing only through a
link-out form (`CanonicalObjectEditForm`), not a JSON editor API. That is fine
for humans today, but it is a gap for an in-page GM assistant.

So Phase 1/2 must treat canonical object editing as a first-class harness
capability, backed by `RoomEditorService::loadCanonicalEntry()` and
`RoomEditorService::saveCanonicalEntry()`, even if we later decide to also add
standalone JSON endpoints for those operations.

This is the correct order of operations:

1. add **harness tools** for canonical definition load/edit first;
2. keep the existing human link-out form working;
3. add standalone JSON endpoints only if a later non-harness UI needs them.

### Required tool families, expanded

In addition to room-draft tools, the harness must include a **canonical object
definition tool family**:

| Tool | Purpose | Backing authority |
|---|---|---|
| `load_canonical_definition` | Load the raw editable definition payload | `RoomEditorService::loadCanonicalEntry()` |
| `update_canonical_definition` | Persist approved canonical definition edits | `RoomEditorService::saveCanonicalEntry()` |
| `plan_canonical_definition_patch` | Produce a proposed JSON patch/diff before save | Harness planning layer |
| `summarize_definition_usage` | Explain where a definition is used in the current room draft | Draft + placement scan |

That is how we guarantee the assistant can work with the same object-level
editing power the human currently reaches through the inspector link-out.

### Room command parity

The planner/executor must support every command type the room editor already
accepts today, including:

- `set_room_metadata`
- `add_hex`
- `remove_hex`
- `set_hex_terrain`
- `set_hex_elevation`
- `place_object`
- `move_object`
- `rotate_object`
- `remove_object`
- `duplicate_object`
- `add_entry_port`
- `add_exit_port`
- `update_entry_port`
- `update_exit_port`
- `remove_entry_port`
- `remove_exit_port`
- `undo`
- `redo`

If a human can do it from the Room Editor contract, the assistant must be able
to plan it, explain it, and execute it through the same authority path.

## Implementation plan

## Phase 0 — contract freeze

1. Define `editor_gm_request`, `editor_gm_response`,
   `editor_gm_tool_definition`, and `editor_gm_command_plan` schemas.
2. Register those contracts in `contract_registry.json`.
3. Freeze the initial Room Editor embedding vocabulary.
4. Explicitly document that editor GM harness is **not** a room-chat transport.

**Exit criteria:** request/response/tool schemas exist and unit tests enforce
strict top-level fields.

## Phase 1 — editor harness foundation

1. Create `src/Service/EditorGm/` subtree.
2. Add `EditorGmController`.
3. Add `EditorGmHarnessService`.
4. Add `EditorGmToolRegistry`.
5. Add `RoomEditorGmContextAssembler`.
6. Add a Room Editor API endpoint:
   `/api/room-editor/drafts/{draft_id}/gm`
7. Expose the endpoint + tool manifest in `drupalSettings`.
8. Add the assistant-first page layout skeleton:
   collapsible Author Tools drawer + persistent GM Assist panel.
9. Remove or migrate `EditorToolGmHarnessService.php` so only one editor GM
   harness entrypoint remains in the codebase.

**Exit criteria:** Room Editor can request a grounded GM harness snapshot for a
draft, receive the supported tool manifest, and render the assistant as a
first-class page region.

## Phase 2 — deterministic tools

Implement, wire, and test:

1. `load_draft_snapshot`
2. `validate_draft`
3. `check_publication_readiness`
4. `diff_draft_vs_published`
5. `inspect_catalog_entry`
6. `summarize_room_topology`
7. `load_canonical_definition`
8. `summarize_definition_usage`

**Exit criteria:** the harness can answer editor questions without any LLM step.

## Phase 3 — command planning

Implement a command-plan pipeline that turns structured authoring goals into
native Room Editor commands.

Target outputs:

- `add_hex`
- `remove_hex`
- `set_hex_terrain`
- `set_hex_elevation`
- `place_object`
- `move_object`
- `rotate_object`
- `update_object_overrides`
- `add_entry_port`
- `add_exit_port`
- `update_entry_port`
- `update_exit_port`
- `remove_entry_port`
- `remove_exit_port`
- `set_room_metadata`

- `plan_canonical_definition_patch`

**Exit criteria:** a proposed plan can be previewed without mutating the draft.

**Delivered.** Planning is implemented as `plan_room_commands`,
`preview_command_plan`, and `plan_canonical_definition_patch`. Preview runs
through `RoomEditorService::simulateCommands()`, which replays the same private
`mutate()` and `validateAggregate()` code as execution but writes nothing: no
revision bump, no command-log row, no draft update. Supported planning goals are
`set_metadata`, `expand_footprint`, `retheme_terrain`, `level_elevation`,
`place_objects`, and `clear_placements`; any other goal hard-fails with
`planning_goal_unsupported`. Plans are surfaced in the dedicated
`command_plan` envelope field so a proposal can never be mistaken for an applied
change.

## Phase 4 — execution pipeline

1. Add `apply_room_commands` execution tool.
2. Add receipt/result contract for batched application.
3. Add conflict handling for draft revision mismatches.
4. Surface command-level success/failure in the UI.
5. Add `update_canonical_definition`.

**Exit criteria:** approved plans execute through `RoomEditorService` only.

## Implementation guardrails for Codex

1. **Do not route editor requests through `GameMasterSubsystemService`.**
2. **Do not call `GmToolExecutionService` for editor mutations.**
3. **Do not create assistant-only draft mutation codepaths in the browser.**
4. **Do not widen public API surface unless the harness cannot satisfy the
   need.**
5. **Do not leave `EditorToolGmHarnessService.php` and the new `EditorGm/`
   subtree both acting as live entrypoints.**
6. **Do not split authority between assistant state and editor draft state.**

## Handoff-ready deliverables

Codex implementation should be considered complete for the first slice only
when all of the following exist together:

1. `EditorGmController` and `/api/room-editor/drafts/{draft_id}/gm`
   GET/POST routes
2. `EditorGm/` service subtree with a single canonical harness entrypoint
3. registered editor GM schemas in `contract_registry.json`
4. Room Editor page refactored to `author drawer | canvas | gm panel`
5. drawer collapsed by default on desktop
6. GM panel visible by default on desktop
7. assistant tool manifest includes room editing + canonical definition tools
8. tests proving editor GM services do not mutate `dc_campaign_*`
9. removal or migration of the old `EditorToolGmHarnessService.php` scaffold

## Delivery status — first slice shipped

All nine handoff deliverables above are implemented and verified against the
live runtime.

| Deliverable | Landed in |
| --- | --- |
| GET/POST `/api/room-editor/drafts/{draft_id}/gm` | `dungeoncrawler_content.routing.yml`, `src/Controller/EditorGmController.php` |
| `EditorGm/` subtree with one canonical entrypoint | `src/Service/EditorGm/EditorGmHarnessService.php` |
| Registered editor GM contracts | `config/schemas/contract_registry.json` (`editor_gm_request`, `editor_gm_response`, `editor_gm_tool_definition`, `editor_gm_command_plan`) |
| `author drawer \| canvas \| gm panel` layout | `templates/room-editor.html.twig`, `css/room-editor.css` |
| Drawer collapsed by default, GM panel primary | `RoomEditorShell._setAuthorDrawerOpen(false)` on init |
| Declared 18-tool manifest | `src/Service/EditorGm/EditorGmToolRegistry.php` |
| Authority-boundary tests | `tests/src/Unit/Schema/RoomEditorContractTest.php` |
| Old scaffold removed | `src/Service/EditorToolGmHarnessService.php` deleted |

Registered toolset (18):

- **context** — `load_draft_snapshot`, `load_published_snapshot`,
  `summarize_room_topology`, `list_catalog_definitions`, `inspect_catalog_entry`
- **validation** — `validate_draft`, `explain_validation_findings`,
  `check_publication_readiness`, `diff_draft_vs_published`
- **definition** — `load_canonical_definition`, `summarize_definition_usage`,
  `update_canonical_definition`
- **planning** — `plan_room_commands`, `preview_command_plan`,
  `plan_canonical_definition_patch`
- **execution** — `apply_room_commands`, `preview_publication_payload`,
  `publish_room_version`

Verification performed: all 15 tools executed against a live
`tavern_entrance` draft; batch `apply_room_commands` advanced the draft two
revisions through `RoomEditorService::applyCommand()`; `revision_conflict`,
unknown-tool, and bad-envelope cases hard-failed; HTTP GET/POST returned the
`editor-gm-response-v1` envelope and a missing CSRF token returned 403;
`RoomEditorContractTest` passes (6 tests, 123 assertions).

Phases 0 through 5 are complete. The Room Editor GM panel now supports the full
propose → preview → approve → apply loop: a planning tool returns a plan, the
plan panel offers **Preview** (non-mutating projection) and **Apply plan**
(execution through `apply_room_commands`), and discarding a plan leaves the
draft untouched.

Not yet implemented (deliberately deferred): **Phase 6** natural-language intent
parsing. The composer accepts explicit `tool_name {json arguments}` calls only
and rejects anything else, so there is no silent fallback path. Phase 6 should
add intent parsing that emits these same tool calls and command plans — it must
not add a second mutation path.

### Delivery status — Phase 6 (natural-language intent), verified live

Delivered:

- `config/schemas/editor_gm_request.schema.json` — `intent` is now a `oneOf` of
  `tool_call` and `natural_language`; both variants are `additionalProperties:
  false`.
- `config/schemas/editor_gm_response.schema.json` — added the
  `editor_intent_proposal` route family.
- `src/Service/EditorGm/EditorGmIntentParser.php` — resolves an utterance into
  exactly one registered tool call using `ai_conversation.ai_api_service`
  (`invokeModelDirect`, `skip_cache: TRUE`). The provider is optional (`@?`) and
  the parser hard-fails (`editor_gm_intent_parser_unavailable`,
  `editor_gm_intent_model_failed`, `editor_gm_intent_response_not_json`,
  `editor_gm_intent_tool_unsupported:<name>`,
  `editor_gm_intent_argument_missing:<tool>.<arg>`) rather than guessing. It
  holds no mutation authority — it never sees `RoomEditorService`.
- `EditorGmHarnessService` — `handle()` now dispatches on intent type into
  `handleUtterance()` or `executeTool()`.

**The safety rule: natural language may read and propose, but never mutate.**
A resolved non-mutating tool executes immediately. A resolved mutating tool is
returned as `route_family: editor_intent_proposal` with `requires_approval:
true` and a `proposed_execution` block; applying it requires the author to send
an explicit `tool_call`. An ambiguous request returns `intent: clarification`
instead of a guess.

Grounding is derived from authoritative sources, not hand-maintained copies:

- `EditorGmToolRegistry::commandPayloadContracts()` derives required payload
  keys per command type directly from `room_editor_command.schema.json`.
- `PlanRoomCommandsTool::GOAL_PARAMETERS` publishes the accepted parameter shape
  per planning goal onto the tool definition.

UI: the composer sends a first token matching a registered tool name as a
`tool_call` and anything else as a `natural_language` intent; natural language is
disabled outright when `context_snapshot.assistant.natural_language_available`
is false. A proposal renders an **Approve and run** button that re-sends the
call as an explicit `tool_call`.

Live verification (draft `5d911104…`, revision 77 before and after):

- `"Summarize the topology of this room."` → `deterministic_editor_tool`,
  `summarize_room_topology` executed.
- `"Rename this room to Iron Gate Vestibule."` → `plan_room_commands` returning
  a one-step `set_room_metadata` plan; no mutation.
- `"Publish this room as version 1.9.0."` (over HTTP, with CSRF) →
  `editor_intent_proposal`, `requires_approval: true`, `publish_room_version`
  **not run**.
- `"Publish this room now."` / `"Make it better."` → clarification questions.
- Malformed intents hard-fail: `editor_gm_intent_type_unsupported`,
  `editor_gm_utterance_required`, `editor_gm_tool_name_required`.
- `RoomEditorContractTest`: 8 tests, 161 assertions passing, including
  `testNaturalLanguageIntentCannotMutate`.

All six phases are now delivered.

## Phase 5 — publication assistance

1. Add `preview_publication_payload`.
2. Add `publish_room_version`.
3. Add publication blocker summary and release checklist.
4. Add starter-room hard-contract warnings where relevant.

**Exit criteria:** publication readiness and publish flow are available through
the harness without bypassing validation contracts.

## Phase 6 — GM/LLM authoring assist

After deterministic grounding is in place:

1. add natural-language intent parsing;
2. add prompt assembly from context snapshot only;
3. require tool-call/planned-command outputs, not direct prose-only mutation;
4. require user approval before execution.

**Exit criteria:** AI assistance produces tool-grounded command plans rather
than unstructured recommendations.

## Recommended initial API surface

### GET

`GET /api/room-editor/drafts/{draft_id}/gm`

Returns:

- harness contract/version
- supported tool registry
- context snapshot
- validation summary
- authority boundary

### POST

`POST /api/room-editor/drafts/{draft_id}/gm`

Accepts:

- request envelope
- tool name + arguments
- optional dry-run flag

Returns:

- route decision
- tool result
- proposed command plan
- validation/errors

## Acceptance criteria

1. Room Editor GM harness is isolated from `dc_campaign_*` mutation tools.
2. All editor writes still pass through `RoomEditorService`.
3. The tool registry is explicit, documented, and returned to the client.
4. Deterministic tools work without LLM assistance.
5. Planned commands are previewable before execution.
6. Execution is revision-checked and idempotent where applicable.
7. Publication remains contract-gated.
8. No legacy room-chat compatibility overlay is required anywhere in the editor
   harness flow.

## Recommended ownership

| Track | Owner |
|---|---|
| Product framing and sequencing | `pm-dungeoncrawler` |
| Harness architecture and contracts | `ba-dungeoncrawler` + `dev-dungeoncrawler` |
| Service/controller implementation | `dev-dungeoncrawler` |
| UI embedding in Room Editor shell | `dev-dungeoncrawler` |
| Validation and acceptance coverage | `qa-dungeoncrawler` |

## Immediate next step

Start with **Phase 0 + Phase 1 only**:

1. create the `EditorGm/` namespace;
2. define the schemas;
3. add the Room Editor GM endpoint;
4. expose the tool registry + grounded context snapshot in the editor shell.

That gives us a real harness backbone before we attempt AI-assisted authoring.
