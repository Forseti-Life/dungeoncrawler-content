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

Preflight on 2026-11-14: all 852 rows convert and validate for save. Reviewed
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

Checks: `drush php:script tests/actor_command_integration.php` (79 checks,
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

Wizard choices go through `plan_character_draft_updates` /
`apply_character_draft_updates`, which execute `actor.build.apply_choices`.

The live validator resolves references against `ActorCatalogService`.

Player-build choice
eligibility is `not_checked` until the character creation rules are projected
from the new document (slice S2). The shared actor tools are not implemented.
