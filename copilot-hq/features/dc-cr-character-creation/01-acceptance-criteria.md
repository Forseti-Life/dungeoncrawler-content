# Acceptance Criteria (PM-owned)

## Gap analysis reference

All criteria below are `[NEW]` — no existing character creation workflow exists in dungeoncrawler_content. This feature is a sequencing/orchestration layer that depends on: dc-cr-ancestry-system, dc-cr-background-system, and dc-cr-character-class all being implemented first. Dev builds the multi-step workflow after the underlying content types are available.

## Dependencies (must be complete before this feature can start Dev work)
- `dc-cr-ancestry-system` — ancestry content type + 6 seeded ancestries
- `dc-cr-background-system` — background content type + 5+ seeded backgrounds
- `dc-cr-character-class` — character_class content type + 12 seeded classes

## Happy Path
- [ ] `[NEW]` A player can initiate character creation, producing a character entity in `draft` state.
- [ ] `[NEW]` The creation workflow has discrete steps in order: (1) ancestry selection, (2) background selection, (3) class selection, (4) ability score assignment, (5) skill/feat selection, (6) final review + save.
- [ ] `[NEW]` Completing each step saves the player's choice to the draft character entity and advances to the next step.
- [ ] `[NEW]` A player can navigate back to a prior step and change their selection; downstream choices that conflict with the change are flagged or cleared.
- [ ] `[NEW]` After step 6, the character's derived statistics are computed and stored: total HP (ancestry HP + class HP/level × 1), AC (10 + Dex modifier + armor proficiency), saves (Fortitude/Reflex/Will at trained = level + 2 + ability modifier), and Perception (trained = level + 2 + Wis modifier).
- [ ] `[NEW]` After step 6, the character transitions from `draft` to `active` state and is visible on the player's character list.

## Edge Cases
- [ ] `[NEW]` A player who abandons the workflow mid-creation has their draft character persisted and can resume from the last completed step.
- [ ] `[NEW]` Two browser sessions cannot simultaneously edit the same draft character; the second session receives a clear conflict message.
- [ ] `[NEW]` Ability score assignment must respect PF2E boost/flaw rules from ancestry and background before allowing free boosts; invalid combinations are rejected at save time.
- [ ] `[NEW]` If a required prerequisite (ancestry/background/class) has no seeded content, the creation workflow returns a clear error rather than an empty dropdown.

## Failure Modes
- [ ] `[NEW]` A player who reaches step 6 with an incomplete prior step (e.g., no ancestry selected) cannot finalize; the error message identifies the missing step.
- [ ] `[NEW]` Derived stat computation with missing inputs (e.g., no class selected) does not crash; it returns a partial result or an explicit "incomplete" flag.

## Permissions / Access Control
- [ ] Anonymous user behavior: anonymous users cannot initiate or view character creation; the creation endpoint redirects to login.
- [ ] Authenticated user behavior: players can only create and edit their own characters; accessing another player's draft returns 403.
- [ ] Admin behavior: admins can view and edit any character draft for GM/admin tooling purposes.

## Data Integrity
- [ ] Draft characters are not visible on public character lists; only `active` characters are shown.
- [ ] A player may have at most 1 active draft creation session at a time per character slot (to prevent orphaned drafts accumulating).
- [ ] Rollback path: if character creation is disabled (module uninstall), existing `active` characters are unaffected; `draft` characters may be cleaned up via a drush command.

## Verification method
```
# Step 1: Confirm ancestry/background/class seeded content is present
drush php-eval "
  print 'Ancestries: ' . \Drupal::entityQuery('node')->condition('type','ancestry')->count()->execute() . PHP_EOL;
  print 'Backgrounds: ' . \Drupal::entityQuery('node')->condition('type','background')->count()->execute() . PHP_EOL;
  print 'Classes: ' . \Drupal::entityQuery('node')->condition('type','character_class')->count()->execute() . PHP_EOL;
"
# Step 2: Manually create a character via the UI/API, complete all 6 steps,
#         verify character appears in character list with correct derived stats.
# Step 3: QA automated audit must still pass (0 violations, 0 failures).
```

## Knowledgebase check
- KB: none found for character creation workflow specifically.
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit immediately after module changes.
- Sequencing note: this feature MUST NOT be started by dev until dc-cr-ancestry-system, dc-cr-background-system, and dc-cr-character-class are all merged and seeded. PM will activate this feature (status → ready) once those three are complete.
