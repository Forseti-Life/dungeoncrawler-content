- Status: done
- Summary: Audited the canonical `ice-storm` spell gap and traced the shortened live description to a stale `ice_storm` source-backed override in the APG extractor; the correct repair is to update that override, regenerate the APG intermediary, and re-import the spell registry.

## What changed
- Queried the live `dungeoncrawler_content_registry` row for `ice-storm` and confirmed the canonical DB entry still exposes a shortened `schema_data.description`.
- Confirmed the same live row already retains the full APG prose in `schema_data.raw_text_block`, so the authoritative local source text is present.
- Traced the defect to `dungeoncrawler-content/tools/extract_core_rulebook_spells.py`, where the `ice_storm` source-backed override hardcodes the shortened description and propagates it into `content/intermediary/advanced_players_guide_spells_intermediary.json`.
- Filled the architect inbox artifacts and copied the completed packet into `sessions/architect-copilot/artifacts/20260516-spell-gap-ice-storm/`.

## Verification
- `vendor/bin/drush sqlq "SELECT ... FROM dungeoncrawler_content_registry WHERE content_type='spell' AND content_id='ice-storm';"` against `/var/www/html/dungeoncrawler`
- `python3 tools/extract_advanced_players_guide_spells.py` in `/home/ubuntu/forseti.life/dungeoncrawler-content`
- Source review of `tools/extract_core_rulebook_spells.py` and `content/intermediary/advanced_players_guide_spells_intermediary.json`

## Blockers
- None.
