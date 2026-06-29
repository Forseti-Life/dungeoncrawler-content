# Feature Brief: GMG NPC Gallery System

- Work item id: dc-gmg-npc-gallery
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Gamemastery Guide, gmg/ch02
- Category: gm-tools
- Created: 2026-04-07
- DB sections: (sub-feature of gmg/ch02 Tools chapter — umbrella covered by dc-gmg-hazards)
- Depends on: dc-cr-npc-system

## Goal

Implement the GMG NPC Gallery as a pre-built stat block template library — covering standard NPC roles (Bandit, Guard, Merchant, Noble, Mage, etc.) — enabling GMs to rapidly populate encounters and social scenes with appropriately leveled adversaries.

## Source reference

> "The NPC Gallery provides a range of pre-built stat blocks for common NPCs; GMs can use these directly or as templates, adjusting for specific story needs."

## Implementation hint

NPC Gallery entries share the `CreatureStatBlock` schema from the creature system; add a `npc_gallery_role` field (enum: Bandit/Guard/Merchant/Noble/Mage/etc.) and `source: gmg` tag for filtering. Implement a `NpcGalleryBrowser` API endpoint returning paginated NPC templates filterable by role, level, and CR. `InstantiateNpcAction` (GM-only) clones a gallery template into a live `NpcEntity` on the current session map, allowing per-instance name/HP overrides. Link NPC templates to the encounter builder via the existing `AddCreatureToEncounter` workflow.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: GM-scoped write for NPC instantiation; NPC gallery is read-only for all users; per-instance overrides are GM-only.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: NPC role must be a valid gallery role enum; level override must be within ±2 of template level; HP overrides must be positive integers.
- PII/logging constraints: no PII logged; log gm_id, session_id, npc_template_id, instantiated_npc_id; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
