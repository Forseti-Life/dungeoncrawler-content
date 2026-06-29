# Feature Brief: GMG Chapter 1 — Running the Game

- Work item id: dc-gmg-running-guide
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Gamemastery Guide, gmg/ch01
- Category: gm-tools
- Created: 2026-04-07
- DB sections: gmg/ch01/Adjudicating Rules, gmg/ch01/Adjudicating Rules (Extended), gmg/ch01/Adventure Design, gmg/ch01/Campaign Structure, gmg/ch01/Chapter Scope and GM Priorities, gmg/ch01/Drawing Maps, gmg/ch01/Encounter Design, gmg/ch01/General Advice, gmg/ch01/Narrative Collaboration, gmg/ch01/Narrative Collaboration (Extended), gmg/ch01/Rarity in Your Game, gmg/ch01/Resolving Problems, gmg/ch01/Resolving Problems (Extended), gmg/ch01/Running Downtime, gmg/ch01/Running Encounters, gmg/ch01/Running Exploration, gmg/ch01/Special Circumstances
- Depends on: dc-cr-gm-tools, dc-cr-encounter-rules, dc-cr-exploration-mode, dc-cr-downtime-mode

## Goal

Surface GMG "Running the Game" reference material — encounter building guidelines, difficulty budget tables, exploration pacing, downtime tables, and social encounter rules — as searchable, linked GM tools within the DungeonCrawler interface.

## Source reference

> "The GM chapter provides guidance on building encounters, running exploration, handling downtime, and adjudicating social situations; these tools help GMs create balanced, engaging sessions."

## Implementation hint

Model Running Guide content as a `GmReferenceContent` entity with fields: title, section, content_markdown, tags[], source_book (gmg). Implement a `GmReferenceSearchService` supporting full-text search and tag filtering; index content by section tags (encounter_building, exploration, downtime, social). Surface key tables (encounter budget, XP thresholds) as structured data linked to the encounter builder and XP award system rather than plain text. Add a GM-facing sidebar panel in the session interface that surfaces contextually relevant GM guidance based on the current game phase.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: GM-only access to Running Guide content in-session; content is read-only for all users.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Search queries sanitized for SQL injection; tag filters validated against known tag enum; content markdown sanitized with allowlisted HTML subset.
- PII/logging constraints: no PII logged; log gm_id, search_query, section_accessed; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
