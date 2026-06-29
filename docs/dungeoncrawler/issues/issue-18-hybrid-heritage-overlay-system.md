# Issue #18: Hybrid Heritage Overlay System

**Status**: Open  
**Type**: Feature Request  
**Priority**: High  
**Source**: PF2E Core Rulebook Chapter 2 — Human Heritages (Half-Elf, Half-Orc)  
**Created**: 2026-04-18

## Overview

Core Chapter 2 defines Half-Elf and Half-Orc as human heritages rather than separate ancestries. The system therefore needs a heritage overlay model that can add traits, senses, and ancestry-feat eligibility from another ancestry without replacing the base human ancestry record.

## Requirements

### REQ-18.1 — Hybrid Heritage Is a Human Heritage Overlay
- Half-Elf and Half-Orc must be modeled as Human heritage records, not standalone ancestry entities.
- A character can still have only one heritage at 1st level.

### REQ-18.2 — Hybrid Heritage Adds Traits and Senses
- Half-Elf adds the `elf` and `half-elf` traits and grants low-light vision.
- Half-Orc adds the `orc` and `half-orc` traits and grants low-light vision.
- Heritage-applied traits and senses must merge with the base ancestry record at runtime.

### REQ-18.3 — Heritage Expands Ancestry Feat Eligibility
- Half-Elf characters can select elf, half-elf, and human ancestry feats whenever they gain an ancestry feat.
- Half-Orc characters can select orc, half-orc, and human ancestry feats whenever they gain an ancestry feat.
- Feat validation must resolve eligibility from the heritage overlay, not just the base ancestry id.

### REQ-18.4 — Heritage-Gated Feats Must Enforce Compatibility
- Some feats granted through hybrid heritage paths require prerequisite capabilities already present on the character.
- The ancestry-feat picker must reject feat choices whose prerequisite senses/traits are missing.

## Notes
- Source requirements captured in `docs/dungeoncrawler/PF2requirements/references/core-ch02-ancestries-backgrounds.md`.
- Initial release-facing feature stub created: `features/dc-cr-half-elf-heritage/feature.md`.
- Existing related shipped/worked item: `features/dc-cr-human-ancestry/feature.md`.
