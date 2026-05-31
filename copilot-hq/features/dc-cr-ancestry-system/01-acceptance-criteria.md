# Acceptance Criteria (PM-owned)

## Gap analysis reference

Gap analysis performed against `dungeoncrawler_content/src/Service/CharacterManager.php`.

Coverage findings:
- `CharacterManager::ANCESTRIES` constant — all 14 ancestries (including 6 core + half-elf, half-orc, leshy, orc, catfolk, kobold, ratfolk, tengu) with hp, size, speed, boosts, flaws, languages, traits, vision — **Full** (data exists as PHP constants)
- `CharacterManager::HERITAGES` — heritage options per ancestry — **Full** (data exists as PHP constants)
- Drupal `ancestry` content type (persistent nodes, admin-editable) — **None** (data is in PHP only; no content type found)
- `GET /ancestries` API endpoint — **None** (no route found for exposing ancestry list)
- Character creation step applying ancestry stats to draft — **Partial** (CharacterManager exists; ancestry application to character not confirmed wired)

Feature type: **enhancement** — ancestry data is already in PHP constants; Dev must expose it via API/content type. Do NOT duplicate the ancestry data; reference or migrate `CharacterManager::ANCESTRIES`.

Depends on: character entity model (no hard dependency on dc-cr-action-economy; can be built in parallel).

## Happy Path
- [ ] `[NEW]` An `ancestry` content type exists with fields: name, hit_points (int), size (tiny/small/medium/large), speed (int, feet), ability_boosts (list of ability scores), ability_flaws (list of ability scores), languages (list), senses (list, e.g. darkvision), and a reference to available ancestry feats.
- [ ] `[EXTEND]` All six core PF2E ancestries are available via API: dwarf, elf, gnome, goblin, halfling, human. (Data already in `CharacterManager::ANCESTRIES` — expose it, do NOT duplicate.)
- [ ] `[NEW]` `GET /ancestries` endpoint returns all ancestries with id, name, hp, size, speed, ability boosts/flaws, languages, and senses.
- [ ] `[NEW]` `GET /ancestries/{id}` returns full ancestry detail including available heritages (from `CharacterManager::HERITAGES`).
- [ ] `[NEW]` A character creation step accepts an ancestry selection and stores it on the character entity.
- [ ] `[NEW]` Selecting an ancestry applies its ability boosts and flaws to the character's ability score array.
- [ ] `[NEW]` Selecting an ancestry sets the character's base hit points contribution from ancestry (e.g., dwarf: 10, elf: 6, gnome: 8, goblin: 6, halfling: 6, human: 8).
- [ ] `[NEW]` Selecting an ancestry sets the character's speed (e.g., dwarf: 20 ft, elf: 30 ft, gnome: 25 ft, goblin: 25 ft, halfling: 25 ft, human: 25 ft).

## Edge Cases
- [ ] `[NEW]` A character cannot have more than one ancestry selected; re-selecting replaces the previous choice.
- [ ] `[NEW]` Attempting to save a character without an ancestry selection returns a clear validation error ("Ancestry is required").
- [ ] `[NEW]` An ancestry with two free ability boosts (e.g., human) allows the player to select any two ability scores; validation rejects duplicate selections.

## Failure Modes
- [ ] `[NEW]` An invalid ancestry ID passed to the creation endpoint returns 400 with a descriptive error.
- [ ] `[NEW]` Ability boost conflicts (boosting and flawing the same score) are caught at validation and rejected with an explicit message.

## Permissions / Access Control
- [ ] Anonymous user behavior: ancestry content type nodes are publicly readable (view); creation/selection requires authentication.
- [ ] Authenticated user behavior: players may select an ancestry for their own characters; cannot modify another player's character.
- [ ] Admin behavior: admins can create, edit, and delete ancestry content type nodes.

## Data Integrity
- [ ] No data loss: an existing character that already has an ancestry set must not lose that data after module updates.
- [ ] Rollback path: uninstalling the ancestry sub-module must leave character nodes intact (ancestry reference field becomes empty/null, not corrupted).

## Verification method
```
drush php-eval "
  // Verify 6 ancestry nodes exist
  \$count = \Drupal::entityQuery('node')->condition('type','ancestry')->count()->execute();
  print 'Ancestry count: ' . \$count . PHP_EOL; // expect 6
"
```
- QA automated audit must still pass (0 violations, 0 failures) after module changes are deployed to local.

## Knowledgebase check
- KB: none found for ancestry system specifically.
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit immediately after module changes.
- Dependency note: character creation workflow (`dc-cr-character-creation`) will reference this feature as a prerequisite; triage character creation only after ancestry, class, and background are planned.
