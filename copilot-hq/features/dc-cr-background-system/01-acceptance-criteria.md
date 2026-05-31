# Acceptance Criteria (PM-owned)

## Gap analysis reference

All criteria below are `[NEW]` — no existing background system implementation exists in dungeoncrawler_content. Dev builds from scratch. Can be built in parallel with dc-cr-ancestry-system and dc-cr-character-class.

## Happy Path
- [ ] `[NEW]` A `background` content type exists with fields: name, description, fixed_ability_boost (one of: Str/Dex/Con/Int/Wis/Cha), free_ability_boost (player selects any ability score except the fixed one), skill_training (one skill name), lore_skill (one Lore specialization string), and skill_feat (reference to a feat node or feat name string).
- [ ] `[NEW]` At least 5 core backgrounds are seeded as content (e.g., Acolyte, Acrobat, Animal Whisperer, Artisan, Barkeep).
- [ ] `[NEW]` A character creation step accepts a background selection and stores it on the character entity.
- [ ] `[NEW]` Selecting a background applies its fixed ability boost and the player's chosen free ability boost to the character's ability score array.
- [ ] `[NEW]` Selecting a background grants training in the background's designated skill and Lore skill on the character entity.
- [ ] `[NEW]` Selecting a background records the granted skill feat on the character entity.

## Edge Cases
- [ ] `[NEW]` A character cannot select the same ability score for both the fixed boost and the free boost; validation rejects duplicate selections with a clear error ("Cannot apply two boosts to the same ability score from a single background").
- [ ] `[NEW]` A character cannot have more than one background; re-selecting replaces the prior choice (ability boosts, skills, and feat from prior background are removed before applying the new one).
- [ ] `[NEW]` Attempting to save a character without a background selection during the background creation step returns a clear validation error ("Background is required").

## Failure Modes
- [ ] `[NEW]` An invalid background ID passed to the creation endpoint returns 400 with a descriptive error.
- [ ] `[NEW]` Selecting a free ability boost that conflicts with the fixed boost is caught at save-time and returns a clear message before any data is written.

## Permissions / Access Control
- [ ] Anonymous user behavior: background content type nodes are publicly readable; application to a character requires authentication.
- [ ] Authenticated user behavior: players may select a background for their own character only; cannot modify another player's character.
- [ ] Admin behavior: admins can create, edit, and delete background content type nodes.

## Data Integrity
- [ ] No data loss: an existing character with a background set must retain that data after module updates.
- [ ] Rollback path: uninstalling the background sub-module must leave character nodes intact (background reference and applied fields become empty/null, not corrupted).

## Verification method
```
drush php-eval "
  \$count = \Drupal::entityQuery('node')->condition('type','background')->count()->execute();
  print 'Background count: ' . \$count . PHP_EOL; // expect >= 5
"
```
- QA automated audit must still pass (0 violations, 0 failures) after module changes are deployed to local.

## Knowledgebase check
- KB: none found for background system specifically.
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit immediately after module changes.
- Dependency note: `dc-cr-character-creation` will depend on ancestry, background, and class all being planned/ready before its own triage.
