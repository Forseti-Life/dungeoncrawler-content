# Feature Brief: Skills Calculator Hardening

- Website: dungeoncrawler
- Type: extend
- Module: dungeoncrawler_content
- Priority: P1
- Status: done
- Release: 20260408-dungeoncrawler-release-f
- Dependencies: dc-cr-skill-system, dc-cr-character-leveling

## Goal

Harden the skills calculator to correctly apply proficiency bonuses, all bonus type stacking rules, and armor check penalties — ensuring each skill modifier is computed from a validated canonical formula and same-type bonuses never illegally stack.

## Source reference

> "Your skill modifier is equal to your relevant ability modifier plus your proficiency bonus (0 for untrained, level+2 for trained, level+4 for expert, level+6 for master, level+8 for legendary) plus any item, status, and circumstance bonuses."

## Implementation hint

Implement `SkillModifierCalculator.compute(character, skill)` that aggregates bonuses by type into named buckets (item, status, circumstance, untyped, penalties); within each typed bucket take the highest positive value (bonus) or most negative (penalty); sum across all buckets. Proficiency is computed as: Untrained=0, Trained=level+2, Expert=level+4, Master=level+6, Legendary=level+8. Armor check penalty is applied to Acrobatics, Athletics, Stealth, and Thievery as an untyped penalty. Add a `BonusStackingValidator` that runs on every skill check and logs a warning if duplicate bonus types are detected.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Skill modifier computation is server-authoritative; no client-submitted modifier overrides accepted.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Proficiency rank must be one of [untrained, trained, expert, master, legendary]; bonus values must be integers; ability modifier derived from character attributes, not client input.
- PII/logging constraints: no PII logged; log character_id, skill, computed_modifier, proficiency_rank, bonus_components[]; no PII logged.

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1553, 1554, 1556, 1563, 1564, 1566, 1567, 1568, 1600, 2321, 2323
- See `runbooks/roadmap-audit.md` for audit process.
