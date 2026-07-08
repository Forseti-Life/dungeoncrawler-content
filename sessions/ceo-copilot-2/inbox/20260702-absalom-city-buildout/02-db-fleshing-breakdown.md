# Absalom DB Fleshing Breakdown

## Current canonical DB coverage (as-audited)
1. `dungeoncrawler_content_registry`
   - `content_type='location'`: **3 records total**
   - Existing ids: `ltba-grandmas-house`, `nantambu`, `next_story_location`
   - Absalom landmark list coverage: **0 / 34**
2. `dungeoncrawler_content_dungeons`
   - Absalom footprint: `tpl_dungeon_tavern_basement` only
   - `dungeon_data.rooms`: 1 room (`tpl_room_tavern_entrance`)
3. `dungeoncrawler_content_rooms`
   - Absalom footprint: `tpl_room_tavern_entrance` only
   - Contents currently include one NPC entry (Eldric) and obstacle ids; no city-scale location inventory payloads.
4. `dungeoncrawler_content_characters`
   - Absalom-adjacent template footprint is minimal (tavern keeper variants only).

## What must be fleshed out in DB
1. **Canonical location registry rows (34 required)**
   - Table: `dungeoncrawler_content_registry`
   - `content_type='location'`
   - One row per landmark in the approved category list.
   - `schema_data` should include at minimum:
     - `location_id`, `name`, `category`, `location_type`
     - `description` (must include non-named/ambient actor presence)
     - `actor_count`
     - `named_npcs` (array)
     - `visible_inventory` (array)
     - `source_module` and `source_book` metadata
2. **Canonical room rows (34 required, one per landmark)**
   - Table: `dungeoncrawler_content_rooms`
   - Each landmark gets a room template id and room payload with:
     - layout metadata
     - contents payload aligned to named NPC + visible inventory references
3. **Canonical city dungeon aggregation**
   - Table: `dungeoncrawler_content_dungeons`
   - Expand/replace current single-room Absalom template so city scope references all landmark room ids.
4. **NPC and item registry/template backfill**
   - Tables:
     - `dungeoncrawler_content_registry` (`content_type='npc'`, `content_type='item'` where missing)
     - `dungeoncrawler_content_characters` for instantiated character templates tied to room/location anchors
   - Populate named NPC definitions and inventory item definitions so room contents do not rely on free-text only.
   - **Rule lock:** create NPC entities only for explicitly named NPCs per landmark source data.
   - **Rule lock:** non-named actors are represented only in location description text, with no separate actor entity rows.

## Immediate blockers before full ingest
1. Canonical id naming lock needed for all 34 locations before insert/upsert scripts are generated.

## Proposed next implementation slice
1. Use completed canonical payload source:
   - `04-absalom-canonical-structures.json`
2. Insert 34 `location` rows into `dungeoncrawler_content_registry` from canonical payload with strict validation.
3. Generate NPC rows only from each location record `named_npcs` list (102 references total).
4. Map visible inventory terms into item references where canonical items already exist; stage any missing items for controlled item backfill.
