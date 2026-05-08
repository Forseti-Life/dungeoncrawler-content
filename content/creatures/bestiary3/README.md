# Bestiary 3 Content Pack

This directory is the mount point for Bestiary 3 creature JSON files.

The Dungeoncrawler import pipeline (`dc:import-creatures`) will recursively
scan this directory and import all valid creature JSON files into the content
registry with `bestiary_source: b3`.

## Content pack requirement

Bestiary 3 creature stat blocks require an authorized source-backed content
pack. Fabricated or AI-generated stat blocks are not permitted. Operators must
supply their own content pack derived from a licensed or open-license source.

## Expected file format

Each creature JSON file must follow the creature schema (see
`config/schemas/creature.schema.json`) and include `"bestiary_source": "b3"`.
Example minimal shape:

```json
{
  "schema_version": "1.0.0",
  "creature_id": "<uuid>",
  "name": "Creature Name",
  "level": 5,
  "creature_type": "fiend",
  "traits": ["extraplanar", "evil"],
  "alignment": "CE",
  "rarity": "rare",
  "size": "large",
  "hex_footprint": 2,
  "bestiary_source": "b3",
  "pf2e_stats": { ... }
}
```

## Import command

```bash
# Import all Bestiary 3 creatures
drush dc:import-creatures --source=b3

# Import all sources
drush dc:import-creatures
```
