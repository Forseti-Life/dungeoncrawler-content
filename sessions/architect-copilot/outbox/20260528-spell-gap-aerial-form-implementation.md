- Status: done
- Summary: Implemented the `aerial-form` spell-gap repair by correcting the stale CRB source-backed override, regenerating the CRB intermediary, and re-importing the canonical spell registry so the live `aerial-form` row now carries the full description.

## What changed
- Updated `dungeoncrawler-content/tools/extract_core_rulebook_spells.py` so the `aerial_form` source-backed override preserves the full CRB spell text.
- Added a focused extractor regression test in `dungeoncrawler-content/tools/test_extract_core_rulebook_spells.py` to ensure `Aerial Form` keeps its complete description.
- Regenerated `dungeoncrawler-content/content/intermediary/core_rulebook_spells_intermediary.json`.
- Re-imported spells into `dungeoncrawler_content_registry`, confirming the live canonical `aerial-form` row now contains the full description text.

## Verification
- `python3 -m unittest tools/test_extract_core_rulebook_spells.py`
- `python3 tools/extract_core_rulebook_spells.py`
- `vendor/bin/drush dc:import-spells`
- `vendor/bin/drush sqlq "SELECT LEFT(JSON_UNQUOTE(JSON_EXTRACT(schema_data, '$.description')), 700) AS description_preview FROM dungeoncrawler_content_registry WHERE content_type='spell' AND content_id='aerial-form';"`

## Blockers
- None.
