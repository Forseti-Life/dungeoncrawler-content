# Feature Brief: APG New Rituals

- Work item id: dc-apg-rituals
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
20260408-dungeoncrawler-release-h
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: apg/ch05
- Category: spells
- Created: 2026-04-06
- DB sections: apg/ch05/Rituals (APG New Rituals)
- Depends on: dc-cr-rituals

## Goal

Extend the ritual catalog with APG ritual entries — using the same ritual schema as CRB rituals — enabling multi-caster, multi-day magical workings for expanded lore and story-driven spell effects.

## Source reference

> "Rituals are special spells that can be cast by anyone with the proper knowledge; they have a casting time of at least 1 hour, a Cost in rare materials, and use Primary and Secondary casters."

## Implementation hint

APG rituals use the same `RitualEntity` schema: name, rank, cast_time_hours, primary_check (skill + DC), secondary_casters (count + check), cost_gp, traditions[], effect_text, heightened. Implement a `RitualCastingService` that: validates primary caster level ≥ ritual rank, assembles secondary casters, resolves primary + secondary checks, and computes the outcome by success tier. APG adds new ritual entries as a bulk data import extending the CRB ritual catalog; validate against the existing schema before insertion. Rituals are available to any character meeting the level and tradition requirements, not class-restricted.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: GM-approved ritual initiation; primary caster character-scoped; secondary caster assignments validated against available party members.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Ritual ID must reference a valid ritual entity; secondary caster count must match ritual requirements; cost_gp deducted server-authoritative from party pool.
- PII/logging constraints: no PII logged; log session_id, ritual_id, primary_caster_id, secondary_casters[], primary_check_result, outcome; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
