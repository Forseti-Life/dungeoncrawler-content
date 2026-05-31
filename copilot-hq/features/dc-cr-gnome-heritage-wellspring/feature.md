# Feature Brief: Gnome Heritage — Wellspring Gnome

- Work item id: dc-cr-gnome-heritage-wellspring
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6484–6783
- Category: game-mechanic
- Release: 20260412-dungeoncrawler-release-j
- Created: 2026-04-08

## Goal

Add the Wellspring Gnome heritage, which allows the gnome to choose a non-primal magical tradition (arcane, divine, or occult) and grants one at-will cantrip from that tradition. Crucially, any primal innate spells gained from gnome ancestry feats also convert to the chosen tradition. This is the most complex gnome heritage from a data model perspective — it requires a persistent tradition-override field on the character record.

## Source reference

> "Choose arcane, divine, or occult. You gain one cantrip from that magical tradition's spell list (pages 307–315). You can cast this spell as an innate spell at will... Whenever you gain a primal innate spell from a gnome ancestry feat, change its tradition from primal to your chosen tradition."

## Implementation hint

Heritage node in `dungeoncrawler_content`. Requires a `wellspring_tradition` field on the character (arcane/divine/occult). Player-selectable cantrip from chosen list. Ancestry feat processor must check for Wellspring Gnome flag and override innate spell traditions at time of feat application. Interacts with `dc-cr-spellcasting` and `dc-cr-first-world-magic`.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: character-scoped write at heritage selection; Gnome ancestry prerequisite enforced server-side; wellspring_tradition field (arcane/divine/occult) validated as enum.
- CSRF expectations: all POST/PATCH routes in character creation flow require `_csrf_request_header_mode: TRUE`.
- Input validation: tradition must be one of {arcane, divine, occult}; cantrip ID must exist in the chosen tradition's cantrip list; tradition override at feat-acquisition time validated server-side.
- PII/logging constraints: no PII logged; log character_id, heritage_id, tradition_selected, cantrip_selected only.
