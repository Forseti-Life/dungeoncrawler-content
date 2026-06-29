# Feature Brief: Canonical Spell and Feat Library Migration

- Work item id: dc-arch-spell-feat-library-canon
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release:
- Feature type: architecture
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Priority: P1
- Source: 2026-05-05 architecture review of Dungeoncrawler spell/feat canonical storage
- Category: data-architecture
- Created: 2026-05-05

## Summary

Migrate Dungeoncrawler so both spells and feats are canonically defined in the database-backed content library instead of being split across registry rows, PHP constants, service-local catalogs, and runtime character state. The target end state is: **library definitions in the content registry, runtime state on characters, and derived effects layered on top**.

## Goal

- Make `dungeoncrawler_content_registry` the single canonical source of truth for spell and feat definitions.
- Eliminate architectural drift between:
  - registry-backed spell selection records
  - `SpellCatalogService` code-backed rich spell data
  - `CharacterManager` feat constants
  - runtime character payloads in `dc_campaign_characters`
- Ensure sheets, tooltips, APIs, character creation, and gameplay resolution all read the same canonical spell/feat library definitions.

## Source reference

Current storage audit findings:

- Spells are currently split between:
  - `dungeoncrawler_content_registry`
  - `SpellCatalogService`
  - `dc_campaign_characters.character_data` / `state_data`
- Feats are currently split between:
  - `CharacterManager::ANCESTRY_FEATS`
  - `CharacterManager::CLASS_FEATS`
  - `CharacterManager::GENERAL_FEATS`
  - `FeatEffectManager` derived state
  - `dc_campaign_characters.state_data`

## Migration plan

### Phase 1: Canonical schema contract

Define the canonical DB-backed schema for both `spell` and `feat` library records in `dungeoncrawler_content_registry`, including which fields live in top-level columns versus `schema_data`.

### Phase 2: Library import and backfill

Add packaged spell and feat source files, extend the seeding/import path, and backfill existing installs so canonical spell/feat rows exist in the content registry.

### Phase 3: Read-path cutover

Replace code-backed spell and feat definition reads with registry-backed resolution throughout:

- spell selection
- character sheet rendering
- spell/feat tooltip data
- gameplay rule lookups
- API responses

### Phase 4: Write-path normalization

Update character creation and save flows so character records store only canonical IDs plus mutable runtime state. Normalize legacy payloads on read/write.

### Phase 5: Runtime/derived layering

Keep runtime mutable data in character state:

- spell slots
- focus points
- selected feats
- feat resource counters
- derived feat effects

But require all canonical feat/spell rule text and definition data to originate from the DB library.

### Phase 6: Source-of-truth cleanup

Remove or demote:

- code-only spell catalog duplication
- feat constants as a primary data source
- stale fallback paths that bypass the canonical library

## Acceptance criteria

### AC-1: Canonical registry coverage

- `dungeoncrawler_content_registry` contains canonical `spell` and `feat` records for all supported Dungeoncrawler gameplay definitions
- Each record has a stable `content_id`
- `schema_data` contains the fields required by UI and gameplay consumers

### AC-2: Spell catalog unification

- Rich spell detail comes from registry-backed library records, not from a service-local canonical copy
- `/api/spells` and `/api/spells/{id}` resolve from the canonical library layer
- Character spell selection and tooltip rendering use the same canonical spell source

### AC-3: Feat catalog unification

- Base feat definitions no longer depend on `CharacterManager` constants as the canonical source
- Feat sheet/tooltips and feat-effect derivation resolve from canonical feat library records
- Feat metadata such as prerequisites, benefits, traits, actions, special rules, and repeatability is library-backed

### AC-4: Character state separation

- Character records in `dc_campaign_characters` store spell/feat references and mutable runtime state only
- Canonical definitions are not duplicated into runtime state except where compatibility shims are required during migration

### AC-5: Legacy compatibility

- Existing characters with legacy spell/feat payloads still load correctly
- Legacy shapes are normalized during migration or on read/save without data loss

### AC-6: Definition-source retirement

- Old code-only canonical sources are removed or explicitly reduced to migration compatibility helpers
- Documentation states that the database library is the canonical source of truth for both spells and feats

## Technical notes

- **Current spell DB path**
  - Table: `dungeoncrawler_content_registry`
  - Current read path: `CharacterManager::getSpellsByTradition()`
  - Current name lookup path: `CharacterViewController::buildSpellLookup()`
- **Current rich spell path**
  - Service: `dungeoncrawler_content.spell_catalog`
  - Files: `SpellCatalogService.php`, `SpellCatalogController.php`
- **Current feat definition path**
  - File: `CharacterManager.php`
  - Constants: `ANCESTRY_FEATS`, `CLASS_FEATS`, `GENERAL_FEATS`
- **Current runtime effect path**
  - Files: `FeatEffectManager.php`, `CharacterStateService.php`
  - Storage: `dc_campaign_characters.state_data`

## Implementation hint

Treat this as a controlled data-model migration:

1. define canonical registry shapes
2. seed/backfill the library
3. cut over read paths
4. cut over write paths
5. remove legacy definition sources

Do not attempt to flip every reader/writer in one pass.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: no new anonymous mutation routes are introduced during catalog migration
- CSRF expectations: all mutation endpoints continue to require existing secured request headers and route protections
- Input validation: spell/feat IDs must resolve against canonical registry records before persistence or gameplay execution
- PII/logging constraints: migration and audit logging must avoid storing personal data; only content IDs, character IDs, and migration outcomes are logged

## Roadmap section

- Roadmap: Data model modernization

## Latest updates

- 2026-05-05: Handed off to QA for test generation (pm-qa-handoff.sh)

- 2026-05-05: Created planned migration feature after architecture review confirmed spells are split across DB + service layers and feats remain code-constant-backed.
- 2026-05-13: Returned to `ready` during stale in-progress cleanup. Current code still relies on `CharacterManager::ANCESTRY_FEATS`, `CLASS_FEATS`, and `GENERAL_FEATS` across character creation, leveling, views, and feat catalog paths, while rich spell resolution still depends on `SpellCatalogService`. The planned canonical registry migration has not yet been cut over, so `in_progress` was no longer accurate.
- 2026-05-13: Ordinary spell-definition reads were narrowed toward the intended DB-only model: `/api/spells` now treats `dungeoncrawler_content_registry` as the runtime source of truth and fails explicitly when the registry is unavailable or empty instead of silently falling back to bundled spell JSON. Focus-spell and feat canonicalization remain pending follow-on work.
- 2026-05-13: Focus spell catalog reads were moved onto the same registry-backed spell library. `/api/focus-spells` now filters canonical focus rows from `dungeoncrawler_content_registry`, the seed data gained a dedicated wizard-school focus override file for missing canonical rows, and the APG/SoM placeholder focus rows were normalized to unique canonical spell IDs so the temporary generic-ID exclusions are no longer needed.
