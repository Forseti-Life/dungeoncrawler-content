# Feature Brief: Difficulty Class (DC) System

- Work item id: dc-cr-difficulty-class
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P0 (core check resolution mechanic — encounter and skill systems depend on this)
- Release: 
20260407-dungeoncrawler-release-b
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26
- DB sections: core/ch10/Setting DCs

## Goal

Implement the Difficulty Class system that defines target numbers for all checks in the game. DCs are set by level-based tables (Simple DC by level), skill DC guidelines, and fixed DCs for specific tasks. The four degrees of success (critical success / success / failure / critical failure) determined by comparing d20 + modifiers to DC are the resolution core of PF2E and govern all non-attack checks.

## Source reference

> "Rules for setting Difficulty Classes, granting rewards, environments, and hazards can also be found here." (Chapter 10: Game Mastering)

## Implementation hint

DC lookup table by level (1–20) and task difficulty (Trivial/Low/Moderate/High/Extreme/Incredible). Check resolution function: input (roll total, DC) → degree of success enum. Degree of success: roll ≥ DC+10 = crit success; roll ≥ DC = success; roll < DC = failure; roll ≤ DC−10 = crit failure. Natural 20 bumps one degree up; natural 1 bumps one degree down. Reusable across all check types.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: DC computation is server-side read-only; no user-editable DC fields exposed
- CSRF expectations: DC lookup endpoints are GET-only; any POST storing custom DCs requires `_csrf_request_header_mode: TRUE`
- Input validation: rarity adjustments and level-based DC tables are server-side constants; no free-text DC overrides accepted
- PII/logging constraints: no PII logged; DC lookups are ephemeral and not stored
