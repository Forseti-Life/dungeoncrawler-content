# Feature Brief: Gods and Magic (deferred)

- Work item id: dc-gam-gods-magic
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260412-dungeoncrawler-release-m
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Lost Omens: Gods and Magic
- Category: lore-sourcebook
- Created: 2026-04-07
- DB sections: gam/s01/Baseline Requirements, gam/s01/Integration Notes, gam/s02/Baseline Requirements, gam/s02/Integration Notes, gam/s03/Baseline Requirements, gam/s03/Integration Notes, gam/s04/Baseline Requirements, gam/s04/Integration Notes, gam/s05/Baseline Requirements, gam/s05/Integration Notes, gam/s06/Baseline Requirements, gam/s06/Integration Notes
- Depends on: 

## Goal

Implement Lost Omens: Gods and Magic content — deity stat blocks (divine ability, alignment, edicts, anathema, domains, spells, favored weapon), domain spell catalog, and the channel smite/holy symbol mechanics that feed into Cleric and Champion class features. Covers 36 requirements across `gam/s01–s06`.

## Source reference

> "This volume describes the major deities of the Inner Sea region and beyond, providing their divine portfolios, favored weapons, domains, and spell lists." (Lost Omens: Gods and Magic — Introduction)

## Implementation hint

Content type: `deity` with fields: `name`, `alignment`, `edicts[]`, `anathema[]`, `domains[]` (primary + alternate), `divine_font` (heal/harm), `divine_skill`, `favored_weapon` (FK to equipment), `cleric_spells{}` (level→spell mapping), `divine_ability[]` (STR/DEX/CON/INT/WIS/CHA options). Character integration: Cleric and Champion reference deity FK; domain feats unlock based on deity's domain list. Channel smite: Cleric spell-slot expenditure mechanic links to divine font. Holy symbol: equipment item FK with deity affiliation. DB sections are baseline/integration placeholders — real deity catalog loaded via drush import once schema is defined. Dependency: `dc-cr-spellcasting` (for cleric spell slot integration), `dc-cr-class-cleric`, `dc-cr-class-champion`.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: deity catalog is read-only public data; character deity selection requires character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH character deity-selection routes require `_csrf_request_header_mode: TRUE`
- Input validation: deity FK validated against allowed catalog entries; domain selections validated against deity's permitted domains
- PII/logging constraints: no PII logged; character id + deity id + domain selection only
