# Feature Brief: GMG Hazards and Traps

- Work item id: dc-gmg-hazards
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260412-dungeoncrawler-release-e
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Gamemastery Guide, gmg/ch02
- Category: gm-tools
- Created: 2026-04-07
- DB sections: gmg/ch02/Baseline Requirements, gmg/ch02/Integration Notes
- Depends on: dc-cr-encounter-rules

## Goal

Implement the hazard system — Environmental and Trap hazard types with Stealth detection, Disable skill checks, AC/Saves for destructible hazards, and Complex hazard initiative/routine support — enabling GMs to place mechanical and environmental threats in encounters.

## Source reference

> "Hazards have a Stealth DC to detect and a Disable DC to deactivate; complex hazards have initiative and take a routine each round until disabled or destroyed."

## Implementation hint

Define `HazardEntity` with fields: hazard_type (environmental/trap), stealth_dc, disable_skill, disable_dc, ac, saving_throws, hardness, hp, bt, reset_condition, trigger_description, routine_actions[]. Simple hazards execute `trigger_effect` once when activated; Complex hazards enter initiative at the roll value and execute `routine_actions[]` each round until `disabled` or `destroyed` flags are set. `HazardDetectionService` runs a Perception check vs stealth_dc for each character entering range during exploration. `DisableHazardAction` resolves the specified disable_skill check vs disable_dc; on success set `disabled=true`; on crit failure trigger the hazard immediately.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: GM-only write for hazard placement; players see only detected hazards; full hazard data visible to GM at all times.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Hazard type must be a valid enum; disable_skill must be a valid skill name; routine actions must reference valid action types; DC values must be positive integers.
- PII/logging constraints: no PII logged; log gm_id, session_id, hazard_id, action (place/trigger/disable), character_id; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
