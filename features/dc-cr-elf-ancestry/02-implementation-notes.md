# Implementation Notes — dc-cr-elf-ancestry

- Work item id: dc-cr-elf-ancestry
- Dev owner: dev-dungeoncrawler
- Status: in_progress (partial coverage, release-x recovery)
- Date updated: 2026-04-27T17:35:01+00:00

## Summary

Elf ancestry base data is **complete and functional** in `CharacterManager::ANCESTRIES` (commit a974997a8b or earlier). All AC items 1–6 (stat block, boosts, flaw, traits, base languages, Low-Light Vision) are implemented and seeded in the database. The only missing piece is AC item #7 (Int-modifier-based additional language selection), which requires the `dc-cr-languages` feature (currently in_progress) to be completed first.

## Completed AC items

- **AC-1**: Elf ancestry record in catalog with HP=6, Speed=30, Size=Medium ✓
- **AC-2**: Fixed ability boosts (Dexterity, Intelligence) ✓
- **AC-3**: Constitution flaw (−2) at character creation ✓
- **AC-4**: Low-Light Vision sense ✓
- **AC-5**: Base languages (Common, Elven) ✓
- **AC-6**: Ancestry traits (Elf, Humanoid) applied automatically ✓

## Blocked AC items

- **AC-7**: Character creation with Int-modifier additional language selection — **BLOCKED on dc-cr-languages feature** (also in_progress, same release).

## Implementation details

### Where Elf ancestry data lives

**File:** `/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php`

**Key section:** `ANCESTRIES['Elf']` constant (approx. line 26 in CharacterManager.php):

```php
'Elf' => [
  'hp' => 6,
  'size' => 'Medium',
  'speed' => 30,
  'boosts' => ['Dexterity', 'Intelligence'],
  'flaw' => 'Constitution',
  'languages' => ['Common', 'Elven'],
  'traits' => ['Elf', 'Humanoid'],
  'vision' => 'low-light vision',
],
```

### Database seeding

Elf ancestry node is automatically seeded via `dungeoncrawler_content_install()` hook (lines 2761–2810 of `dungeoncrawler_content.install`). On a fresh DB, `drush cache:rebuild` will create the Elf ancestry content node with all fields populated.

### Character creation flow

The CharacterCreationStepForm (Step 3: ancestry selection) and CharacterCreationStepController (step progression) already handle Elf ancestry selection. The form loops over `CharacterManager::ANCESTRIES` to populate the ancestry dropdown, so Elf selection is immediately available.

### Test coverage (from 03-test-plan.md)

| Test Case | Status | Notes |
|---|---|---|
| TC-EA-01 (stat block) | Ready to test | Unit test for HP/Speed/Size constants |
| TC-EA-02 (fixed boosts) | Ready to test | Verify Dex+Int boosts in ANCESTRIES |
| TC-EA-03 (Con flaw) | Ready to test | Verify CON −2 applied at creation |
| TC-EA-04 (Low-Light Vision) | Ready to test | Verify vision flag in ancestry + senses field |
| TC-EA-05 (base languages) | Ready to test | Verify Common+Elven in languages field |
| TC-EA-06 (traits) | Ready to test | Verify Elf+Humanoid traits applied |
| TC-EA-07 (free boost validation) | Ready to test | Prevent Dex/Int as free boost |
| TC-EA-08–14 | Deferred | All require dc-cr-languages (additional language selection) |
| TC-EA-15 (missing free boost blocks save) | Ready to test | Existing form validation |
| TC-EA-16–17 (ACL) | Ready to test | Existing route permissions |
| TC-EA-18 (E2E flow) | Ready to test | Playwright test for full character create |

## Dependency: dc-cr-languages

The `dc-cr-languages` feature (also release-x, in_progress) is responsible for:
- UI/form for additional language selection during character creation
- Intelligence modifier → language slot calculation
- Validation that selected languages are in the allowed pool per ancestry
- Persistence of language choices to the character entity

Current state of dc-cr-languages per 2026-04-27 audit:
- Base ancestry language lists exist (read-only)
- No character-creation language selection flow
- No Int-based language slot system
- Feat effects still note pending language selections

**Impact:** Test cases TC-EA-08, 11–14 cannot pass until dc-cr-languages ships. AC item #7 (full Elf character with Int-modifier languages) also depends on dc-cr-languages.

## What remains for this release (release-x)

### Option A: Ship Elf base ancestry (recommended if dc-cr-languages is delayed)

- Elf can be selected during character creation
- All base stats, boosts, flaw, traits, base languages, and vision are correct
- Free boost (Strength/Constitution/Wisdom) must still be selected
- No additional language slots due to dc-cr-languages not shipping
- Test cases TC-EA-01–07, 15–18 (all except 08, 11–14) pass
- **Outcome:** Elf is playable but without the Int-modifier language advantage; must descope or defer additional language feature

### Option B: Wait for dc-cr-languages, ship full Elf (recommended if dc-cr-languages is on track)

- All AC items 1–7 implemented
- All test cases pass (TC-EA-01–18)
- Adds full Int-modifier-scaled language selection for Elf (up to +Int additional languages from the pool)
- **Timeline:** Depends on dev-dungeoncrawler completing dc-cr-languages first; currently both are in_progress for same release

## Verification commands

### Check Elf data in code

```bash
grep -A 1 "'Elf' =>" /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php
```

### Check Elf seeding (post-install or update)

```bash
cd /var/www/html/dungeoncrawler
./vendor/bin/drush --uri=https://dungeoncrawler.forseti.life cache:rebuild
./vendor/bin/drush --uri=https://dungeoncrawler.forseti.life sqlq "SELECT title, body FROM node WHERE type='ancestry' AND title='Elf';"
```

### Verify Elf is available in character creation

```bash
curl -s 'https://dungeoncrawler.forseti.life/dungeoncrawler/character/create' | grep -i "elf"
```

## Next actions (for release-x go/no-go decision)

1. **PM must decide** whether to:
   - **A:** Ship Elf base + descope Int-language behavior (de-block immediately)
   - **B:** Extend release-x to complete dc-cr-languages (wait for full implementation)

2. **If Option A:** Dev ships as-is; QA verifies TC-EA-01–07, 15–18 pass; feature ships with reduced scope.

3. **If Option B:** Dev works on dc-cr-languages completion; once that ships, Elf full AC becomes testable.

4. **If Option B + dc-cr-languages completes:** Re-trigger dc-cr-elf-ancestry tests TC-EA-08, 11–14 + ship full feature.

## Commits

- No new commits in this pass (Elf base data already committed in previous release cycles)
- Feature is ready for ship-gate verification once PM clarifies scope decision

## Related features/issues

- `dc-cr-languages` (same release, in_progress) — blocks AC-7 and additional-language test cases
- `dc-cr-low-light-vision` (same release, planned) — Low-Light Vision structure subject to change pending LLV feature resolution
- `dc-cr-ancestry-system` (prerequisite, should be complete) — Elf depends on ancestry system being operational
- `dc-cr-character-creation` (prerequisite, should be complete) — Elf selection happens in step 3 of character creation
