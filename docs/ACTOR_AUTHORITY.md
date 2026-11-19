# Actor authority reconciliation

Work item **DC-ACTOR-AUTHORITY-20261114**
([issue #2](https://github.com/Forseti-Life/dungeoncrawler-content/issues/2)).
Progress is tracked in the issue; this file describes the durable contract and
tooling.

## Target

One command authority for every actor mutation, whatever its origin:
Character Setup, Storyline Builder, Definition Editor, manual forms, gameplay
GM tools, deterministic rule handlers, imports and jobs.

- `CharacterManager` becomes the typed command facade.
- `StateValidationService` is the validation entry.
- `ActorStateService` -> `CharacterStateService` stays the effective-state read.
- Player characters, NPCs and creatures are one actor type with one schema,
  rules definition, identity namespace and lifecycle.
- Per-campaign actor state belongs to the campaign instance of the actor.
  A template never holds it, and editing a template never resets it.
- Each fact has exactly one physical home. No backwards-compatible aliases,
  fallback reads, dual writers or read-time repair.

## Creatures

A creature is a canonical actor definition of type `npc` whose
`state_data.class` is exactly `creature`. What kind of creature it is lives in
`state_data.species`. The code has no creature definition family, schema,
catalog or generation tool; a placement with `family: creature` fails like any
unknown family.

- `CanonicalDefinitionService::isCreature($actor_id)` is the one place the
  rule is evaluated. It throws `definition_missing:actor:<id>` for an actor
  definition that does not exist.
- Room contents keep their buckets. `CanonicalRoomProjectionService` and
  `RoomEditorService` put an `actor` placement in `creatures` when
  `isCreature()` is true and in `npcs` otherwise.
- Encounters select `publishedDefinitions('actor', ['class' => 'creature',
  ...])`. An actor's selection tags are its `traits` and
  `spawn.encounter_themes`.
- `generate_npc_definition` with `class: creature` generates a creature: the
  same actor definition, with a required stat block and no NPC sheet defaults.

The data migration that moves the stored creature definitions and repoints
room placements is separate; its plan, measurements and open decisions are in
[CREATURE_FOLD_PLAN.md](CREATURE_FOLD_PLAN.md).

## Current state: S0 (inventory and contract freeze)

Nothing in the runtime has changed yet. The writer set is frozen so it cannot
grow while it is migrated.

| Artifact | Purpose |
|---|---|
| `tools/actor-authority/ActorAuthorityScanner.php` | Read-only source scanner. Classifies literal and constant table access by ownership lane, and reports dynamic table writes and raw SQL as review obligations rather than omitting them. |
| `tools/actor-authority/inventory.php` | CLI wrapper. `php tools/actor-authority/inventory.php --findings [label=/abs/root ...]` |
| `tools/actor-authority/writer-register.json` | Reviewed register of every production write site, with a disposition for each unprovable site and a migration slice per file. |
| `tools/actor-authority/rules-disparity-ledger.json` | Field and rule differences between the character, canonical actor and creature schemas, hot columns and validators, each with its target rule. Also records the read-only data preflight. |
| `tests/src/Unit/Service/ActorWriteAuthorityGuardTest.php` | Fails when the source and the register disagree, or when a site lacks a reviewed disposition. |

### Changing an actor writer

A new direct write to an actor store, a new call to a generic mutation method,
or a new dynamic table write fails the guard test. Route the change through the
actor authority. If a writer is removed as part of a migration slice, remove it
from the register in the same change:

```bash
cd /var/www/html/dungeoncrawler
./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_content/phpunit.xml \
  web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/ActorWriteAuthorityGuardTest.php
```

### Scanner limits

The scan proves literal and same-file constant table names only. It does not
follow variables across methods, service aliases, or SQL assembled at runtime.
Those sites appear as `dynamic_*` findings and need a reviewed disposition.
`tests/` is inventoried but not frozen; test fixture writers migrate in S6.

## Decisions recorded in the ledger

- **D22** - the campaign-instance row is the single home for instance state:
  `character_data` is the pinned actor document, `state_data` is one typed
  instance-state document, and the row placement columns are the one placement
  record. `dc_campaign_actor_runtime_state` stops owning actor facts.
- **D20** - party definitions become group templates, not actors.
- `tools/actor-authority/field-map.json` assigns each of 195 observed or
  schema-declared top-level keys to exactly one owner. An unmapped key fails
  the guard test.

## S1 (contract core, in progress)

| Artifact | Purpose |
|---|---|
| `config/schemas/actor.schema.json` | The single actor document contract. `build.mechanics_model` is `pc_build` or `authored_statblock`; taxonomy never selects a schema. No runtime state, inventory, conditions or quest data. |
| `src/Service/Validation/ActorDocumentValidator.php` | Schema plus rule checks under the `actor_authoring` and `actor_publication` profiles. Reports each check as passed, failed, not_checked, not_applicable or dependency_blocked; a required unchecked check never yields valid or ready. |
| `StateValidationService::validateActor()` | The validation entry. |
| `src/Service/Validation/ActorReferenceCatalogInterface.php` | Exact local catalog lookups for classes, ancestries, feats, spells, items and skills. |

| `src/Service/ActorCatalogService.php` | The single local catalog authority (ancestry, heritage, background, class, feat, spell, equipment, familiar ability, item, skill). The character GM's catalog tools delegate to it; their previous private copy was removed. |

| `src/Service/ActorDocumentConverter.php` | Pure one-time conversion of a stored actor row into the actor document and the campaign-instance state document. Unmappable values are reported as blocking issues, never guessed. |
| `tools/actor-authority/conversion-preflight.php` | Read-only full-table conversion and validation report (`drush php:script`). |

Applied to the live database (update 10225): every actor row has a unique,
required UUID allocated by the database; `level` is signed; the
`dc_actor_revisions` and `dc_actor_command_receipts` tables exist.

Preflight on 2026-11-14: all 955 rows convert and validate for save. Reviewed
one-time repairs of stored values are listed in the ledger under
`conversion_preflight.reviewed_repairs`. Spell lists stored without a tradition
or casting type convert as drafts and stay unpublishable until authored.
Identify is the Identify Magic skill activity, not a spell; a stored "Identify"
spell entry is dropped.

Spell damage that adds the caster's spellcasting ability modifier declares
`"ability_modifier": "spellcasting"` beside an executable dice formula. The
resolver adds the modifier from the caster's canonical state and fails
explicitly when the caster records none.

A familiar is its own actor (`identity.kind` = `companion`); the owner's
`build.companions` records the selection that links to it.

## Commands (live)

`CharacterManager::executeActorCommand()` and `previewActorCommand()` are the
actor write entry. A command follows `config/schemas/actor_command.schema.json`
(`actor-command-v1`) and is executed by
`src/Service/Actor/ActorCommandProcessor.php`: lock the actor, re-read it,
apply only the requested operations, validate the whole candidate, write, bump
the aggregate revision (`version`), append `dc_actor_revisions`, store the
receipt in `dc_actor_command_receipts`, and verify the stored row reads back
as the validated candidate. A failure returns a typed code with
`committed: false` and changes nothing.

| Command | Paths |
|---|---|
| `actor.identity.patch` | `/identity/name`, `/identity/alignment`, `/identity/personal/{age,gender,deity,concept}` |
| `actor.narrative.patch` | `/narrative/{description,appearance,personality,biography,fears,bonds}` |
| `actor.archive`, `actor.restore` | Empty payload. Archiving records the status to return to; archived actors accept no edits until restored. |
| `actor.delete` | Empty payload. Only an unreferenced library actor; a campaign actor or a library actor that is the source of campaign actors must be archived instead. The final document stays in `dc_actor_revisions`. |

Behaviour: an older inspected revision still applies to the latest row and
preserves unrelated fields; replaying a command id returns the original
receipt; a no-change command does not advance the revision; a caller who may
not edit an actor gets `actor_not_found`. Commands address canonical paths;
until rows store the canonical document directly, the processor writes each
fact to the stored key it currently lives under and keeps duplicate copies
equal.

Checks: `drush php:script tests/actor_command_integration.php` (88 checks,
one original fixture, always rolled back).

Routed through the authority so far:

- The character archive and delete forms execute `actor.archive` and
  `actor.delete`. `CharacterManager::deleteCharacter()` is removed.
- **New characters** are created by `CharacterManager::createPreparedActor()`
  (`actor.draft.create`). The authority allocates the UUID, owner and revision;
  a caller cannot supply them.
- **GM wizard choices** are the typed, idempotent command
  `actor.build.apply_choices`. `CharacterCreationGmService::applyDraftUpdates()`
  executes it; the creation rules (`applyValidatedChoices()`) still decide what
  the choices mean, and their write is committed under the command's identity.
- **Manual wizard saves** are committed by
  `CharacterManager::commitPreparedActorWrite()` (`actor.creation.save`).

In all three the authority locks the row, checks the revision, writes,
requires the result to convert and validate under the actor contract, and
records history and a receipt. A save that would break the contract fails and
changes nothing.

Every other writer in the register is still direct.

## GM harness tools

The Character Creation surface registers three shared actor tools
(`src/Service/EditorGm/Tool/Actor/ActorTool.php`), thin adapters with no rules
or persistence of their own:

| Tool | Effect |
|---|---|
| `inspect_actor` | Read-only canonical view: document, revision, scope, validation checks, the paths each command accepts, recent history. |
| `plan_actor_changes` | Preview one typed command: exact changed paths with before and after values. Read-only. |
| `apply_actor_changes` | Execute one typed command immediately; returns the committed receipt with next steps, or a typed failure. |

The Definition Editor and Storyline Builder surfaces register the same three
tool names (`DefinitionActorTool`) for canonical actor definitions. The actor
is the library row that mirrors the definition. An applied change is saved by
`CanonicalDefinitionService::saveDefinition()`, which stays the one writer of
definitions and commits the library row through the actor authority
(`ActorDefinitionMirror`). Storyline Builder executes it immediately; the
Definition Editor keeps its existing approval step for mutating tools.

## Canonical actor definitions

A canonical actor definition of type `pc` or `npc` is stored in exactly one
place: its library actor row (`campaign_id = 0`,
`lifecycle_state = canonical_template`, `instance_id` = the definition id).
`character_data` holds the definition's `state_data` verbatim plus three
wrapper keys (`CanonicalDefinitionService::LIBRARY_ROW_KEYS`): `actor_id`,
`canonical_actor_version`, and `canonical_definition` (`source_module`,
`metadata`). No class, ancestry, statistics or level clamp is invented.

- `CanonicalDefinitionService` still owns definition identity, schema
  validation, versioning and room impact. It reads and writes these rows only
  through `ActorDefinitionMirror` (`definitionRecords`, `mirror`), which
  commits through the actor authority.
- Typed actor commands sent straight to a definition row are refused with
  `actor_definition_owned`; the actor tools on the Definition Editor and
  Storyline Builder go through the definition service.
- `dc_canonical_actors` now holds only the four non-actor placeables (one
  trap, one obstacle, two parties) until they get their own home (D20).
- Template import no longer truncates and rebuilds actor definitions; storyline
  and objective actor refs resolve against the definition service. Update
  10227 dropped the two foreign keys that pointed at `dc_canonical_actors`.
- `CharacterManager::loadActorBySlugOrId()` is read-only. The load-time
  materializer, `saveCanonicalActorTemplate()` and
  `instantiateCampaignActorFromTemplate()` (invented defaults) are removed.

History: `fold-canonical-actors.php` created the 110 rows;
`retire-canonical-actor-rows.php` (dry run by default, `ACTOR_RETIRE_APPLY=1`)
moved the definitions on 2026-11-14, proving each read back identical before
deleting the old row. A copy of the old table is kept as
`zz_backup_dc_canonical_actors_20261114`.

Wizard choices go through `plan_character_draft_updates` /
`apply_character_draft_updates`, which execute `actor.build.apply_choices`.

The live validator resolves references against `ActorCatalogService`.

Player-build choice
eligibility is `not_checked` until the character creation rules are projected
from the new document (slice S2). The shared actor tools are not implemented.

## Character GM: build in one step, no confirmations (2026-11-14)

- `autofill_character_draft` (`CharacterCreationGmService::autofillDraft`)
  applies the player's stated preferences, then fills every open decision in
  wizard order from the options the validation authority offers for the
  candidate character, and saves once through `actor.build.apply_choices`.
- It is strict. An invalid preference fails the request (422). A wizard
  option the rules then reject (`character_wizard_offered_option_rejected`),
  a decision with nothing offered (`character_wizard_decision_not_offered`)
  or a multi-select with no declared count
  (`character_wizard_choice_count_not_declared`) is a rules defect and fails
  loudly. Only the character's name may stay open.
- The character surface never returns a clarification, never asks for
  approval, and returns exactly one next step, taken from validation after a
  save.
- Choice projection now states `selection_count` for every multi-select,
  `required_from` for a subset rule, and leaves out options the form shows
  disabled.

Known open defects it reports (free-text decisions with no offered options):
deity for Domain Initiate, the Multitalented language, the Gnome Obsession
lore.

## Spell tables (2026-11-14)

`src/Service/SpellcastingTables.php` is the only source of spell counts for
wizard, magus, witch, cleric, druid, bard, oracle, sorcerer and summoner:
slots per day at every level, cantrips per day, where known spells live, and
the exact number of cantrips and 1st-rank spells known at level 1. A wizard
specialist gains one cantrip and one slot per rank 1-9 and two 1st-rank
school spells in the starting spellbook. `CharacterManager::CASTER_SPELL_SLOTS`,
the per-class `spell_slots_by_level` copies in `CharacterRulesCatalog` and the
default slot table in `CharacterProgressionRegistry` are removed. Creation
validation requires the exact counts.

## Level-1 class feats (2026-11-14)

52 level-1 class feats were authored for champion, monk, investigator,
swashbuckler, gunslinger, inventor, oracle, witch, magus and summoner, which
had none and could not finish creation. Their engine effects are data in
`src/Service/ClassFeatEffects.php`, applied by `FeatEffectManager`. The 16
multiclass dedication feats the Eldritch Trickster racket offers are in the
feat catalog as type `archetype` (a bonus feat slot).

## Spell catalog rebuild (2026-11-15)

The packaged spell records (`content/intermediary/*_spells_intermediary.json`)
were rebuilt against a rules reference, because the original text extraction
had mixed page furniture, neighbouring spells and stat blocks into
descriptions and had lost or misnamed records.

- Facts (rank, school, traditions, traits, casting, range, area, targets,
  duration, save) were corrected wherever they disagreed with the reference.
- A record's text was kept only where it belongs to that spell; 67 bodies
  filed under the wrong name moved to their true spell; 18 records named
  after traits (`hex`, `cursebound`, `witch`, ...) are gone.
- 21 spells whose text was unrecoverable have original descriptions.
- Focus spells and rituals have no tradition, so they no longer appear in
  tradition spell lists at character creation.
- `raw_text_block` is `none` on rebuilt records.
- Registry copy before the rebuild: `zz_backup_spell_registry_20261115`.

Known limit: a sentence was removed only when it was page furniture, sidebar
narrative, or provably another reference spell's text. Text from a spell that
is not in the reference set could remain.

## Storyline actor relationships (2026-11-15)

- Affiliations and relationships follow the institution and relationship
  engines (2026-11-16); the free-text `faction` and `relationship_type` are gone.
- Draft schema: each actor spec may list `affiliations[] {domain, name}`.
  Domains are `InstitutionNormalizationService::AUTHORABLE_DOMAINS`: family,
  government (state or nation), allegiance (faction), settlement, religion,
  employer (guild), education (order), noble, security, criminal, culture.
  Ancestry and profession memberships are not authored; they follow the
  actor's `ancestry` and `class`. One settlement and one government per actor.
- `objects.actors.relationships` lists `{source_actor_id, target_actor_id,
  attitude, bond?, notes?}`. `attitude` is one of
  `RelationshipAttitudeService::ATTITUDES`; `bond` is a free label (spouse,
  rival, serves). One relationship per ordered pair of actors.
- Published definition: `contact.relationship_state.affiliations` holds the
  engine's structured selections (`domain, display_name, source_asset_type:
  storyline, source_asset_id: <storyline>:<domain>:<label>`). Relationships
  become `introduces_to` entries of type `knows` carrying the attitude, with
  `relationship_state.bond`, marked `authored_in: storyline_actor_relationships`.
- Campaign: `StorylineCampaignAttachmentService::apply` calls
  `InstitutionMembershipService::syncStorylineContactMemberships`, which puts
  each contact's selections in the NPC's structured affiliation fields and
  runs the normal NPC membership sync (`institution_member` edges). Bundled
  storylines seeded by `CampaignInitializationService` do not take this path.
- `/game-master/storyline-builder/actors?draft_id=…` groups actors by any one
  domain ("Group by"), colours links by attitude, and manages both.
- An actor that exists only as a draft spec opens a "not created yet" review
  page (`/actors/{id}?draft_id=…`); nothing is created by viewing.
- `StorylineAuthoringDraftController::decodeBody()` now decodes to arrays; it
  returned nested objects, so the save endpoint rejected every payload.
