# Feature Brief: Performance Skill Actions

- Work item id: dc-cr-skills-performance-perform
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260410-dungeoncrawler-release-d
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Performance (Cha)
- Depends on: dc-cr-skill-system

## Goal

Implement Performance (Cha) skill actions — Perform and Earn Income — including Bard Composition Spell modifier integration and street performance downtime income resolution.

## Source reference

> "Perform: You use your talents to put on a show; attempt a Performance check, with the DC set by the audience or event. You can also Earn Income using the Performance skill."

## Implementation hint

`PerformAction` is an encounter/exploration activity that resolves a Performance check vs audience DC (set by GM or event type); outcome feeds into NPC attitude reactions or crowd size modifiers. `EarnIncomePerformance` routes through the shared `EarnIncomeService` using the Performance modifier; the GP-per-day table is keyed by character level and check result tier. For Bard integration: `CompositionSpellService` (from `dc-cr-class-bard`) injects Performance modifier into the composition spell DC calculation; link via a shared `PerformanceModifierProvider` interface. No separate performance "stat" exists beyond the Cha-based skill.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; Earn Income gold awards computed server-side from the DC table.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Perform DC must be a positive integer (GM-set or event enum); Earn Income downtime days must be a positive integer; Bard composition DC validated against caster's Performance modifier.
- PII/logging constraints: no PII logged; log character_id, action_type, dc_attempted, gp_earned; no PII logged.

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1716, 1717, 1718, 1719, 1720
- See `runbooks/roadmap-audit.md` for audit process.
