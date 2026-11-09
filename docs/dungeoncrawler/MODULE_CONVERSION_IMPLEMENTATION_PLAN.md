# Module conversion: design before implementation

Status: the actor-completeness and unified-validation sections are design
only. The reviewed source-art adaptation has been published separately;
neither it nor `island-original-story-v1` is a source-faithful tactical or
encounter conversion. Scope is the reusable conversion workflow, with the
Island release as one acceptance case.

## Baseline and decision boundary

- Existing release services inspect an immutable archive/plan, record native
  receipts, generate original rooms through the canonical Room Editor, assemble
  them with the canonical Dungeon Editor, validate scene/quest coverage, and
  publish an immutable module release. The Island adaptation has ten rooms, ten
  original waymarks, and a completed 20-objective rollback-only player audit.
- Nine Island scene grids are 5-foot square tactical scenes; the tenth is a
  3-mile overland overview. Actual reviewed tactical traces: **0/9**. The
  read-only `ModuleSceneHexTraceService` requires cell and port review, rejects
  vector walls, and produces two hexes per square. It preserves orthogonal
  connectivity, **not** the square grid's movement distance or diagonal rules.
  `ModuleSceneRasterDiagnosticService` produces unreviewed hints for three
  lair images, not terrain or doors. No source encounter placements are
  approved.
- Published-dungeon provisioning now certifies the runtime H3 ledger before
  structural readiness; the map controller only reads readiness. Include this
  invariant in every new release's campaign launch audit. Existing campaign
  1128 was separately repaired through the provisioning service.
- A source-faithful designation requires rights to use each source asset and
  independently reviewed correspondence. Without those approvals, retain
  source imagery only in its permitted illustrative role and publish original
  content under an **adaptation** label. Do not ship third-party source files
  as test fixtures or send source prose/art to an image or text provider.

## One canonical path

`ArchiveInspector -> deterministic Plan -> GapAnalysis -> unpublished
proposals/review -> RoomEditor drafts/versions -> DungeonEditor layout/version
-> ReleaseService receipts and sceneCoverageReport -> campaign initializer ->
H3 projection ledger -> runtime/player audit`.

Every scene authoring mode must converge on the existing Room Editor command,
simulation, validation, and publication path. A reviewed trace is an
alternative *input* to that path, not a second room generator, room store,
movement engine, or publisher. Existing original-generation callers must
continue using that same path. Quest definitions go through the canonical
quest-template publisher and lifecycle; images through
`ImageGenerationIntegrationService` and `GeneratedImageRepository`. The
release service alone owns immutable execution, coverage, and publication.

## Campaign launch-readiness design

The published-dungeon creation branch previously wrote `structural_ready`
without H3 ledger certification. Campaign 1128 had all ten sparse room maps
but no ledger rows; the canonical `provisionLaunchSliceNow()` call repaired its
ten-room scope. The provisioning call is now in the published-dungeon
initializer before the ready transition. Follow-up design and acceptance:

- **Producer ordering:** create campaign rooms, connector authority and
  dungeon; persist exact runtime sparse H3 mappings; persist active state;
  certify the scope synchronously through `H3ProjectionQueueService`; record
  its room-scope hash and both graph-version tokens; only then persist
  `structural_ready`. Keep these writes in the campaign creation transaction.
  Failure must roll back creation, not return a ready-looking campaign.
- **Read boundary:** `HexMapController` and selectors check
  `ensureLaunchSliceReadiness()` against current graph versions and required
  room IDs; they never project cells or create ledger rows on a launch
  request. Active-room and next-room scopes must be covered by the writer.
  Subsequent authoritative graph mutations must reprovision the affected
  frontier before navigation exposes the new graph.
- **Idempotence and repair:** choose current dungeon-owned sparse mappings
  before any same-named room mapping in another campaign; require both anchor
  and cells per certified room. A privileged, explicit repair of an existing
  campaign must verify its campaign room IDs against sparse mappings, call
  that **same** provisioning service, and audit the repair time separately
  from original creation (never rewrite historical certification metadata
  as though it existed at creation).
- **Verification gate:** fail if any room status or current graph version is
  missing, failed, or stale. Cover both starter-theme and published-module
  creation, rollback on missing H3 cells, duplicate room IDs across campaigns,
  version changes after mutation, and an unchanged read-only 503 on absent
  certification. A live check must establish both entrance and full-scope
  readiness before accepting a new module release; a readiness result alone
  does not prove browser launch or complete gameplay.

## Proposed reviewed-artifact contracts

1. **Gap/proposal**: archive SHA-256, exact plan hash, resource ID/type,
   explicit absent-versus-present source fields, assumption list, seed/provider
   provenance, proposal status, and content hash. A generated image records
   ready provider, image ID, byte SHA-256, and project-original rights review.
   A generated proposal is unpublished and cannot become a receipt or launch
   source without an operator's review and the canonical editor validation.
   Provider errors and mismatched hashes fail explicitly.
2. **Tactical review**: archive/plan hash, exact scene resource and background
   hashes, source dimensions/grid units, every cell's reviewed footprint and
   terrain, explicit boundary/door ports and exit destinations, reviewer and
   rationale, review hash, and a recorded movement policy. Diagnostic pixels
   never become reviewed cells automatically. Vector walls, conflicting
   annotations, unresolved transitions, and missing rights block approval.
3. **Encounter/quest review**: each resource-to-scene placement cites its
   source evidence *or* declares a project-original adaptation; canonical
   definition ID/version, room placement anchor, objective type/target/room,
   trigger, reviewer, and rights status are pinned. Missing source placement
   cannot be represented as verified source placement. Each objective must
   resolve against the exact pinned room and run through the normal player
   action and quest lifecycle.
4. **Release attestation**: a fresh conversion revision pins reviewed-artifact
   hashes, exact Room Editor version receipts, dungeon version, quest version,
   rights decisions, and the explicit fidelity classification. Publication
   recalculates hashes and checks every required resource. No old release
   acquires a new fidelity label by editing metadata.

## Implementation slices and exit gates

1. **Inventory and acceptance matrix (design/input gate).** Enumerate all
   scene grids, image rights, vector walls, doors, source descriptions, actor
   and item evidence, and destination links. Mark each field `source_present`,
   `absent`, `ambiguous`, or `original_adaptation`. Decide whether each scene
   is tactical, illustrative, or overland. Output a pinned per-resource review
   queue; do not guess data to clear a gap. Keep bounded inspection responses
   for large inventories.
2. **Reusable original proposal authoring.** Extend the existing
   `ModuleConversionGapAnalysisService` and Module Manager review/bind UI to
   offer scene metadata, original art, and quest proposals as well as the
   current actor/item proposals. Use the existing image integration and
   canonical generation services; pass only project-original prompts and safe
   structural hints. Review, edit, and bind exact hashes and canonical
   versions, then pass accepted definitions to the existing
   `original_scene_generation` request. Exit: a project-authored incomplete
   three-scene fixture covers absent geometry/art/story, failed providers,
   stale hashes, review rejection, and a successful original release. This
   slice does not claim source fidelity.
3. **Tactical review and geometry.** Provide a bounded reviewer view over
   image/grid registration, diagnostic hints, cell annotations, wall and door
   evidence, and port/destination pairs. Extend the *existing*
   `ModuleSceneHexTraceService` for reviewed wall cases only after a contract
   and tests exist. Project reviews through
   `ModuleSceneHexRoomValidationAdapter`, then apply to canonical Room Editor
   drafts/versions using its normal command and publication flow. Do not let
   its present `publication_ready: false` output directly enter a release.
   Exit: every tactical scene has an independently reviewed image alignment,
   full cell/port coverage, one reachable valid footprint, and exact version
   pins; 0 unreviewed cells and 0 inferred doors.
4. **Movement semantics (hard fidelity gate).** First specify allowed
   orthogonal and diagonal transitions, 5-foot square-center distances,
   diagonal alternation, difficult terrain, collision/actor occupancy, and
   port traversal. Verify that the current two-hex embedding can implement
   these via the **existing** `MovementResolverService` and every caller that
   charges movement; do not change cost in only one API. If equivalent
   gameplay cannot be demonstrated, label the result `source_informed` rather
   than `source_faithful`, retain the current original adaptation as the
   launchable release, and block the tactical fidelity gate. Include
   shortest-path and player-action cases that expose 2.5/5/10-foot and
   diagonal discrepancies, not just connectivity.
5. **Overland and encounter authoring.** Keep the 3-mile overview out of the
   tactical room tracer. Review location/transition scale through the existing
   map and navigation authorities before authoring an overland projection;
   if none can represent it without a parallel navigation path, keep the image
   illustrative and block the source-faithful designation. Place reviewed
   canonical actors/items/interactables and exact-room quest objectives
   through the existing editors and quest lifecycle. Exit: all required
   encounters and objectives are enumerated with explicit evidence or
   adaptation labels, and player actions verify their triggers.
6. **Immutable execution, launch and rollout.** Extend
   `ModuleConversionReleaseService` preflight and `sceneCoverageReport` to
   require the correct reviewed artifacts for the selected release
   classification. Reuse its receipts and canonical dungeon assembly; do
   not add a second execution/publisher. Stage a *new* revision, run negative
   contract gates and generic fixture audits, then rollback-only Island
   publication/player trials. Verify all required tactical scenes (9/9),
   the overland decision (1/1), exact rights and version pins, room/port
   movement, encounter actions, quest completion, campaign H3 readiness,
   browser map alignment, and ten-room traversal before making the new
   revision launchable. Keep the published original adaptation unchanged.

## Actor completeness and one conversion-validation gate (design only)

**Current gap.** The Island archive inventory contains 17 actor resources.
All ten inventoried scenes report `scene_has_no_tokens` (no source token
placements); source actor identity alone supplies no room coordinates.
The published survey, original-story, and story/source-art revisions each
place **zero actors or creatures** in their ten canonical rooms. Their ten
placed items and completed scene/quest coverage do not establish actor
coverage. `ModuleConversionGapAnalysisService` identifies actor and placement
gaps, but `ModuleConversionReleaseService::sceneCoverageReport()` checks
rooms, reviewed art, nonempty placements, dungeon validity, and quest
objectives; a waymark item satisfies its nonempty-placement check. The
rollback-only `module_conversion_full_validation_test.php` verifies required
execution receipts, not publication or gameplay. There is no single current
function that certifies every generated object's source coverage, placement,
runtime representation, and player behavior. An actor listed in the archive
is not evidence that the module places that actor in a particular scene.

**Source-to-canonical inventory.** Extend the existing archive inspector and
deterministic plan to record every actor resource and every scene token as
distinct identities, with archive/record hashes, source actor reference,
scene ID, source coordinates and scale, token disposition and any embedded
prototype or external-compendium reference. Preserve duplicate instances;
do not equate one definition with one placement. Reconcile token references
against the 17 actor resources and explicitly classify each actor as
`placed_in_source`, `library_only`, `external_reference`, or
`unresolved`. Missing token data must remain *unknown*, not become an
empty-encounter assertion. Reject dangling references, ambiguous IDs,
conflicting token data and unreviewed rights. Produce a per-scene matrix of
expected instances, not a single aggregate actor count.

**Authoring and placement.** Feed absent/ambiguous actor fields through the
existing gap analysis and canonical definition proposal/review/bind flow;
record source evidence versus project-original adaptation separately.
Require reviewed actor role, rights, definition ID/version, and source
resource receipt before use. For each source token require reviewed
scene/room pin, placement anchor, facing and instance identity; map source
coordinates to hexes *only* under a reviewed image/geometry alignment.
Otherwise leave placement blocked for a source-faithful release. Place
approved actors/creatures through the same Room Editor commands, simulation,
publication validation, and pinned dungeon assembly used for items, then
instantiate them via the **standard actor harness** at campaign creation.
Library-only actors require a canonical definition and an explicit
not-placed classification; do not invent a room for them. For the Island
encounter-complete target, review scene descriptions/journal links and
other attested location evidence for each of the 17 actors. If no source
placement can be substantiated, request an original encounter/placement
proposal through the content-generation workflow, review its room/anchor
and gameplay intent, and label the resulting placement as an adaptation,
not a recovered source token. Require a reviewed placement decision for
every actor, including a reason if deliberately catalog-only.

**Unified validation entry point.** Add one release-level
`validateConversion($release_id, $manifest, $profile)` operation in
`ModuleConversionReleaseService`, backed by the existing validators rather
than parallel room, definition, quest, or actor validators. It must work on
an unpublished release using the proposed exact dungeon/version receipt;
the current public `sceneCoverageReport()` needs published mapping pins and
cannot alone certify a staged revision. Return one structured report with
archive/plan/manifest hashes, selected fidelity profile, per-resource
findings, expected/verified definition and placement counts, room and
version IDs, and gate results. Run, in dependency order:

1. Archive/plan provenance and a **complete inventory of every source data
   object**: adventures, scenes and constituent tiles/tokens/walls/doors,
   actors and embedded data, items, journals/pages, folders, rolltables and
   results, links, images and licenses. Reconcile stable IDs, hashes,
   dependencies and exact native receipts. A receipt is evidence of
   execution, **not** proof of a complete converted object.
2. Validate each required object's full schema and semantic completeness
   against its existing canonical owner: mandatory metadata, rights and
   provenance, type-specific stats and references, playable definition
   versions and quarantine state, and every embedded child. Require each
   source object to have either an exact canonical equivalent or a reviewed
   profile-specific disposition with a reason and evidence. `reference_only`
   and `unsupported` must appear in the report; they are not silently
   dropped or treated as successfully converted. For a profile promising
   complete conversion, any required unsupported or unresolved object blocks
   certification.
3. Validate **every relationship and placement**, not just presence:
   actor/token instances, items, hazards and interactables against reviewed
   source scene references; journals, quests, tables and embedded items
   against their owners and targets; room/hex anchors against exact pinned
   geometry and ports. Compare identity, quantity, family, definition
   version, scene, coordinates or reviewed translation, and applicable
   objectives. Fail duplicates, missing/wrong-room objects, missing
   metadata, broken dependencies and unapproved invented placements.
4. Reuse Room Editor publication validation and traversal,
   rights/image/hash checks, Dungeon Editor topology and scene coverage,
   canonical quest target/room checks, and all relevant canonical definition
   validators. An original adaptation can report outstanding
   source-fidelity gaps without claiming source completeness; the selected
   fidelity profile sets which findings are blocking.
5. A rollback-only fresh-campaign audit of provisioned H3 readiness,
   actor instances and active-room visibility, and standard actor-harness
   actions (including representative interaction/combat and travel).
   This **post-publication launch certification** is separate from static
   release validation; never claim it ran from a scene-coverage report.

**Repair loop, not a one-pass checklist.** Each finding identifies source
object ID, field/path, expected evidence, missing/ambiguous/invalid value,
dependent objects, canonical owner and permissible remediation. Classify
missing placement independently from missing definition or metadata. Use
`ModuleConversionGapAnalysisService` as the shared gap classifier: expand
it beyond actor/item proposals to identify every incomplete object and its
dependency graph. For absent or ambiguous data, request a **draft**
project-original proposal through the existing content-generation service
for that object family; where no canonical generator exists, leave a
blocking manual-review task rather than invent a competing importer.
Proposals record provider, seed, source/plan hashes, generated-field
provenance, assumptions, rights and confidence, and require explicit
review/approval. Never label generated values as source facts. Resolve
source-backed fields from actual evidence; do not silently fill them with
defaults. Reviewed placement proposals must be tied to approved canonical
geometry and to a real source anchor or clearly labeled original
adaptation. Apply accepted repairs through the **existing** canonical
definition, editor, image, quest and release write paths; revalidate
affected objects and dependents against the latest pins, then rerun the
whole-release gate. A changed source hash, proposal, definition, room or
dependency invalidates prior signoff. Continue until every required
object and relationship passes with **zero blocking findings**; never
turn an exception, missing validator or unsupported family into
`complete: true`.

**Execution boundaries and validator routing.** Make validation read-only:
`validateConversion()` inspects the pinned proposed state and returns
findings, without generating content, saving definitions, publishing rooms,
or modifying campaigns. A separate, existing Module Manager review/action
flow consumes those findings to request proposals, approve them, and invoke
canonical writes. The next validation run loads the newly pinned versions
and computes a fresh verdict. Keep the immutable source inventory as the
left-hand side of every comparison; a missing object must not disappear
from the expected set simply because a converted object was never
created. Use the existing plan's exact resource IDs, plus stable
child-instance IDs for tokens, tiles, results and other embedded records.
Store the reviewed mapping and approved disposition in release-owned
review data so both the authoring action and the publication validator
consume the **same** contract. Do not maintain a second ad hoc audit
mapping in an Island-specific script.

Route each family to its existing canonical authority. Definitions,
including actors, creatures, items, hazards and their embedded content,
must pass the definition service's applicable schema and publication
profile; additional actor/runtime constraints belong to the standard
actor harness. Scene geometry and object anchors go to Room Editor
simulation and publication validation; connections and exact placement
pins go to Dungeon Editor. Map bytes, tile positions, notices and rights
go through the existing image staging and attestation service. Quests
and journal-linked objectives go through the canonical quest lifecycle.
Rolltables, results, folders and journal pages must have an explicit
reconciled owner and validator before a profile that requires them can
pass. If a family has no supported canonical representation or validator,
report `validator_unavailable` for that family; do **not** accept a
syntactically valid receipt as a substitute. A placement is validated
both as an object owned by a room and as an edge from a reviewed source
instance to a specific canonical version, preventing "valid actor,
wrong room" from passing.

**Finding-to-repair protocol.** Each finding is a stable,
machine-readable work item with `source_resource_id`, child/instance
path, category (`missing`, `ambiguous`, `invalid`, `unplaced`,
`unsupported`, or `stale`), severity for the selected fidelity profile,
expected source evidence, observed canonical state, owning validator,
and affected dependencies. The repair action is one of: bind an
existing approved version, generate a **project-original draft** for
missing content, submit a manual evidence-backed correction, review a
source-to-canonical placement, or explicitly record a not-applicable
disposition where policy allows. Separate data provenance from object
completeness: generation may complete a missing *adaptation* field but
cannot convert an unknown source fact into an attested source value.
For example, a missing actor role can receive an original proposed
role and reviewer approval; a missing scene token coordinate cannot
become "source placement" by guessing an anchor. A failed generation
provider, rejected proposal, invalid definition, or ambiguous reviewer
decision leaves the original finding open. Canonical writes occur under
the owning service's normal transaction and versioning rules; changing
one room's geometry invalidates placement reviews for that room.

**Report and workflow surfaces.** Extend the Module Manager release
view to show, for each family, inventory total, required canonical
objects, validated objects, expected instances, verified placements,
unresolved reviews, and blocked/approved dispositions. Allow drilling
into a source ID and opening its existing proposal/review/bind action,
not a second "complete" checkbox. The JSON report has an overall
verdict, gate verdicts, sorted bounded findings and a cursor for
additional findings, plus complete aggregate counters computed over
*all* records. This is essential for the Island's large rolltable
result collection: paginate review output, not validation itself.
Store a hash of the exact source archive, plan, review set, native
receipts and target version pins with the verdict. A report is stale
if any of those hashes change; publishing must re-evaluate inside
the transaction, not trust a prior displayed green badge.

**Release profiles and hard exit.** Define required families and
relationship policies explicitly for each declared deliverable.
`original_adaptation` validates every object it **claims** to convert
and exposes source omissions as visible scope gaps; it cannot advertise
full actor or source-object coverage. `encounter_complete_adaptation`
additionally requires a reviewed placement decision for every source
actor and a validated canonical instance for each approved encounter.
`source_faithful` requires all source-required objects and exact
placements with reviewed source evidence, with no unresolved,
unsupported, or project-original substitutions for source claims.
The gate must make these differences unmistakable in both the release
report and campaign-source label. Publication is blocked on any required
family with missing metadata, absent mapping, invalid child, unapproved
placement, stale version, or unavailable validator. A future
campaign-launch audit records `launch_certified` only after the
published release has actually provisioned and rendered its required
objects; static completeness never implies a successful player action.

Make the existing Module Manager coverage endpoint expose this same static
report, and make `registerPublishedManifest()` invoke the identical blocking
gate inside its publication transaction. No `complete: true` when any
required resource, placement, service validation, hash, or exact pin fails;
give resource ID, scene ID, expected versus observed value, and producer
boundary in every blocking finding. Do not retrofit the three published
Island adaptations or claim they contain encounters. Stage a new immutable
encounter-bearing revision only after its reviewed evidence and validation
are complete.

**Acceptance gates for implementation.** A small project-authored fixture
must exercise a placed actor, two instances of one definition, a catalog-only
actor, a missing type resolved by reviewed generation, an unresolved
compendium reference, a scene with missing token evidence, an embedded item
with incomplete metadata, a missing tile/notice and a table result pointing
at an absent resource. Negative
cases must fail for missing/wrong-room placements, unreviewed alignment,
invalid definition versions, stale hashes, and a quest target in another
room; valid cases must prove all source actors are classified and all
required instances pinned. Exercise a repair cycle: missing field or
placement -> draft proposal -> review -> canonical write -> dependent
revalidation -> complete, and reject stale approval after a dependency
changes. For Island, report per-family `source / classified / validated /
placed where required` totals across the **entire** inventory, including
`17/17` actor identities accounted for, `0` unresolved actor or token
references, exact reviewed per-scene instance counts (not inferred from
the actor total), `17/17` reviewed placement decisions (including any
approved catalog-only decisions), ten validated room pins, then perform rollback-only
fresh-campaign actor-harness and quest checks before publishing an
encounter-complete revision. The archive contains large table-result
collections; process and summarize them in bounded batches without
skipping validation or flooding the reviewer view. Record separate
verdicts for `receipt_complete`, `static_release_complete`,
`source_object_complete`, `source_actor_complete`, and `launch_certified`.

## Codex handoff: generative conversion architecture

**Scope and evidence accepted for this handoff.** Treat the Island validation
findings as valid, without re-diagnosing them: all three releases have complete
receipts and scene coverage but fail source-metadata completeness; the
encounter-complete profile additionally lacks decisions and placements for
17 source actors; source-faithful conversion additionally lacks source-backed
actor-placement evidence. The latest source-art revision reports 195
incomplete metadata objects. The goal is a reusable process for *other*
modules as well as Island, not a hardcoded fix to one release. This is a
high-level design; Codex owns the detailed code trace and implementation.

### Architectural outcome

Deliver one pipeline with one set of canonical owners:

`source inventory -> evidence/gap classification -> original proposals when
source evidence is absent -> human review -> canonical definition/room/story
authoring -> pinned release -> full-object validation -> campaign launch
audit`.

Conversion must distinguish **source evidence**, **reviewed interpretation**,
and **project-original generation** throughout. A generated answer never
silently becomes source truth. Existing canonical content-generation,
definition, image, room/dungeon editor, quest, validation, and actor-harness
services remain the sole implementation paths. The module-conversion layer
orchestrates and carries provenance; it must not become a competing content
store, validator, room generator, publisher, or runtime actor system.

### Capabilities to build

1. **Evidence and completeness inventory.** Preserve each source object,
   embedded child, relationship, map asset and instance under a stable
   identity; inventory both available and *missing* required metadata.
   Build explicit dependency edges (for example, actor -> encounter
   placement -> scene -> room version, and table result -> referenced
   definition). Record available source location evidence separately from
   an actor's mere appearance in the module catalog. Inspect source files
   and narrative evidence only under applicable rights and local-provider
   rules. The 17 Island actors require individual accounting, not a
   single count or automatic assignment to rooms.
2. **Gap-to-proposal orchestration.** Make validation findings actionable
   requests categorized as source-resolvable, ambiguous, missing,
   unsupported or invalid. First resolve evidence-backed fields from the
   source; when source data is absent, ask the existing content-generation
   subsystem for a **draft project-original proposal** appropriate to the
   object family. Needed proposal types include actor/creature definitions
   and roles, item and embedded-item details, scene metadata where truly
   absent, quest/story material, and *encounter/placement plans*. A
   placement proposal selects a reviewed canonical room and valid anchor
   but never asserts source coordinates that were not provided. Do not
   invent formulas, journal text, table outcomes, walls or terrain to
   satisfy a source-faithful gate. Unsupported families remain blocked
   pending an approved canonical owner or an explicit scope decision.
3. **Review and provenance.** Show each candidate with the source object
   ID, missing fields, available evidence, assumptions, origin
   (`source`, `interpretive`, or `project_original`), provider/seed when
   generated, review decision, rights, and affected dependent objects.
   Reviewers can approve, edit and resubmit, reject, bind an existing
   canonical object, or declare a catalog-only actor with a reason.
   Persist reviews against exact archive/plan/definition/room hashes;
   stale decisions cannot be reused after dependencies change. Original
   content can complete an **adaptation**, never upgrade a release to
   source-faithful by assertion.
4. **Single canonical authoring path.** Convert approved drafts into
   definitions, instance placements, room versions, dungeon links,
   images and quests using their existing owners. Preserve authored hex
   footprints. Enforce placement count, correct room/version, reachable
   unoccupied anchor and source-instance identity; multiple instances of
   one definition need separate placement records. Instantiate actors
   through the standard actor harness on campaign creation, not a
   special module-only harness. Keep conversion revisions immutable;
   author a new release instead of mutating published Island releases.
5. **Full-object release gate and repair cycle.** Reuse the existing
   release-level validator as the *single* read-only verdict. It must
   evaluate every required object and dependency through its canonical
   validator, not treat a receipt or a nonempty room as proof of
   completeness. Return per-family expected, resolved, validated and
   placed counts plus bounded, stable findings. Route each finding to
   evidence review or a generation proposal; after canonical writes,
   invalidate dependent approvals and rerun the entire gate. A missing
   validator, generation failure, invalid metadata, unresolved reference
   or missing placement is a blocking result, not a success-shaped
   default. Publication must re-evaluate that same verdict against the
   exact pinned release inside the transaction.
6. **Separate launch certification.** Static conversion validation is
   not proof of live playability. A new-campaign, rollback-only audit
   checks readiness, placed actor instantiation and visibility, room
   travel, interaction, combat where applicable and quest progression via
   the standard gameplay authorities. Keep source-fidelity and
   encounter-completeness verdicts distinct from launch certification.

### Delivery order for Codex

**Phase A: make findings actionable.** Establish stable identities,
dependency/provenance contracts, profile-specific requirements, complete
per-family reporting and an explicit review queue for all currently
flagged objects. Avoid flooding the UI with large table-result families;
paginate findings while validating all records. End with every Island
gap assigned an owner, remediation path and evidence classification.

**Phase B: connect generation to review.** Extend existing proposal
capabilities to missing canonical object metadata, reviewed original
encounters and placement suggestions. Enforce local/original-input
restrictions, reproducible provenance, canonical schema checks and
human approval. End with no proposed data silently written to the
canonical store and no invented value presented as source evidence.

**Phase C: author a new converted release.** Apply approved metadata and
encounters through the existing editors and definition/quest owners,
preserving existing room geometry and reviewed source-art rights. End
with exact version receipts, all required actors classified, approved
instances pinned to the intended rooms, and missing metadata resolved
or explicitly blocked according to the release profile.

**Phase D: enforce and exercise the gates.** Run the full-object validator,
repair remaining findings through the same review loop, then do
rollback-only campaign/actor-harness checks. Publish only a *new*
immutable revision after the selected profile reaches zero blocking
findings. Leave source-faithful blocked if the archive does not supply
the evidence needed to justify source-equivalent placement, geometry
or movement semantics.

### Required negative and positive cases

Use original project-authored fixtures to demonstrate missing metadata,
provider failure, unapproved or stale proposal, unresolved external
reference, wrong-room/duplicate/missing actor, repeated instances of
one definition, catalog-only actor, missing tile/license, and invalid
embedded item or table result. Each must yield an actionable blocking
finding without partial publication. A successful adaptation must
resolve every *claimed* object and reviewed placement, validate exact
versions, and launch a fresh rollback-only campaign where the actors
are present in their approved rooms. A source-faithful claim additionally
requires genuine reviewed source evidence, not generated substitutes.

### Implementation status (Codex baseline now in code)

- Added actor review persistence for conversion releases:
  `POST /api/module-manager/releases/{release_id}/actor-placement-review`
  with immutable-release guards, exact `expected_plan_hash` pinning,
  required rationale/origin/status, and placement contract checks for
  `status=placed`.
- Expanded conversion validation output to include per-family coverage
  counts (`expected`, `requires_authoring`, `with_receipts`,
  `metadata_complete`, `placed`) and an actionable remediation queue.
- Placement decisions are checked against a pinned room/version, instance ID,
  definition version and canonical `{q,r}` anchor; a receipt alone is not a
  validated definition and is not reported as one.
- Validation now treats malformed/stale actor reviews as blocking and
  enforces source-evidence expectations for source-faithful actor decisions.
  Source-faithful certification remains blocked until source scene token
  instance/coordinate evidence is independently matched; a review's
  free-form evidence cannot certify itself.
- Findings contexts are now bounded/stable (`count` + `truncated`) to keep
  outputs actionable at large module sizes without hiding total impact.
- Module Manager UI now exposes:
  1) full conversion validation (profile + optional dungeon/version pin),
  2) validation report/findings rendering,
  3) actor placement review submission workflow.

These are partial delivery of the architecture, not completion of phases A-D.
Metadata-resolution proposals, canonical writes for approved encounter
placements, per-object canonical validator coverage, campaign certification,
and a completed new Island revision remain outstanding.

**Handoff boundary:** Codex should inspect implementation contracts,
choose precise API/storage changes, implement and validate the above
phases. Do not re-run the Island failure investigation merely to begin
work; use the findings above as accepted inputs. Do not change the
three existing published Island revisions or patch campaign 1128.

## Release decision

`source_faithful` requires **every** relevant contract above to pass,
including rights, geometry, distance/diagonals, overland semantics, encounters,
quest, and live launch-readiness certification. A failed or unreviewed gate
produces explicit blocking findings, never an automatic fallback or an
upgraded fidelity label. A reviewed original adaptation can ship separately
without falsely satisfying those gates.

## Campaign 1128 live map analysis (2026-10-06)

The user-reported `island-original-story-v1` campaign is a diagnostic case,
not permission to alter the immutable release or source assets. The URL
selects campaign character row 5531 in Island Lookout. Inspection of live
campaign rows and the active map path established:

- Ten campaign rooms and nine connectors exist; the map request deliberately
  asks `RuntimeGraphAssemblerService` for depth-one coverage, yielding three
  rooms at Island Lookout. Keep that bounded gameplay payload; expose a
  separate lightweight **full-dungeon topology summary** if the UI needs all
  ten rooms at once. Do not mistake a three-room neighborhood for failed
  generation.
- Island Lookout has 23 authored hexes but the visual projector fills its
  7-by-5 bounding rectangle with 12 additional floor hexes. Eight other
  rooms also gain synthetic floor hexes (7-12 each). The same projector
  ignores the room's `entry_points` and marks the center `(0,0)` as the
  entrance, although the authored entrance is `(-3,0)`. Both authored
  Lookout ports have outward-facing edges. Fix the **existing**
  `MapVisualStateProjector` to display only authored cells and to use the
  authoritative entry/port coordinates; reject malformed geometry instead
  of inventing floor. Verify the exit/connector marker after room-to-level
  placement transformation before attributing its apparent location to a
  generation error. Exit gate: 0 synthetic traversable/visible cells,
  exactly the reviewed port markers at their authored boundaries, and
  player/visual topology agreement for all ten rooms.
- The Lookout's original image exists and returns HTTP 200, but it has **no
  reviewed hex alignment** and is not a tactical background. Its metadata
  is in `dc_campaign_rooms.layout_data`; `RuntimeGraphAssemblerService`
  drops that metadata, while canvas drawing needs `source_map.alignment`.
  The Room View gallery's source-asset contract additionally requires
  license-notice/author fields not present on this project-original image.
  Extend the existing image projection and gallery paths to distinguish
  project-original art from attested source art, without fabricating source
  attribution. Show approved original art as illustrative gallery content;
  fit it to the tactical canvas only after separate alignment review.
  Exit gate: ten accessible original-art entries (or an explicit unavailable
  finding per missing asset), and no unreviewed tactical overlay.

### Room backgrounds: source-art revision (current focused delivery)

**Five Whys.** (1) The module background does not appear because the
original-story release pins a project-generated illustration instead.
(2) That release's staging input chose `original_map`, so the existing
`maps`/`map_review` source-art path was never exercised with its story.
(3) Even reviewed source art could only appear in the gallery: runtime room
assembly discarded `metadata.module_conversion.source_scene`, and the canvas
required reviewed tactical alignment. (4) The distinction between
illustration and tactical map was not represented as a drawing mode.
(5) Conversion and projection had no end-to-end acceptance gate that
asserted the reviewed image actually reaches the room while preserving
canonical hexes. Repair the existing staging, projection and validation
path, not individual campaign rooms.

Stage a new immutable story revision with the existing ten-room source
identities, story item definitions, quest objectives and canonical room
generation, using the archived backgrounds/tiles through the existing
`islandMapRights()` attestation and `maps` plus explicit `map_review`.
Reject missing archive hashes, notices, scene coverage, review, or tiles.
Keep the published original-story release and campaign 1128 unchanged.
The canonical room generator owns hex and port geometry; project only
authored hexes, without synthesizing a bounding-box floor. Pass reviewed
source-scene metadata through runtime assembly and project the source image
as **illustrative_background** with source tiles. Fit and mask it to exactly
the visible authored hex footprint; it must not imply source-grid terrain
or substitute for a reviewed tactical alignment. Keep the original
author/license-notice links in the Room View gallery. Invalid image
contracts must throw, not silently substitute generated art.

**Fresh-campaign handoff gate:** a new release has ten exact pinned room
receipts and quest coverage; each room resolves its reviewed archive image
and any tiles with the expected license notice; room payloads retain
`source_map.display_mode=illustrative_background`; rendered backgrounds
load behind exactly the authored visible hexes; and a new campaign points
at the new release without altering campaign 1128. Stop for player
validation at that point; follower, encounters and global map UX are out
of scope for this image slice.
- Burasco (campaign character row 5531) occupies Lookout; his owner-linked
  familiar Mimi (row 5532, instance `familiar-1033`) remains at the
  starting Slate Ward. A second global Mimi record (row 5533) is not the
  owner-linked follower and must not be moved to disguise the failure.
  `moveEntityToRoom()` moves only the selected actor; subsequent follower
  sync favors Mimi's stale persisted room. Add owner-linked follower
  placement to the **same authoritative transition transaction**, with
  destination H3 and free-hex validation through the standard actor harness.
  Do not cure it with a map-read relocation. Exit gate: Mimi and owner share
  the destination after each valid transition, persisted row and rendered
  entity agree, failed travel changes neither, and unrelated global actors
  remain untouched.
- All ten published rooms have one item and zero authored NPCs, creatures,
  traps, or interactables. This is an **original adaptation content gap**,
  not an invisible encounter rendered by the UI. Author and validate
  encounter placements in a new reviewed immutable revision through the
  existing canonical editor and quest flow. The campaign state and stored
  dungeon snapshot still name the starting room while Burasco's runtime
  character is in Lookout; trace the transition state writer and reconcile
  only at authoritative mutation points, never on a read request.

Repair order: (1) visual projection and exact room/port diagnostics; (2)
original-art gallery contract; (3) follower/active-state transition
authority; (4) lightweight full-map summary; (5) reviewed new-revision
encounters. Validate each against campaign 1128 read-only evidence and
rollback-only synthetic player travel before changing any published pin.
