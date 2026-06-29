# Problem Statement

The canonical DB row for `ice-storm` is still carrying a shortened description even though the authoritative APG source text and the stored `raw_text_block` already contain the full rules text.

## Current state

- Live canonical registry row: `dungeoncrawler_content_registry.content_id = ice-storm`
- Source-backed DB metadata: `source_book = advanced_players_guide`, `source_file = intermediary/PF2E Advanced Players Guide.txt`, `version = apg-raw-text-v1`
- Live `schema_data.description`: `You create a gray storm cloud that pelts creatures with magical hail and continues to fill the area with snow, sleet, concealment, and cold.`
- Live `schema_data.raw_text_block`: contains the full APG paragraph including the hail damage, difficult terrain, concealment, end-of-turn cold damage, and outdoor double-cloud clause

## Root cause

This is not a DB-normalization failure and not a missing-source-text problem.

The truncation is introduced earlier by the APG extractor's stale source-backed override for `ice_storm` in `tools/extract_core_rulebook_spells.py`. That override hardcodes a shortened `description` while also preserving the full `raw_text_block`. Regenerating the intermediary today still reproduces the shortened description, which confirms the intermediary payload and the DB row are both inheriting the bad override rather than losing text during import.

## Correct repair path

The minimal safe repair belongs in **source-backed overrides**:

1. Replace the `ice_storm` override's `description` with the full APG rules text already present in `raw_text_block`, or remove the stale description override entirely if the parser output is now trustworthy.
2. Regenerate `content/intermediary/advanced_players_guide_spells_intermediary.json`.
3. Re-import spells into `dungeoncrawler_content_registry` so the canonical DB row picks up the corrected description.

## Legacy duplicate handling

The live registry only contains the canonical hyphenated row (`ice-storm`). No underscore duplicate row currently exists in the DB. Runtime consumers already normalize underscore lookups to hyphenated IDs, so no separate `ice_storm` row needs synchronization.
