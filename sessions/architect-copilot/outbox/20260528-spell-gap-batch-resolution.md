- Status: done
- Summary: Cleared the remaining architect spell-gap backlog by adding a raw-text recovery path for source-backed override descriptions, regenerating and re-importing the spell registry, and closing the false-positive sin-label items that were never real spells.

## What changed
- Added extractor logic in `dungeoncrawler-content/tools/extract_core_rulebook_spells.py` that recovers the canonical `description` from `raw_text_block` for known source-backed override IDs whose stored description had degraded into summary prose.
- Added focused extractor regressions in `dungeoncrawler-content/tools/test_extract_core_rulebook_spells.py` to cover representative override shapes, including simple narrative text and multi-action spell variants.
- Regenerated:
  - `dungeoncrawler-content/content/intermediary/core_rulebook_spells_intermediary.json`
  - `dungeoncrawler-content/content/intermediary/advanced_players_guide_spells_intermediary.json`
  - `dungeoncrawler-content/content/intermediary/secrets_of_magic_spells_intermediary.json`
- Re-imported the canonical spell registry so the remaining architect spell-gap rows now carry full descriptions, including:
  - `air-walk`
  - `animal-form`
  - `curse-of-lost-time`
  - `detect-alignment`
  - `devil-form`
  - `foresight`
  - `fungal-infestation`
  - `gate`
  - `ghostly-tragedy`
  - `grisly-growths`
  - `heal`
  - `holy-cascade`
  - `impending-doom`
  - `inner-radiance-torrent`
  - `invoke-spirits`
  - `one-with-the-land`
- Investigated `envy`, `gluttony`, `greed`, and `lust` and confirmed they do not exist as canonical spell rows or intermediary spell records. In Secrets of Magic they appear only as runelord sin-school labels in class-archetype text, so those inbox items were false positives.

## Verification
- `python3 -m unittest tools/test_extract_core_rulebook_spells.py`
- `python3 tools/extract_core_rulebook_spells.py`
- `python3 tools/extract_advanced_players_guide_spells.py`
- `python3 tools/extract_secrets_of_magic_spells.py`
- `vendor/bin/drush dc:import-spells`
- Live registry spot checks for the fixed spell IDs via `vendor/bin/drush sqlq ...`
- Source-text search in `../forseti-docs/dungeoncrawler/reference documentation/PF2E Secrets of Magic.txt` confirming `ENVY`, `GLUTTONY`, `GREED`, and `LUST` are runelord labels rather than spell entries

## Blockers
- None.
