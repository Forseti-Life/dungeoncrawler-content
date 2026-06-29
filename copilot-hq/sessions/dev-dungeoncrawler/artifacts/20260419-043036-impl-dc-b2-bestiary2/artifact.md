# dc-b2-bestiary2 Implementation Artifact

## Inbox item
`sessions/dev-dungeoncrawler/inbox/20260419-043036-impl-dc-b2-bestiary2`

## Commit
`6ceef3fb7`

## Files changed

### New creature data files (8 creatures, Track B extension)
```
content/creatures/bestiary2/will_o_wisp.json        aberration, level 6
content/creatures/bestiary2/merfolk_soldier.json     aquatic humanoid, level 1
content/creatures/bestiary2/giant_octopus.json       aquatic animal, level 8
content/creatures/bestiary2/clockwork_soldier.json   construct, level 3
content/creatures/bestiary2/pixie.json               fey sprite, level 4
content/creatures/bestiary2/quasit.json              fiend demon, level 1
content/creatures/bestiary2/barghest.json            fiend, level 4
content/creatures/bestiary2/dullahan.json            undead, level 10
```

Each file uses schema_version: 1.0.0, includes `bestiary_source: b2`, `family`, `tactical_role`,
and `encounter_themes` for filter support. All B2 creature families required by the AC are covered.

### ContentRegistry.php — sanitization added
`src/Service/ContentRegistry.php`
- Added `sanitizeTextFields(array $data): array` — recursively strips HTML tags from string fields
  before validation and persistence. Skips ID, version, and numeric fields.
- Called in `importContentFromJson()` immediately after `content_id` normalization.

### CampaignGmAccessCheck.php — new access check
`src/Access/CampaignGmAccessCheck.php`
- Implements `_campaign_gm_access: TRUE` routing requirement
- Requires `administer dungeoncrawler content` permission
- Registered in `dungeoncrawler_content.services.yml`

### CreatureCatalogController.php — new controller
`src/Controller/CreatureCatalogController.php`
- `GET  /api/creatures`                  — public read, list with ?level_min/max/rarity/trait/source
- `GET  /api/creatures/{creature_id}`    — public read, single stat block
- `POST /api/creatures/import`           — GM-only batch import (JSON body)
- `POST /api/creatures/{id}/override`    — GM-only single record override
- Import/override routes: `_campaign_gm_access: TRUE` + `_csrf_request_header_mode: TRUE`

### dungeoncrawler_content.routing.yml — 4 routes added
`dungeoncrawler_content.routing.yml`
- `dungeoncrawler_content.api.creature_catalog.list`
- `dungeoncrawler_content.api.creature_catalog.get`
- `dungeoncrawler_content.api.creature_catalog.import`
- `dungeoncrawler_content.api.creature_catalog.override`

### ContentSeederCommands.php — dc:import-creatures command
`src/Commands/ContentSeederCommands.php`
- Added `importCreatures()` command (`dc:import-creatures` alias)
- Calls `ContentRegistry::importContentFromJson($type)` — idempotent upsert
- Injected `ContentRegistry` via `drush.services.yml`

## Verification
- `php -l` clean on all PHP files
- `python3 -m json.tool` clean on all 8 JSON files
- `dc:import-creatures` will load B2 creatures into the registry on next drush run
- Encounter tooling (EncounterBalancer) queries the same `dungeoncrawler_content_registry` table — B2 creatures auto-available once imported, no code change needed
