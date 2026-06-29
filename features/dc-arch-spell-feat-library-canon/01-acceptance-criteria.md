# Acceptance Criteria: dc-arch-spell-feat-library-canon

- Feature ID: dc-arch-spell-feat-library-canon
- Website: dungeoncrawler
- PM owner: pm-dungeoncrawler
- Status: ready for QA test plan

## Grooming decision

Decision: Ship as a **phased epic with 6 child delivery slices** (Phases 1–6 as defined in feature.md), NOT as a single monolithic release feature. Each phase is independently shippable and verifiable. Phases 1–3 (schema, import, read-path) are the minimum viable migration; Phases 4–6 follow in subsequent releases.

Rationale: The migration touches `dungeoncrawler_content_registry`, `SpellCatalogService`, `CharacterManager` constants, `FeatEffectManager`, and `dc_campaign_characters` simultaneously. Attempting all six phases in one release exceeds the 20-feature cap risk threshold and creates an unrollbackable blast radius. Phased delivery allows QA to verify each read/write boundary independently.

## Release posture

- Phase 1 (schema contract) + Phase 2 (library import/backfill): target next available release slot (release-v or later)
- Phase 3 (read-path cutover): follow-on release after Phase 2 is verified stable
- Phases 4–6: subsequent releases, sequenced by dev capacity

## Acceptance criteria (full epic)

### AC-1: Canonical registry coverage
- `dungeoncrawler_content_registry` contains canonical `spell` and `feat` records for all supported gameplay definitions
- Each record has a stable `content_id`
- `schema_data` contains all fields required by UI and gameplay consumers
- Verification: `SELECT content_type, COUNT(*) FROM dungeoncrawler_content_registry WHERE content_type IN ('spell','feat') GROUP BY content_type` returns non-zero counts matching the expected spell/feat catalog size

### AC-2: Spell catalog unification
- Rich spell detail resolves from registry-backed library records, not from `SpellCatalogService` local copy
- `/api/spells` and `/api/spells/{id}` resolve from the canonical library layer
- Character spell selection and tooltip rendering use the same canonical spell source
- Verification: `SpellCatalogService` no longer contains a hardcoded spell definition array; all spell reads trace to `dungeoncrawler_content_registry`

### AC-3: Feat catalog unification
- Base feat definitions no longer depend on `CharacterManager::ANCESTRY_FEATS`, `CLASS_FEATS`, or `GENERAL_FEATS` as the canonical source
- Feat sheet/tooltips and feat-effect derivation resolve from canonical feat library records
- Feat metadata (prerequisites, benefits, traits, actions, special rules, repeatability) is library-backed
- Verification: `CharacterManager.php` constants are removed or reduced to migration compatibility aliases only

### AC-4: Character state separation
- `dc_campaign_characters` stores spell/feat references and mutable runtime state only (spell slots, focus points, selected feat IDs, resource counters)
- Canonical definitions are not duplicated into runtime state except where compatibility shims are explicitly documented
- Verification: character save/load round-trip does not write canonical spell/feat definition text into `character_data` or `state_data`

### AC-5: Legacy compatibility
- Existing characters with legacy spell/feat payloads load correctly after migration
- Legacy shapes are normalized on read/save without data loss
- Verification: automated test loads a pre-migration character fixture and confirms all spells and feats render correctly on the character sheet

### AC-6: Definition-source retirement
- Old code-only canonical sources are removed or explicitly reduced to migration compatibility helpers
- A code comment or doc update states that the database library is the canonical source of truth for both spells and feats
- Verification: `grep -r "ANCESTRY_FEATS\|CLASS_FEATS\|GENERAL_FEATS" dungeoncrawler-pf2e/web/modules/custom/` returns only migration shim references, not primary definition usage

## Security acceptance criteria

- No new anonymous mutation routes introduced during catalog migration
- All mutation endpoints continue to require existing secured request headers and route protections
- Spell/feat IDs must resolve against canonical registry records before persistence or gameplay execution
- Migration and audit logging must avoid storing personal data; only content IDs, character IDs, and migration outcomes are logged
- Verification: security review confirms no new unauthenticated write endpoints in the migration diff

## KB reference

- None found in `knowledgebase/` for spell/feat DB migration patterns on this codebase. This is a first-of-kind migration; lessons learned should be recorded post-ship.
