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

Not wired yet: no caller uses `validateActor()`, and the live service has no
catalog adapter, so local references report `not_checked` until slice S2
supplies one. Player-build choice eligibility is `not_checked` until the
character creation rules are projected from the new document. Command
envelopes, the transaction core and tools are not implemented.
