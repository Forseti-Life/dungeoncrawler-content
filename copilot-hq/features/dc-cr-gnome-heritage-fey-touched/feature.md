# Feature Brief: Gnome Heritage — Fey-touched Gnome

- Work item id: dc-cr-gnome-heritage-fey-touched
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6484–6783
- Category: game-mechanic
- Release: 20260412-dungeoncrawler-release-i
- Created: 2026-04-08

## Goal

Add the Fey-touched Gnome heritage, which grants the fey trait, one primal cantrip as an innate spell at will, and the ability to swap that cantrip once per day via a 10-minute concentration activity. This is the most magically versatile gnome heritage and integrates with the spellcasting and innate spell systems.

## Source reference

> "You gain the fey trait, in addition to the gnome and humanoid traits. Choose one cantrip from the primal spell list (page 314). You can cast this spell as a primal innate spell at will. A cantrip is heightened to a spell level equal to half your level rounded up. You can change this cantrip to a different one from the same list once per day by meditating to realign yourself with the First World; this is a 10-minute activity that has the concentrate trait."

## Implementation hint

Heritage node in `dungeoncrawler_content`. Adds `fey` trait to character traits list. Grants a player-selectable primal cantrip as at-will innate spell (heightened by level). Must expose a daily cantrip-swap action (10-minute downtime activity with concentrate tag). Interacts with `dc-cr-spellcasting` innate spell subsystem.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: character-scoped write at heritage selection; Gnome ancestry prerequisite enforced server-side.
- CSRF expectations: all POST/PATCH routes in character creation flow require `_csrf_request_header_mode: TRUE`.
- Input validation: cantrip ID must exist in the primal cantrip list; daily cantrip-swap action validates new cantrip against same list; duration/concentrate tag enforced server-side.
- PII/logging constraints: no PII logged; log character_id, heritage_id, cantrip_selected only.
