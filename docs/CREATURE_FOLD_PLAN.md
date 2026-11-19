# Creature fold: creatures become actor definitions

Part of **DC-ACTOR-AUTHORITY-20261114** (`docs/ACTOR_AUTHORITY.md`). The
binding decision is "creatures are actors; retire the creature family".
This plan covers only that part.

Status on 2026-11-19: **stages A and B done; stage C (cutover) not run.**

- 18 creatures exist as actor definitions with class `creature` (15 created,
  3 merged into the actor that already had the id or name). Applied with
  `CREATURE_FOLD_APPLY=definitions`; library actor rows were copied first to
  `zz_backup_library_actor_rows_20261119`.
- The creature family, its 86 registry rows and the 76 room placements that
  point at it are unchanged until the cutover.

## Decision (Board, 2026-11-19): a creature is a class

A creature is a canonical actor definition of type `npc` whose `class` is
`creature`. There is no creature family, no creature actor type and no
creature schema.

- What kind of creature it is (plant, undead, fiend) is `species`.
- It is stored like every other actor definition: one library actor row,
  written by `CanonicalDefinitionService::saveDefinition('actor', ...)`
  through `ActorDefinitionMirror` and the actor authority.
- Five actor definitions already follow this: `goblin_chieftain`,
  `goblin_warrior`, `tpl_char_enemy_goblin_002`, `tpl_char_enemy_ooze_001`,
  `tpl_char_enemy_skeleton_001`.
- `ActorDocumentConverter` reports an actor as kind `creature` when its
  stored class is `creature`. Today it reads the row's `role` column; that
  rule changes at cutover (step C2).

## What the creature library holds (measured)

```bash
cd /var/www/html/dungeoncrawler
./vendor/bin/drush php:script \
  web/modules/custom/dungeoncrawler_content/tools/actor-authority/creature-fold-preflight.php
```

| Measure | Count |
|---|---|
| Creature definitions in the content registry | 86 |
| With a stat block (hit points, AC, saves, attacks) | 11 |
| Name, level and type only ("stubs") | 75 |
| Quarantined today | 82 |
| Fold to a valid actor definition as they are | 11 |
| Blocked: no hit points to record | 75 |
| Blocked: also no level | 2 |
| Ids that already exist as an actor definition | 4 |
| Definitions placed in rooms | 8 |
| Placements to repoint (all room versions and active drafts) | 41 |
| Campaign actor rows with role `creature` | 84, all with no data |

Three facts shape the work:

1. **Most creatures are empty.** All 75 stubs came from the example registry
   file `default_registry_examples.json`. They have a name, a level and a
   type, and nothing else.
2. **Seven of the eight placed creatures are stubs.** Rooms in play place
   `army_ant_swarm`, `assassin_vine`, `changeling`, `cockroach_swarm`,
   `ice_mephit`, `level_7_black_pudding` and `young_copper_dragon`. None has
   a stat block, so the 84 campaign creature rows hold nothing.
3. **The eleven real stat blocks lose fields in today's actor definition.**
   The definition contract has no place for the fields listed under B1.

## Board decisions (2026-11-19)

| # | Question | Decision |
|---|---|---|
| Q1 | The 7 placed stubs need stat blocks. | Original stat blocks authored at each creature's level. |
| Q2 | The 66 unplaced stubs. | Analysed, below. Not migrated; archived to a backup table at cutover. |
| Q3 | Third-party names and text. | Keep the content, change the names. |
| Q4 | `r7-drowned-dead-pack` and `r7-sump-beast` exist as both. | Creature stat block, actor story fields, one definition each. |
| Q5 | `skeleton_guard_alpha`, `skeleton_guard_beta`. | Keep the actor, drop the stub. |

The creature `Goblin Warrior` duplicated the actor `goblin_warrior` by name;
it was merged under the Q4 rule.

Names, stat blocks, action costs and merge rules are data in
`tools/actor-authority/creature-fold-authoring.json`.

| Was | Is now |
|---|---|
| Merfolk Soldier | Tidewarden Soldier |
| Pixie | Glimmerwing Sprite |
| Quasit | Cinderspite Imp |
| Will-o-Wisp | Marsh Lantern |
| Giant Octopus | Reefstrangler Octopus |
| Clockwork Soldier | Brasswork Sentry |
| Barghest | Ashmaw Stalker |
| Army Ant Swarm | Legion Ant Swarm |
| Assassin Vine | Throttlevine |
| Changeling | Hagblood Foundling |
| Cockroach Swarm | Skitterroach Swarm |
| Ice Mephit | Rimeling |
| Black Pudding | Blackmire Ooze |
| Young Copper Dragon | Young Verdigris Dragon |

Definition ids did not change, so room placements and campaign data keep
resolving. Seven ids therefore still spell the old name (`assassin_vine`).
Renaming an id is a separate `actor.template.rename`.

### The 66 unplaced stubs

None has a stat block, and none is placed in a room.

| Group | Count | What they are |
|---|---|---|
| Not creatures | 17 | Text fragments from a book import: "Introduction", "UNCOMMON CE", "Strike; Effect The devil pulls the". |
| Index fragments | 12 | "Level 20 draconal" stored at level 1; ancestry sidebar entries such as "Shae (rare)". |
| Name only | 37 | A published creature name, a level and the type `creature`. |

Under Q3 the name is the one thing that may not be kept, so nothing in these
records carries forward. The 37 are a list of level slots (0 to 20) that
original creatures could fill later; the list is in the fold's dry-run output
under `archived_name_only`.

What the fold does not carry, by rule: skill proficiency ranks (a stat-block
skill is its modifier), `xp_reward` (derived from level), and loot drop
chances and currency ranges (the actor definition holds item names).

## Stages

### A. Preflight (done)

`tools/actor-authority/creature-fold-preflight.php` folds every creature in
memory, validates the result as an actor definition, and accounts for every
source field. It is the gate for stage C: it must report zero blocked
definitions and zero fields without a home.

### B. Prepare (done)

`tools/actor-authority/fold-creatures.php` is the migration. Its dry run
reports 18 actor definitions, all valid and ready for publication, and no
problems.

**B1. Give the actor definition a home for creature mechanics.** Add these
optional fields to `canonical_actor.schema.json` `state_data`, and carry each
into the actor document in `ActorDocumentConverter`:

| Creature field | Creatures (most per field) | Actor document target |
|---|---|---|
| `rarity` | 84 | `identity.taxonomy.rarity` |
| `traits` | 23 | `identity.taxonomy.traits` |
| `family` | 7 | `identity.taxonomy.family` |
| `title` | 2 | `identity.title` |
| `hex_footprint` | 11 | `build.hex_footprint` |
| attack `type`, `traits`, `reach_ft` | 11 | `build.strikes[]` |
| `special_abilities` | 7 | `build.special_abilities[]` |
| hit point `weaknesses` (5), `immunities` (3), `resistances` (2), `hardness` (1) | 5 | `build.hit_points` |
| `speed.swim` (4), `speed.fly` (3) | 4 | `build.speed` |
| `encounter_themes` (7), spawn type (4), respawn, wander radius (1 each) | 7 | `defaults.spawn` |
| combat personality, speech patterns, desires | 1 | `defaults.psychology` |
| skill `proficiency` | 8 | none: a stat-block skill is its modifier |
| `xp_reward` | 3 | none: derived from level, owned by quests |
| `loot_table` | 1 | `defaults.loadout`, needs item ids authored |

`defaults.spawn` has no `encounter_themes` and `defaults.psychology.combat`
has no `preferred_targets` in `actor.schema.json`; both are added here.
Seven special abilities have no action cost. The actor contract requires
one, so each is authored, never guessed.

The existing preflight (`conversion-preflight.php`) must still report every
actor row clean after this change (1001 rows on 2026-11-19).

**B2. Author what is missing** (Q1, Q3): stat blocks for the placed stubs,
action costs, loot item ids.

**B3. Write the migration** as one script with a dry run by default, in the
style of `retire-canonical-actor-rows.php`. For each creature it saves the
actor definition, reads it back, and proves every source field arrived.

### C. Cutover (one change, under a maintenance window)

Code and data move together. Applying the data alone would leave an empty
creature family that the encounter generator refills at run time.

1. Back up `dungeoncrawler_content_registry` creature rows, the creature
   quarantine rows, room versions, room drafts and campaign room contents.
2. Run the migration: create the actor definitions, resolve the four id
   collisions as decided, and repoint the 41 placements from
   `{family: creature}` to `{family: actor}`.
3. Remove the creature family from code:
   - `CanonicalDefinitionService::FAMILIES` and its schema, id and name maps;
     `config/schemas/creature.schema.json`.
   - `generate_creature_definition` and the creature branch of
     `CanonicalDefinitionGenerationService`. A generated creature is an
     actor definition with class `creature`.
   - `EncounterGeneratorService`: select and generate actor definitions with
     class `creature`.
   - `CanonicalRoomProjectionService` and `RoomEditorService`: the
     `creatures` room bucket is filled from actor placements whose
     definition has class `creature`, so encounter behaviour does not change.
   - `CreatureCatalogController`, the creature seeds in `TemplateImportService`
     and `ContentSeederCommands`, and the example registry file.
   - Room Editor and Definition Editor family lists, token colours, map
     legend, and `GenerationVocabulary::PLACEABLE_FAMILIES`.
4. Delete the creature registry and quarantine rows.
5. Verify: the canonical library has no creature family; the preflight finds
   no creature rows; every repointed room loads, validates and projects the
   same `creatures` bucket as before; the Dungeon Editor contract tests and
   the actor authority guard test pass.

### D. Follow-up, not part of this fold

The 84 empty campaign creature rows are instance data. They are rebuilt from
the new definitions under the campaign-instance work (D22 in the ledger), not
here.
