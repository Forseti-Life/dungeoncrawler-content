# Feature Brief: Environmental Hazards

- Work item id: dc-cr-hazards
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- Release: 
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: game-mechanic
- Created: 2026-02-26
- DB sections: core/ch10/Hazards

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; character ownership enforced via `_character_access: TRUE` on all character-scoped routes
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: entity field types enforced at Drupal entity layer; mutations server-validated against allowed values
- PII/logging constraints: no PII logged; gameplay action logs (character id, action type) only

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2373–2396
- See `runbooks/roadmap-audit.md` for audit process.

## Goal

Implement full PF2e hazard system (REQs 2373–2396). Current state: stealth_dc and
disable_dc schema exist in ContentRegistry; RoomStateService hides traps until detected.
Everything else is absent. Full scope required:
1. **Hazard stat block** (REQ 2389): AC, saving throw modifiers, Hardness, HP, Broken Threshold
2. **Simple vs complex type** (REQs 2379–2382): simple=one reaction; complex joins initiative with
   routine array per round
3. **Passive/active triggers** (REQs 2377–2378): passive fires on undetected entry; active on interact
4. **Detection** (REQs 2374–2376): auto-secret Perception vs stealth_dc on room entry; min-proficiency
   enforcement; detect_magic reveals magical hazards
5. **Disable action** (REQs 2384–2388): 2-action skill check vs disable_dc; crit fail triggers;
   multi-success for complex; proficiency minimum; requires detected flag
6. **Hazard HP** (REQs 2390–2392): BT/0-HP states; hitting triggers unless destroyed outright
7. **Magical hazards** (REQs 2393–2394): spell_level, counteract_dc fields
8. **Hazard XP** (REQs 2395–2396): award on overcome; Table 10-14 level-relative XP
9. **Reset condition** (REQ 2383): auto-reset or manual reset steps field

## Source reference

> "Rules for setting Difficulty Classes, granting rewards, environments, and hazards can also be found here." (Chapter 10: Game Mastering)

## Implementation hint

Content type: `hazard` with fields for hazard type (trap/haunt/environmental), level, stealth DC, disable DC (skill), triggered effect (damage/condition), and complexity (simple|complex). Complex hazards enter initiative. Integration: hazard XP contributes to the reward system. Disable attempts use the skill check / DC system. Can be placed as dungeon room elements.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
