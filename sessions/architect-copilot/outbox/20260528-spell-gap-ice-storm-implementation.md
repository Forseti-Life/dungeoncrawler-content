- Status: done
- Summary: Implemented the `ice-storm` spell-gap repair by correcting the stale APG source-backed override, regenerating the APG intermediary, and re-importing the canonical spell registry so the live `ice-storm` row now carries the full description.

## What changed
- Updated `dungeoncrawler-content/tools/extract_core_rulebook_spells.py` so the `ice_storm` source-backed override preserves the full APG spell text.
- Added a focused extractor regression test in `dungeoncrawler-content/tools/test_extract_core_rulebook_spells.py` to ensure `Ice Storm` keeps its complete description.
- Regenerated `dungeoncrawler-content/content/intermediary/advanced_players_guide_spells_intermediary.json`.
- Re-imported spells into `dungeoncrawler_content_registry`, confirming the live canonical `ice-storm` row now contains the full description text.

## Verification
- `python3 -m unittest tools/test_extract_core_rulebook_spells.py`
- `python3 tools/extract_advanced_players_guide_spells.py`
- `vendor/bin/drush dc:import-spells`
- `vendor/bin/drush sqlq "SELECT LEFT(JSON_UNQUOTE(JSON_EXTRACT(schema_data, '$.description')), 700) AS description_preview FROM dungeoncrawler_content_registry WHERE content_type='spell' AND content_id='ice-storm';"`

## Blockers
- None.
