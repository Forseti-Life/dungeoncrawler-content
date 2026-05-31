# Feature Brief: Champion Class Mechanics

- Work item id: dc-cr-class-champion
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release:
20260409-dungeoncrawler-release-e
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Champion
- Depends on: dc-cr-character-class, dc-cr-focus-spells

## Goal

Implement Champion class mechanics — Champion's Code, Divine Ally, Champion's Reaction, Lay on Hands, and Devotion Spells — so players experience deity-bound holy warfare with cause-specific defensive reactions.

## Source reference

> "A champion's cause determines which champion's reaction they can use: paladins use Retributive Strike, liberators use Liberating Step, and redeemers use Glimpse of Redemption."

## Implementation hint

Store `cause` (Paladin/Liberator/Redeemer) and `deity_id` as required fields on the Champion class entity; `cause` determines which `ChampionReactionHandler` subclass is active. `LayOnHandsAction` heals as a 1-action focus spell spending 1 Focus Point; gate it behind the focus pool system from `dc-cr-spellcasting`. Divine Ally is a subtype selection (Blade/Shield/Steed) each granting passive modifiers; implement as a strategy pattern on the champion entity. Validate anathema violations by cross-referencing deity anathema list on any action that could trigger it.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; deity selection immutable after character creation unless GM override.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Cause enum restricted to [Paladin, Liberator, Redeemer]; deity_id must reference a valid deity record; alignment check enforced server-side.
- PII/logging constraints: no PII logged; log character_id, reaction_type, target_id; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
