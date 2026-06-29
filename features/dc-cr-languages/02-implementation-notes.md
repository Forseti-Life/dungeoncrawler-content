# Implementation Notes: dc-cr-languages

**Dev owner:** dev-dungeoncrawler
**Created:** 2026-04-28T02:05:36Z
**Release:** 20260412-dungeoncrawler-release-x

## Implementation approach

### Phase 1: Language catalog (GET /languages endpoint)
- Create LanguagesController with public GET /languages endpoint
- Return hardcoded JSON array with 10 core languages (Common, Elvish, Dwarvish, Gnomish, Halfling, Orcish, Sylvan, Undercommon, Draconic, Jotun)
- Each language includes: id, name, typical_speakers, script
- Route registered in dungeoncrawler_content.routing.yml
- No authentication required; cacheable response

### Phase 2: Character creation language support
- Update CharacterApiController::saveCharacter to accept `languages` in request body
- Add language validation: check against known catalog IDs
- Store languages in character_data JSON (already has `languages` field in schema)
- Auto-assign ancestry default languages at creation
  - Query ancestry metadata from CharacterManager::ANCESTRIES
  - Extract `languages` array for selected ancestry
  - Merge with player-provided bonus languages (deduplication)

### Phase 3: INT-modifier bonus language slots
- Fetch INT modifier from `abilities.int` field
- Calculate bonus slots: INT modifier * 1 (per AC)
- Validate player-provided bonus languages count does not exceed slots
- Return HTTP 400 if exceeded

### Phase 4: GET /characters/{id} language field
- Update CharacterApiController::loadCharacter to include `languages` in response
- Ensure `languages` is populated from character_data JSON

## Implementation notes (technical details)

- Languages field already exists in character schema at line 222–231 (character.schema.json)
- CharacterManager::ANCESTRIES has bonus_language_pool metadata (added in elf-ancestry work)
- Each ancestry has `languages` array (e.g., Elf: ['Common', 'Elven'])
- Character creation flow already handles JSON field storage
- No database schema migration needed

## Commits (to be created)

1. feat: Add languages GET endpoint and language catalog
   - LanguagesController
   - routing.yml update
   - Language data structure

2. feat: Implement language support in character creation
   - CharacterApiController changes (saveCharacter)
   - Language validation logic
   - Ancestry default-language assignment
   - INT-modifier bonus slot allocation

3. feat: Extend character GET endpoint with languages field
   - CharacterApiController changes (loadCharacter)
   - Response serialization

## Testing notes (for QA)

- Manual tests by dev before QA handoff:
  - `curl https://dungeoncrawler.forseti.life/api/languages` → HTTP 200, 10 languages returned
  - Create character with Elf + INT 14: verify languages includes [Common, Elven, <2 bonus>]
  - Create character with INT 10: verify languages only includes ancestry defaults
  - POST with invalid language ID: verify HTTP 400 response
  - GET /api/character/load/{id} includes languages field

## Risks and constraints

- Production-only environment: no rollback to test server, all changes deployed to live
- Ancestry default languages depend on CharacterManager ANCESTRIES constant being populated
  - Current code has metadata, but need to verify for all 6 core ancestries
- INT-modifier logic added with no prior unit tests (team may want to add async test coverage later)

## Open questions / next steps

- Should bonus languages be user-selectable at creation, or auto-assigned from pool?
  - AC says "player selects from available languages" → implementing as user-selectable
- Feat-based additional language acquisition is out-of-scope for this release (documented in AC)
- Edge case: what if ancestry has no `languages` array? Will default to empty array per AC
