# Feature Brief: Session and Campaign Structure

- Work item id: dc-cr-session-structure
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 301–600
- Category: rule-system
- Created: 2026-02-27

## Goal

Implement the session and campaign data model for dungeoncrawler. A session is a single play instance (one-shot or one installment of a campaign). A campaign is a series of sessions sharing persistent characters, XP, and world state. This structure determines how character state is saved and restored between sessions, how the AI GM tracks narrative continuity, and how the game scales from a quick one-shot to a months-long adventure.

## Source reference

> "A complete Pathfinder story can be as short as a single session, commonly referred to as a 'one-shot,' or it can stretch on for multiple sessions, forming a campaign that might last for months or even years."
> "A session can be mostly action, with battles with vile beasts, escapes from fiendish traps, and the completion of heroic quests."

## Implementation hint

Content types: `session` (single play instance, with start/end timestamps, participants, mode: one-shot|campaign-chapter, linked campaign) and `campaign` (series of sessions, shared world state, persistent character roster). Session start/end API: save character state, XP delta, inventory changes, and GM narrative summary to campaign log. One-shot sessions are self-contained and do not persist state beyond the session. Campaign sessions persist all changes.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; campaign membership enforced; campaign invites validate registered user email only
- CSRF expectations: all POST/PATCH session/campaign routes require `_csrf_request_header_mode: TRUE`
- Input validation: campaign names and descriptions sanitized at Drupal field layer; session state fields validated against enums
- PII/logging constraints: no PII logged; session/campaign ids and participant user ids only

## Roadmap section

- Roadmap: Core Rulebook
