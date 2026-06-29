# Problem Statement

The canonical DB row for `aerial-form` was carrying a shortened summary description even though the authoritative CRB source text and stored `raw_text_block` already contained the full battle-form rules text.

## Current state

- Live canonical registry row: `dungeoncrawler_content_registry.content_id = aerial-form`
- Source-backed DB metadata: `source_book = core_rulebook_4th_printing`, `source_file = intermediary/PF2E Core Rulebook - Fourth Printing.txt`, `version = core-raw-text-v3`
- The live row previously stored a shortened `schema_data.description` summary while retaining the full spell prose in `schema_data.raw_text_block`

## Root cause

This was another **source-backed override defect** in `tools/extract_core_rulebook_spells.py`.

The `aerial_form` override hardcoded a compact summary description instead of the full spell text. That shortened text propagated into `content/intermediary/core_rulebook_spells_intermediary.json` and then into the canonical registry import.

## Correct repair path

The minimal safe repair was:

1. Replace the stale `aerial_form` override description with the full CRB battle-form text already reflected in `raw_text_block`.
2. Add a focused extractor regression test so the override cannot regress to a summary sentence again.
3. Regenerate `content/intermediary/core_rulebook_spells_intermediary.json`.
4. Re-import spells into `dungeoncrawler_content_registry`.

## Legacy duplicate handling

The live registry uses the canonical hyphenated row (`aerial-form`). No separate underscore row needed synchronization; runtime spell readers already normalize underscore spell IDs to the canonical hyphenated form.
