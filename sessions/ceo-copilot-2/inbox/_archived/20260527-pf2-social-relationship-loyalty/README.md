# Implement PF2e-inspired social relationship and loyalty system

- Agent: ceo-copilot-2
- Requested-by: Board
- Requested-at: 2026-05-27T21:00:41+00:00
- Source: Board command

## CEO closeout

- Closed-at: 2026-06-01T12:15:00+00:00
- Closed-by: ceo-copilot-2
- Closeout outbox: `sessions/ceo-copilot-2/outbox/20260601-1215-ceo-load-pf2-closeout.md`
- Status: Complete and archived

This item is complete enough to leave the active CEO queue. Phase 12 and the immediate follow-up closed the generated-faction review surface, approve/reject/merge decisions, campaign subject cleanup/rebind behavior, and richer operator review UX. Remaining PF2 work is quality-of-life follow-on scope, not a blocker for this inbox item.

## Issue

Define and dispatch a first-party Dungeoncrawler workstream for a **PF2e-inspired social relationship system** that models:

- social **attitude**
- scene-based **influence**
- longer-horizon **reputation**
- explicit **loyalty** as a distinct dimension
- expanding circles of trust and obligation where useful
- institutions as first-class social actors that can hold direct relationships, belong to factions, and nest under parent institutions

The Board has explicitly abandoned using other open source projects as active design references for this workstream. This should now be treated as an original Dungeoncrawler design effort grounded in Pathfinder 2e social mechanics plus an added loyalty/trust model that fits Forseti product goals.

## Required outcome

The CEO should convert this into actionable org work by deciding:

1. What the canonical Dungeoncrawler relationship contract should be for:
   - attitude
   - trust
   - loyalty
   - influence hooks
   - reputation tracks
   - faction/community/institution membership and institution hierarchy
2. How this system should relate to:
   - canonical world records
   - NPC runtime state
   - quests/storylines
   - AI GM context assembly
3. What the first release slice should be so the team proves the contract before attempting rich UI.
4. Which follow-on PM, architect, dev, and QA inbox items are needed after planning is complete.

## Acceptance criteria

- A Dungeoncrawler feature brief exists for the PF2e-inspired social relationship and loyalty system.
- The brief distinguishes **trust**, **loyalty**, and **attitude** instead of collapsing them into one score.
- The brief defines how **influence scenes** and **reputation tracks** mutate relationship state.
- The brief explains how campaign runtime records stay separate from library/template defaults.
- The brief identifies the safest first delivery slice and non-goals.
- The work is framed as original Forseti design work, not as an adaptation of Fantasia, Aleph, or any other external product.

## Verification

- CEO inbox item exists with ROI and measurable outcomes.
- Dungeoncrawler backlog includes a new planned feature with acceptance criteria and implementation notes.
- Session plan is updated to reflect the new PF2e + loyalty direction.

## Notes

- PF2e contributes the mechanical posture: attitudes, influence, and reputation-style progression.
- Forseti adds the explicit loyalty/trust dimension and any circle-of-obligation modeling needed for campaign narrative depth.
- This was originally scaffolded against the prior world-codex direction, but the Board has since closed the Fantasia-derived worldbuilding intake as not a good fit. The planning pack now narrows the upstream dependency to a **minimal campaign runtime subject-registry prerequisite** instead of treating the archived materials as active scope authority.

## Current CEO-owned execution state

This thread is now **CEO-owned directly**. The intermediate PM/architect/dev/QA queue items that were created to distribute follow-on planning have been pulled back and archived so this workstream runs from the CEO inbox.

Current planning artifact set:

- `../_archived/20260527-fantasia-archive-worldbuilding-relationship-map/01-dungeoncrawler-worldbuilding-master-plan.md` (archived reference only)
- `../_archived/20260527-fantasia-archive-worldbuilding-relationship-map/02-dungeoncrawler-service-boundary-validation.md` (archived reference only)
- `features/dc-cr-social-relationship-loyalty/feature.md`
- `features/dc-cr-social-relationship-loyalty/01-acceptance-criteria.md`
- `features/dc-cr-social-relationship-loyalty/02-implementation-notes.md`
- `features/dc-cr-social-relationship-loyalty/03-schema-contract.md`
- `features/dc-cr-social-relationship-loyalty/04-transition-and-defaults.md`
- `features/dc-cr-social-relationship-loyalty/05-readiness-matrix.md`
- `features/dc-cr-social-relationship-loyalty/06-relationship-taxonomy.md`
- `features/dc-cr-social-relationship-loyalty/07-worked-scenarios.md`
- `features/dc-cr-social-relationship-loyalty/08-endpoint-contracts.md`
- `features/dc-cr-social-relationship-loyalty/09-subject-id-contract.md`
- `features/dc-cr-social-relationship-loyalty/10-runtime-subject-registry-prerequisite.md`
- `features/dc-cr-social-relationship-loyalty/11-npc-prompt-and-action-integration.md`
- `features/dc-cr-social-relationship-loyalty/12-implementation-closure-matrix.md`
- `features/dc-cr-social-relationship-loyalty/13-institution-seeding-and-backfill-plan.md`
- `features/dc-cr-social-relationship-loyalty/14-actor-creation-field-mapping-contract.md`
- `features/dc-cr-social-relationship-loyalty/15-library-asset-normalization-and-backfill-spec.md`
- `features/dc-cr-social-relationship-loyalty/16-institution-seeding-qa-verification-matrix.md`
- `features/dc-cr-social-relationship-loyalty/17-authoring-admin-workflow-requirements.md`
- `features/dc-cr-social-relationship-loyalty/18-institution-sentiment-mapping-and-character-sheets.md`
- `features/dc-cr-social-relationship-loyalty/19-actor-faction-persistence-and-seeding-contract.md`
- `features/dc-cr-social-relationship-loyalty/20-faction-generation-workflow-and-tool-contract.md`

Current upstream prerequisite:

- `features/dc-cr-social-relationship-loyalty/10-runtime-subject-registry-prerequisite.md`

Archived delegated planning inputs for reference:

- `sessions/pm-dungeoncrawler/inbox/_archived/20260528-world-codex-social-relationship-grooming/`
- `sessions/architect-copilot/inbox/_archived/20260528-world-codex-social-contract-review/`
- `sessions/dev-dungeoncrawler/inbox/_archived/20260528-world-codex-social-discovery/`
- `sessions/qa-dungeoncrawler/inbox/_archived/20260528-world-codex-social-readiness-matrix/`

## Current planning state update

The planning pack now explicitly treats institutions as a **default campaign runtime actor layer** instead of optional flavor data.

Current planning decisions now locked in the pack:

1. campaign creation must seed represented institution subjects for the standard domain set
2. campaign PC creation and campaign NPC creation must populate represented institutional memberships at creation time
3. existing library assets require a non-destructive normalization/backfill pass before campaign backfill is trusted
4. live campaign backfill follows only after library normalization and subject-registry readiness

Current institution-domain posture:

- required launch coverage: ancestry/race, class/profession, settlement/community, government/polity, law/security
- standard conditional domains: family/house, religion/faith, guild/employer, education/order, noble/patron, criminal/covert, culture/tribe
- all of these route through the shared `institution` runtime subject mechanism in the first slice

Current planning pack coverage now includes:

- acceptance criteria for campaign institution bootstrap, immediate PC/NPC institutional population, and library backfill
- a shared normalized institution-bearing input contract for campaign creation, actor creation, and backfill
- creation-flow and migration rules that explicitly reject silent omission of required institutional links
- a dedicated actor creation field-mapping contract that distinguishes rich PC/NPC payloads from thin legacy template rows and identifies the schema-extension requirements for deterministic institution seeding
- a library asset normalization and backfill spec that classifies current source buckets, defines crosswalk/resolver-based normalization, and makes review-queue generation part of the migration plan
- a dedicated QA verification matrix for bootstrap, PC/NPC creation, hierarchy behavior, library backfill, live campaign backfill, and canonical read-path validation
- a dedicated authoring/admin workflow requirements doc covering structured affiliation entry, canonical institution create/select flows, review queue operations, dedupe, preview, and inspection
- a canonical seven-institution sheet set that maps theme-authored sentiment into direct-edge vs reputation-track seeding without inventing new relationship types
- a dedicated actor-faction persistence contract that defines how creation-time memberships, domain-scoped sentiment tracks, unknown-vs-known-neutral state, and mutable-vs-immutable memberships should extend the already-live creation flow
- a dedicated faction generation workflow contract that defines how a GM can move from "this scene needs a new faction" to a validated canonical faction draft, creation, and scene-safe use
- concrete endpoint/schema/admin-contract detail for faction-need capture, dedupe search, draft generation, validation, approval, provenance, and canonical creation
- explicit rule that faction generation happens in the library and campaigns instantiate runtime faction instances from those library assets; NPC generation may attach any library faction

## Current design extension state update

**Design extension complete: theme-authored institution sentiment mapping and first-pass institutional character sheets**

Delivered in `copilot-hq`:

- `features/dc-cr-social-relationship-loyalty/18-institution-sentiment-mapping-and-character-sheets.md`
  - locks the seven canonical institution names and first-slice subject domains
  - maps authored sentiment to the current PF2e-inspired runtime model:
    - `sworn_ally`
    - institution reputation `positive` / `neutral`
    - `rival`
    - `enemy`
  - defines a canonical peer-sentiment matrix
  - builds first-pass institutional character sheets with values, motivators, taboos, affinities, direct edges, and reputation posture

Current design posture after this extension:

1. initial institution posture is authored from theme, not ring adjacency
2. only formal alliances and active hostilities become direct edges by default
3. softer favorable/neutral posture routes through institution reputation tracks
4. these seven sheets are now the canonical content baseline for future seeding/admin work
5. the matrix and sheet-defined relationships are **seed values only** and must never be treated as permanently hard-coded campaign truth

Explicit runtime rule now locked:

- institution/faction relationships may change over the course of a campaign
- seeding docs define starting posture only
- live campaign relationship and reputation rows are authoritative after initialization

## Current implementation state update

**Phase 1 complete: runtime subject-registry groundwork**

Delivered in `dungeoncrawler-content`:

- `src/Service/InstitutionNormalizationService.php`
  - canonical first-slice institution-domain normalization
  - deterministic institution subject-id generation
  - duplicate-prevention label normalization
- `src/Service/CampaignSubjectRegistryService.php`
  - campaign-scoped resolve-or-create registry flow for institution subjects
  - duplicate prevention by canonical domain + normalized label
  - parent/child institution edge creation through the existing runtime relationship graph
- `dungeoncrawler_content.install`
  - new `dc_campaign_subject_registry` schema definition
  - update hook `dungeoncrawler_content_update_10069()`
- `dungeoncrawler_content.services.yml`
  - service registration for institution normalization and campaign subject registry
- `src/Service/RelationshipManagerService.php`
  - public runtime relationship-upsert seam so the subject registry can create hierarchy edges without duplicating relationship persistence logic
- tests:
  - `tests/src/Unit/Service/InstitutionNormalizationServiceTest.php`
  - `tests/src/Unit/Service/CampaignSubjectRegistryServiceTest.php`

Phase 1 intentionally stops at **registry + normalization groundwork**.

Not yet wired in this phase:

- PC creation / NPC creation institutional membership population
- library asset normalization execution
- live campaign backfill
- admin review queue and inspector surfaces

**Phase 2 complete: creation-flow institution membership wiring**

Delivered in `dungeoncrawler-content`:

- `src/Service/InstitutionMembershipService.php`
  - shared deterministic ancestry + profession membership synchronization for campaign runtime actors
  - canonical institution resolution through the new campaign subject registry
  - membership persistence through `dc_campaign_relationships` as `institution_member` edges
  - stale membership cleanup for the actor when the deterministic source set changes
- `src/Controller/CampaignController.php`
  - wires campaign PC selection/instantiation to attach campaign-scoped institution memberships when the canonical character is cloned into the campaign runtime row
- `src/Service/CharacterCreationGmService.php`
  - wires campaign-scoped GM draft saves to attach institution memberships once the character reaches completed creation state
- `src/Service/NpcService.php`
  - wires campaign NPC creation to attach deterministic institution memberships at create time
- `dungeoncrawler_content.services.yml`
  - registers the shared institution membership service and injects it into the relevant creation surfaces
- tests:
  - `tests/src/Unit/Service/InstitutionMembershipServiceTest.php`

Current first-slice creation coverage now implemented:

1. campaign PCs attach represented ancestry + class/profession institutions when they become campaign runtime actors
2. campaign NPCs attach represented ancestry + profession institutions at creation time
3. all actor memberships reuse the existing campaign relationship graph instead of introducing a second membership table

Implementation gap now explicitly confirmed:

1. current creation wiring only covers deterministic institution membership attachment
2. it does **not** yet seed actor-held faction sentiment or reputation rows
3. it does **not** yet distinguish unknown-neutral from known-neutral
4. it does **not** yet persist mutable-vs-immutable membership posture
5. it does **not** yet generate domain-scoped peer sentiment state for political, class, or ancestry factions

Current documentation expansion now also covers:

1. the structured path from narrative need detection to faction generation
2. dedupe/search requirements before a new faction may be created
3. GM-authorized draft creation for campaign factions with explicit characteristic constraints
4. scene-safe faction presentation through proxies rather than prose-only canon

Still not yet wired after Phase 2:

- library asset normalization execution / backfill tooling
- live campaign backfill for pre-existing records
- admin review queue and inspector surfaces
- actor faction sentiment/reputation seeding and membership mutability

**Phase 3 complete: library normalization manifest and review queue**

Delivered in `dungeoncrawler-content`:

- `src/Service/LibraryInstitutionBackfillService.php`
  - inventories packaged character-template library rows from the authoritative template files
  - classifies rows into social actor, context-only, and skip buckets
  - reuses the live deterministic ancestry/profession extraction rules for staged normalization
  - writes a staged manifest plus explicit review rows instead of guessing through unresolved cases
- `src/Commands/InstitutionBackfillCommands.php`
  - adds a Drush entrypoint for rebuilding the staged library manifest
- `dungeoncrawler_content.install`
  - adds `dc_library_institution_manifest`
  - adds `dc_library_institution_review`
  - adds update hook `dungeoncrawler_content_update_10070()`
- `dungeoncrawler_content.services.yml`
  - registers the library backfill service
- `drush.services.yml`
  - registers the new library-backfill Drush command
- tests:
  - `tests/src/Unit/Service/LibraryInstitutionBackfillServiceTest.php`

Current Phase 3 posture:

1. packaged library actor rows now have a deterministic staged backfill surface
2. ambiguous rows like `quest_giver` / `mentor` / unresolved location refs are routed into review, not guessed
3. non-social or encounter-only rows are explicitly classified out of first-pass institutional normalization

Still not yet wired after Phase 3:

- live campaign backfill for pre-existing runtime actors
- admin review queue and inspector surfaces

**Phase 4 complete: live campaign runtime backfill**

Delivered in `dungeoncrawler-content`:

- `src/Service/CampaignInstitutionBackfillService.php`
  - scans existing `dc_campaign_characters` PC/NPC runtime rows for campaigns already in flight
  - backfills deterministic institution memberships using the same shared normalization rules as live creation
  - writes explicit review rows when runtime actors are missing ancestry or have ambiguous profession labels
  - avoids guessing through unresolved runtime actor data
- `src/Commands/InstitutionBackfillCommands.php`
  - adds a Drush entrypoint for live campaign backfill
- `dungeoncrawler_content.install`
  - adds `dc_campaign_institution_backfill_review`
  - adds update hook `dungeoncrawler_content_update_10071()`
- tests:
  - `tests/src/Unit/Service/CampaignInstitutionBackfillServiceTest.php`

Current Phase 4 posture:

1. existing campaign PCs and generated NPC runtime actors can now be backfilled from deterministic ancestry/profession data
2. unresolved runtime actors enter an explicit review queue instead of receiving guessed institution memberships
3. the same institution extraction rules are now reused across live creation, library staging, and live campaign backfill

Still not yet wired after Phase 4:

- admin review queue and inspector surfaces

**Phase 5 complete: admin review queue and inspector surface**

Delivered in `dungeoncrawler-content`:

- `src/Controller/InstitutionReviewBrowserController.php`
  - read-only admin/operator browser for unresolved institution review rows
  - separate queue sections for packaged library rows and live campaign runtime actors
  - inline JSON inspector for normalized payload and actor/library review context
  - filter support for status, reason, search term, and campaign id
- `dungeoncrawler_content.routing.yml`
  - adds `/admin/content/dungeoncrawler/institution-reviews`
- `dungeoncrawler_content.links.menu.yml`
  - adds dashboard/admin menu entries for the institution review queue

Current Phase 5 posture:

1. unresolved institution normalization rows are now visible to operators in Drupal admin
2. both staged library backfill review rows and live campaign backfill review rows have an inspection surface
3. the first institutional-management implementation slice now covers runtime creation, library staging, live campaign backfill, and operator review visibility

**Post-implementation tightening complete**

Follow-on review and refactor work has now tightened the delivered institution slice across the touched implementation files.

Stabilization changes completed:

- `src/Controller/CampaignController.php`
  - removes the redundant reread of the newly created campaign runtime character row
  - now syncs campaign PC institution memberships directly from the in-scope runtime payload and instance id
- `src/Service/NpcService.php`
  - now requires `InstitutionMembershipService` as a hard dependency instead of treating institution sync as optional
- `src/Service/InstitutionMembershipService.php`
  - preserves the true `source_scope` for relationship metadata (`character_creation`, `npc_creation`, `library_backfill`, `campaign_backfill`)
  - falls back from `species` to ancestry when deriving deterministic memberships
- `src/Service/LibraryInstitutionBackfillService.php`
  - processes template files in deterministic order
  - uses stable fallback source ids when malformed library rows lack both `instance_id` and `character_id`
- `src/Service/CampaignInstitutionBackfillService.php`
  - safely falls back to top-level runtime ancestry/class fields when JSON payload fields are empty strings
  - persists campaign review rows even when `instance_id` is missing by using a deterministic fallback identifier
- tests
  - focused institution unit coverage was updated to lock in the tightened behavior

Current implementation state after tightening:

1. creation-time institution sync is now fail-fast on misconfiguration instead of silently degrading
2. staged and live backfill review rows are more durable for malformed legacy data
3. metadata emitted by institution sync now reflects the real source path, improving auditability for later review-resolution work

**Phase 6 complete: structured review-resolution workflow**

Delivered in `dungeoncrawler-content`:

- `src/Service/InstitutionReviewDecisionService.php`
  - validates and persists structured review decisions for both library and campaign institution review queues
  - enforces explicit status/action contracts (`open`, `resolved`, `deferred`) rather than free-form operator state
- `src/Form/InstitutionReviewResolutionForm.php`
  - adds an admin decision form for review rows
  - supports reopen, defer, map-to-existing, create-institution, and intentional-blank decisions
  - captures structured decision summary, target identifier, canonical domain/label, and operator note
- `src/Controller/InstitutionReviewBrowserController.php`
  - now surfaces decision summaries and review/update actions directly from the queue browser
  - warns operators when the pending schema update for structured decisions has not yet been applied
- `dungeoncrawler_content.install`
  - extends both institution review tables with structured decision fields
  - adds update hook `dungeoncrawler_content_update_10072()`
- `dungeoncrawler_content.routing.yml`
  - adds the institution review resolution form route
- tests:
  - `tests/src/Unit/Service/InstitutionReviewDecisionServiceTest.php`

Current Phase 6 posture:

1. institution review rows now support explicit operator decisions instead of remaining permanently read-only
2. review queue decisions are recorded in structured fields suitable for later automation and audit
3. the queue browser now acts as an actionable workflow surface instead of only an inspection page

**Environment activation note**

The pending `dungeoncrawler_content` update chain has now been executed in the local Drupal environment, so the institution review/backfill/decision schema for `10070`, `10071`, and `10072` is live locally.

**Operational backup checkpoint completed**

Before proceeding with further schema/debug work, current Drupal backup posture was verified, the live update chain was applied, and fresh ad hoc backups were revalidated after cleanup.

Verified backup state:

1. scheduled Drupal `backup_migrate` backups are present in `/var/private/dungeoncrawler/backup_migrate`
2. current scheduled inventory count: `22` database backups
3. latest scheduled backup verified: `/var/private/dungeoncrawler/backup_migrate/backup-2026-05-29T00-00-25.mysql.gz`
4. scheduled backup artifact passed gzip verification
5. manual `backup_migrate:quick_backup` now completes successfully on a clean database
6. latest verified manual Drush dump: `/var/private/dungeoncrawler/drush-backups/dungeoncrawler-20260529T194746Z-stable.sql.gz`
7. latest verified Drush dump artifact passed gzip verification
8. current ad hoc Drush inventory count: `5` database dumps

Backup remediation completed:

- orphaned Drupal `test*` tables were cleared from the live database after identifying them as the source of ad hoc backup failures
- a subsequent clean Drush SQL dump completed successfully with no live test tables present
- `backup_migrate:quick_backup` was rerun afterward and completed successfully

**Registry import remediation completed**

The feat-registry import defect exposed during the update run has been fixed and re-applied locally.

Delivered:

1. `dungeoncrawler_content.install`
   - widens `dungeoncrawler_content_registry.version` from `varchar(20)` to `varchar(128)`
   - adds `dungeoncrawler_content_update_10073()` to apply the schema correction durably
2. `tests/src/Unit/Service/ContentRegistryTest.php`
   - locks in preservation of the long packaged feat version string
3. local Drupal state
   - update `10073` has been executed successfully

**Phase 7 complete: library-backed faction attachment path**

Delivered in `dungeoncrawler-content`:

- `src/Service/InstitutionNormalizationService.php`
  - now treats `faction`, `allegiance`, and generic `institution` inputs as canonical `allegiance` domain values for the actor-creation path
- `src/Service/CampaignSubjectRegistryService.php`
  - now reuses campaign institution subjects by `source_asset_type` + `source_asset_id` when actor creation is instantiating from canonical library faction assets
- `src/Service/InstitutionMembershipService.php`
  - adds structured `faction_refs` support alongside the existing explicit campaign subject refs
  - accepts library-backed faction selections during PC/NPC institution sync and instantiates campaign institution subjects through the existing registry seam
  - writes source-asset provenance into the resulting `institution_member` relationship state for downstream audit and sentiment seeding work
- tests:
  - `tests/src/Unit/Service/InstitutionNormalizationServiceTest.php`
  - `tests/src/Unit/Service/CampaignSubjectRegistryServiceTest.php`
  - `tests/src/Unit/Service/InstitutionMembershipServiceTest.php`

Current Phase 7 posture:

1. actor creation can now consume canonical library faction refs without requiring a pre-existing campaign subject id
2. campaign-runtime institution subjects preserve source-library provenance when instantiated from canonical faction assets
3. the next faction slice can build on a live create/select contract instead of inventing a second campaign-only faction membership path

Phase 7 review/refactor hardening:

1. existing campaign institution subjects now keep their stable `subject_id` and stored source-asset provenance when reused
2. institution parent updates now clear stale `institution_parent` edges before writing the current parent
3. asset-backed structured affiliation refs now enforce their expected domain contract after subject resolution

Still not yet wired after Phase 7:

- actor-held faction sentiment/reputation seeding
- unknown-vs-known neutral tracking
- mutable-vs-immutable faction membership posture
- richer authoring UI that exposes `faction_refs` directly in the GM flows
   - canonical feat import reran cleanly
   - current feat registry population: `470` rows
   - longest imported feat version length now stored successfully: `30`

**Phase 7 complete: approved review-decision application**

Delivered in `dungeoncrawler-content`:

- `src/Service/InstitutionReviewApplicationService.php`
  - applies resolved institution review decisions back into the underlying source state instead of leaving them as queue-only records
  - writes persisted `institution_review_overrides` into packaged library actor state and campaign runtime actor state
  - refreshes the affected library manifest row or campaign runtime actor immediately after apply
- `src/Form/InstitutionReviewResolutionForm.php`
  - now auto-applies resolved review decisions immediately after they are saved
- `src/Service/LibraryInstitutionBackfillService.php`
  - now honors persisted review overrides during staged library normalization analysis
  - adds targeted single-row refresh support for a resolved library source asset
  - preserves resolved/deferred review rows as audit history instead of deleting them on refresh
- `src/Service/CampaignInstitutionBackfillService.php`
  - now honors persisted review overrides during live runtime backfill analysis
  - adds targeted single-actor refresh support after a resolved campaign decision
  - preserves resolved/deferred review rows as audit history instead of deleting them on refresh
- tests:
  - `tests/src/Unit/Service/LibraryInstitutionBackfillServiceTest.php`
  - `tests/src/Unit/Service/CampaignInstitutionBackfillServiceTest.php`
  - `tests/src/Unit/Service/InstitutionReviewDecisionServiceTest.php`

Current Phase 7 posture:

1. approved institution review decisions now mutate library or campaign source state instead of remaining advisory only
2. rerunning staged/library or live/campaign backfill now respects persisted operator decisions and does not reopen the same ambiguity by default
3. resolved and deferred review rows are retained as workflow/audit history while stale open rows are cleared when the underlying issue is no longer active

**Phase 8 complete: character-wizard structured affiliation entry**

Delivered in `dungeoncrawler-content`:

- `src/Form/CharacterCreationStepForm.php`
  - adds campaign-only structured institution affiliation pickers to the live character-creation wizard on step 6, the existing alignment/deity/social identity surface
  - stores canonical campaign subject ids directly in `character_data` for settlement, government, security, family, religion, employer, order, noble, criminal, and culture affiliations
  - validates submitted subject ids against the campaign subject registry and expected institution domains instead of accepting freeform or mismatched refs
- `src/Service/InstitutionMembershipService.php`
  - now consumes explicit structured affiliation refs from character payloads alongside existing ancestry/class inference
  - syncs explicit memberships directly to the referenced campaign subject ids instead of re-resolving by display name
- `src/Service/CampaignSubjectRegistryService.php`
  - adds direct lookup support for existing institution subjects by stable campaign subject id
- tests:
  - `tests/src/Unit/Form/CharacterCreationStepFormTest.php`
  - `tests/src/Unit/Service/InstitutionMembershipServiceTest.php`
  - `tests/src/Unit/Service/CampaignSubjectRegistryServiceTest.php`

Current Phase 8 posture:

1. campaign PC authoring now has a real structured affiliation entry surface on the live wizard instead of relying on prose drift
2. explicit institution refs bind to existing campaign subject ids deterministically during membership sync
3. existing ancestry/class inference remains intact for the first-slice automatic coverage already delivered

**Phase 9 complete: actor-held faction sentiment + posture seeding**

Delivered in `dungeoncrawler-content`:

- `src/Service/InstitutionMembershipService.php`
  - classifies synced memberships into `identity`, `vocation`, and `allegiance` with `immutable`, `sticky`, and `mutable` posture on the runtime `institution_member` edges
  - seeds domain-scoped `institution_sentiment` runtime edges for ancestry, class/profession, and political/allegiance subjects during actor creation
  - seeds canonical ancestry/class peers plus currently known political peers without inventing a second storage system outside `dc_campaign_relationships`
  - reconciles untouched seeded sentiment edges when memberships change instead of freezing old neutral/self defaults forever
  - prevalidates asset-backed domain mismatches before any campaign subject registry write occurs
- `src/Service/CampaignSubjectRegistryService.php`
  - preserves existing `entity_ref`, `status`, and merged `metadata_json` when an existing subject row is reused from sparse peer-seeding inputs
- tests:
  - `tests/src/Unit/Service/InstitutionMembershipServiceTest.php`
  - `tests/src/Unit/Service/CampaignSubjectRegistryServiceTest.php`
  - `tests/src/Unit/Service/InstitutionNormalizationServiceTest.php`

Current Phase 9 posture:

1. every actor membership created through the current sync path now carries explicit mutability/domain posture
2. actor creation now writes first-pass runtime faction sentiment state directly into `dc_campaign_relationships`
3. seeded sentiment rows now upgrade/downgrade with membership changes when they are still untouched system-seeded defaults, while preserving the path for later runtime mutations to diverge

Still not yet wired after Phase 9:

- richer distinction and UX around unknown vs known-neutral beyond the current seeded `status` + `knowledge_state`
- broader faction-to-faction or institution-to-institution sentiment mutation tooling beyond actor-held defaults
- richer NPC/GM authoring surfaces that expose faction selection and sentiment editing directly

**Phase 10 complete: runtime mutation contract for memberships + actor sentiment**

Delivered in `dungeoncrawler-content`:

- `src/Service/InstitutionMembershipService.php`
  - adds explicit `mutateInstitutionSentiment()` so campaign events or GM actions can change actor-held faction posture without relying on seed-time defaults
  - adds explicit `mutateInstitutionMembership()` so mutable memberships can be abandoned or reactivated while immutable memberships remain locked and sticky memberships require explicit override
  - marks both `institution_sentiment` and `institution_member` edges with explicit `mutation_state` / `mutation_count` / `touched_at` metadata
  - updates sync/reconciliation so touched membership/sentiment edges are preserved instead of being silently flattened back to seeded defaults during later resync
  - adds `listActorInstitutionSentiments()` so runtime/query consumers can distinguish `known-neutral` from `unknown-neutral` directly instead of inferring only from score `0`
- tests:
  - `tests/src/Unit/Service/InstitutionMembershipServiceTest.php`
    - covers sentiment mutation
    - covers mutable membership abandonment
    - rejects immutable membership mutation
    - preserves touched membership and sentiment edges during later sync
    - exposes known-neutral vs unknown-neutral classification

Current Phase 10 posture:

1. creation-time seeds are now clearly separated from later campaign history
2. runtime mutation is explicit for both faction sentiment and mutable memberships
3. neutral score rows now have a first concrete runtime/query surface that distinguishes known-neutral from unknown-neutral

Still not yet wired after Phase 10:

- broader neutral-knowledge surfacing outside the new service/query helper
- executable narrative-need -> canonical library faction generation flow
- richer NPC/GM/character authoring surfaces for faction generation, selection, and editing

**Phase 11 complete: executable narrative-need -> canonical library faction generation from the character wizard**

Delivered in `dungeoncrawler-content`:

- `src/Service/FactionGenerationService.php`
  - validates a structured faction-generation request from narrative need
  - generates a deterministic canonical faction draft with stable slug, seed profile key, and membership model
  - searches for an existing canonical faction by stable slug before create
  - writes/reuses a canonical generated-faction row in `dc_library_institution_manifest`
  - instantiates the campaign subject through `CampaignSubjectRegistryService` with `source_asset_type = library_faction`
- `src/Form/CharacterCreationStepForm.php`
  - adds `faction_refs` to the structured affiliation contract
  - captures faction-generation inputs directly in the wizard: role in story, why existing factions are insufficient, public face, hidden face, ideology tags, method tags, and membership style
  - validates those fields before canonical faction creation proceeds
  - routes faction creation through `FactionGenerationService` instead of directly creating a campaign-only subject row
- `src/Service/CharacterCreationGmService.php`
  - allows GM-assisted draft updates to submit faction-generation helper fields
  - resolves those helper fields into canonical library-backed `faction_refs` during campaign draft saves
- `src/Service/NpcService.php`
  - accepts structured `faction_create_requests` payloads for NPC authoring
  - resolves those requests into canonical library-backed `faction_refs` before NPC membership sync runs
- `src/Controller/InstitutionReviewBrowserController.php`
  - exposes generated faction manifest rows in the existing review browser so admins can inspect canonical faction payloads and provenance
- `dungeoncrawler_content.services.yml`
  - registers the new faction-generation service
- tests:
  - `tests/src/Unit/Service/FactionGenerationServiceTest.php`
    - covers request validation
    - covers deterministic draft generation
    - covers reuse-existing behavior
    - covers create-new behavior

Current Phase 11 posture:

1. the narrative-need -> canonical faction generation path is now executable in code
2. canonical faction creation is now library-backed through `dc_library_institution_manifest`, not direct campaign-only subject creation
3. character and NPC authoring now use the same hard contract instead of a label-only shortcut

Still not yet wired after Phase 11:

- GM/admin review and approval surfaces for ambiguous reuse-vs-create decisions
- deeper inspector and approval surfaces around generated faction drafts, manifest rows, and downstream campaign instantiation

Next likely slice:

- add review/inspector surfaces for generated library factions and ambiguous duplicate/reuse decisions
- wire admin-facing approval and audit views around generated faction manifest rows

## Highest-value planning/requirements docs still worth authoring before implementation

The planning pack now covers the highest-value pre-implementation requirements surfaces for this slice.

## CEO next actions

1. Continue social-system planning and implementation routing from the CEO queue only.
2. Treat the minimal campaign runtime subject-registry prerequisite as the active upstream contract for the first social slice.
3. Next implementation expansion should focus on canonical institution create/select tightening and NPC authoring parity, not on new fallback heuristics.
4. Keep unresolved campaign rows review-driven rather than creating guessed institutional memberships.
5. Use the authoring/admin workflow doc as the operational contract for later mutation/dedupe/resolution surfaces.
6. Next operational expansion should move into library normalization/backfill, richer NPC authoring UI surfaces, and dedupe/merge administration on top of the now-live create/select decision-application path.

**Phase 9 complete: campaign NPC structured affiliation parity**

Delivered in `dungeoncrawler-content`:

- `src/Service/InstitutionMembershipService.php`
  - extends the same structured institution affiliation-ref contract already used by campaign character creation onto campaign NPC payloads
  - now honors explicit canonical subject ids for settlement, government, security, family, religion, employer, order, noble, criminal, and culture affiliations during NPC institution sync
  - keeps ancestry/profession inference active alongside explicit refs instead of replacing the existing deterministic first-slice coverage
- tests:
  - `tests/src/Unit/Service/InstitutionMembershipServiceTest.php`

Current Phase 9 posture:

1. campaign NPC creation can now attach explicit structured institution memberships using the same stable campaign subject ids as the character wizard
2. domain validation remains strict because explicit NPC refs still resolve through the canonical campaign subject registry
3. the first-slice institution contract now covers both campaign characters and campaign NPC creation paths with one shared affiliation-ref shape

Next likely slice:

- tighten canonical institution create/select UX for campaigns that still lack the needed registry rows at authoring time
- expose the same structured affiliation pickers on richer NPC authoring UI surfaces once the current payload contract has proven stable

**Phase 10 complete: canonical institution create/select tightening in character creation**

Delivered in `dungeoncrawler-content`:

- `src/Form/CharacterCreationStepForm.php`
  - step 6 structured affiliation pickers now support explicit in-flow canonical institution creation for campaign-scoped affiliations instead of forcing pre-seeded registry rows
  - creation requires canonical labels, supports optional parent institution and provenance note capture, and persists only resolved campaign subject ids back into character data
  - duplicate posture is strict: exact canonical matches are blocked, likely near matches require explicit operator confirmation, and invalid parent selections are rejected
- tests:
  - `tests/src/Unit/Form/CharacterCreationStepFormTest.php`

Current Phase 10 posture:

1. campaign character creation now supports the required create-or-select institution workflow inside the wizard for represented affiliation domains
2. the wizard remains strict about canonical ids as the stored contract even when the operator creates a new institution mid-flow
3. duplicate prevention now happens at authoring time instead of relying on later cleanup or silent normalization

**Phase 11 complete: institution sentiment mapping and character sheets**

Delivered in `copilot-hq`:

- `features/dc-cr-social-relationship-loyalty/18-institution-sentiment-mapping-and-character-sheets.md`
  - locks seven canonical institutions: Crown, Commonweal, Compact, Wildwood Covenant, Shadow Syndicate, Forge Assembly, Twilight Church
  - maps peer-sentiment matrix from theme-authored posture
  - defines concrete relationships: formal alliances (Crown ↔ Church, Compact ↔ Forge), enmities (Crown ↔ Syndicate), rivalries
  - establishes implementation path for seeding admin UI

Current Phase 11 posture:

1. canonical seven-institution baseline is now locked with formal sentiment mapping
2. theme-authored starting posture replaces circle-ring adjacency as the source of truth
3. formal alliances and hostilities become direct edges; favorable/neutral routes through reputation tracks
4. institutional seeding docs now act as canonical content baseline for actor creation work

**Phase 12 complete: actor faction persistence and seeding contract**

Delivered in `copilot-hq`:

- `features/dc-cr-social-relationship-loyalty/19-actor-faction-persistence-and-seeding-contract.md`
  - extends schema to support domain-scoped sentiment/reputation tracks for political/social, class, ancestry, and campaign domains
  - introduces knowledge-state distinction: unknown-neutral vs known-neutral
  - defines mutable vs immutable membership posture with clear mutation rules
  - locks creation-time seeding rules for all actor types
  - specifies integration points for character creation, NPC creation, and campaign initialization
  - maps to dungeoncrawler schema via new `dc_actor_sentiments` and extended `dc_actor_memberships` tables

Current Phase 12 posture:

1. actor-held sentiment now has an explicit storage and seeding contract ready for implementation
2. membership edges can be classified as mutable/immutable with explicit mutation semantics
3. unknown-vs-known neutral state is now part of the model instead of being inferred from raw score
4. creation-time seeding rules are explicit and deterministic for all actor types

Next likely slice:

- dispatch implementation work to add actor sentiment seeding to character creation, NPC creation, and admin UI
- wire mutation paths for mutable allegiances and permanent identity memberships
- add richer NPC authoring UI surfaces that expose faction sentiment and membership controls

**Phase 12 complete: generated faction review + approval surface**

Delivered in `dungeoncrawler-content`:

- `src/Service/FactionGenerationService.php`
  - adds near-match detection using meaningful slug token overlap (4+ char tokens)
  - flags newly generated factions as `pending_review` when near-matches exist
  - writes a `dc_library_institution_review` row with `review_reason = 'near_match_detected'` carrying near-match candidates in `details_json`
  - exposes `MANIFEST_STATUS`, `MANIFEST_PENDING_STATUS`, `NEAR_MATCH_REVIEW_REASON`, `MANIFEST_SOURCE_TABLE`, `MANIFEST_SOURCE_FILE` as public constants for downstream consumers
- `src/Service/InstitutionReviewDecisionService.php`
  - adds `approve_faction`, `reject_faction`, `merge_with_existing` to the `STATUS_RESOLVED` allowed action set
  - enforces `target_identifier` for `merge_with_existing` (same contract as `map_existing`)
- `src/Service/InstitutionReviewApplicationService.php`
  - detects generated-faction review rows by virtual source file path
  - routes to `applyGeneratedFactionDecision()` which updates `dc_library_institution_manifest.status` to `normalized`, `rejected`, or `merged` without attempting to read a real packaged JSON file
- `src/Controller/InstitutionReviewBrowserController.php`
  - left-joins open near-match review rows into the generated faction query
  - surfaces near-match candidates in a collapsible cell per row
  - adds a per-row Review action link for `pending_review` manifest rows routed through the existing `dungeoncrawler_content.institution_review_resolution` route
- tests:
  - `tests/src/Unit/Service/FactionGenerationServiceTest.php` — 2 new cases: pending_review on near-match, direct normalized on no near-match
  - `tests/src/Unit/Service/InstitutionReviewDecisionServiceTest.php` — 4 new cases: approve_faction, reject_faction, merge_with_existing requires target_identifier, merge_with_existing accepts target_identifier

Current Phase 12 posture:

1. generated faction creation now has an explicit ambiguity gate — near-matches surface for operator decision before the manifest reaches `normalized`
2. no generated faction is silently treated as distinct if it shares meaningful label tokens with an existing one
3. the review browser is now an active decision surface for generated factions, not just a read-only manifest viewer
4. approve/reject/merge decisions are fully wired through the existing review resolution form and application service

Still not yet wired after Phase 12:

- GM-facing faction inspector UX that previews the full draft payload alongside near-match candidates during review
- admin bulk-approval path for large faction generation sessions
- downstream cascade when a faction is rejected: campaign subject cleanup or rebind to target

---

## Phase 12 follow-up — campaign subject cleanup + richer review form UX

**Completed in session c483fc8d / commit fa1a5b0**

### Campaign subject cleanup (data integrity)

`InstitutionReviewApplicationService::applyGeneratedFactionDecision()` now handles downstream subject lifecycle for all three generated-faction actions:

| Action | Manifest status | Campaign subjects |
|---|---|---|
| `approve_faction` | `normalized` | No change (remain active) |
| `reject_faction` | `rejected` | All `active` subjects with matching `source_asset_type = 'library_faction'` + `source_asset_id = slug` → `status = 'orphaned'` |
| `merge_with_existing` | `merged` | Same subjects have `source_asset_id` rewritten to `target_identifier` |

Two new protected helpers: `orphanGeneratedFactionSubjects()`, `rebindGeneratedFactionSubjects()`.

### Richer inspector UX in resolution form

`InstitutionReviewResolutionForm`:
- `isGeneratedFactionRow()` — detects generated faction rows by `source_file` or `review_reason`
- `buildDetailsMarkup()` — routes to rich inspector for generated faction rows (was always generic JSON)
- `buildFactionDraftMarkup()` — renders faction draft characteristics (label, public/hidden face, ideology/method tags, story role) + near-match candidates (slug, shared tokens) side-by-side; operator now sees full context before making a decision
- `buildActionOptions(?array $row)` — appends `approve_faction`, `reject_faction`, `merge_with_existing` options only for generated faction rows
- `validateForm()` — `merge_with_existing` now requires `target_identifier` (same contract as `map_existing`)
- `details` fieldset opens automatically for generated faction rows

`FactionGenerationService::createNearMatchReviewItem()` — updated `details_json` shape from flat `{canonical_label, near_matches}` to structured `{draft: {...}, near_match_candidates: [{slug, label, shared_tokens}]}`.

### Test coverage

`tests/src/Unit/Service/InstitutionReviewApplicationServiceTest.php` (NEW — 5 cases):
- `testRejectFactionOrphansActiveCampaignSubjects`
- `testMergeWithExistingRebindsActiveCampaignSubjects`
- `testApproveFactionDoesNotTouchCampaignSubjects`
- `testMergeWithExistingWithoutTargetIdentifierSkipsRebind`
- `testUnsupportedActionThrows`

All 52/52 social unit tests pass.

### Remaining open items (all optional follow-on)

- Admin bulk-approval path for large faction generation sessions
- Operator-facing "faction inbox" that shows only `pending_review` factions separately from the generic institution review browser
