# Feature Brief: Medicine Skill Actions

- Work item id: dc-cr-skills-medicine-actions
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260408-dungeoncrawler-release-f
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Medicine (Wis)
- Depends on: dc-cr-skill-system, dc-cr-conditions, dc-cr-encounter-rules

## Goal

Implement Medicine (Wis) skill action handlers: Administer First Aid, Treat Disease, Treat Poison, and Treat Wounds — including all degree-of-success outcomes, healer's tools requirement, the 1-hour re-treat immunity window, and the dying/bleeding stabilization integration. Covers `core/ch04/Medicine (Wis)`.

## Source reference

> "You can patch up wounds, administer antidotes, and sometimes even lift the veil of death." (Chapter 4: Skills — Medicine)

## Implementation hint

**Administer First Aid** (2-action, manipulate, healer's tools, adjacent): one ailment per use; Stabilize: Medicine vs DC 15 + dying value; Stop Bleeding: Medicine vs appropriate DC, crit fail triggers immediate bleed damage. Note: `HPManager::stabilizeCharacter()` exists but is wired to Hero Point heroic-recovery — redirect to Medicine skill check. **Treat Disease** (downtime, trained, healer's tools, 8 hrs): Medicine vs disease DC; applies bonus/penalty to next disease save only. **Treat Poison** (1-action, trained, healer's tools): one attempt per creature per poison save; Medicine vs poison DC; bonus applies to immediate save. **Treat Wounds** (exploration, trained, healer's tools, 10 min): DC 15 / 2d8 HP (base); DC 20 / +10 HP (expert); DC 30 / +30 HP (master); DC 40 / +50 HP (legendary); crit success removes wounded condition + heals; success heals + removes wounded; crit fail deals 1d8 damage; extended 1-hour treatment doubles HP healed; 1-hour immunity window per patient (track `last_treated_at` on character).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; character ownership enforced via `_character_access: TRUE` on all character-scoped routes
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: HP restoration amounts server-computed from dice roll + proficiency tier; immunity window enforced server-side
- PII/logging constraints: no PII logged; gameplay action logs (character id, action key, target id, degree of success) only
