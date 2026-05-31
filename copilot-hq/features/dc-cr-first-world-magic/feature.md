# Feature Brief: First World Magic

- Work item id: dc-cr-first-world-magic
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

Add the First World Magic gnome ancestry feat (Feat 1), granting one player-chosen primal cantrip as an at-will innate primal spell. This is a simpler counterpart to the Fey-touched Gnome heritage (which also grants a primal cantrip but allows daily swapping). The feat version is fixed at selection. Wellspring Gnome characters who take this feat have it converted to their chosen tradition.

## Source reference

> "Your connection to the First World grants you a primal innate spell, much like those of the fey. Choose one cantrip from the primal spell list (page 314). You can cast this spell as a primal innate spell at will. A cantrip is heightened to a spell level equal to half your level rounded up."

## Implementation hint

Ancestry feat node in `dungeoncrawler_content` (Gnome, level 1). Player selects one primal cantrip at feat-acquisition time; stored on character record as a fixed innate spell. If character has Wellspring Gnome heritage, override tradition at acquisition. Interacts with `dc-cr-spellcasting` innate spell subsystem and `dc-cr-gnome-heritage-wellspring`.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: character-scoped write at feat-acquisition time; Gnome ancestry prerequisite enforced server-side before feat selection is accepted.
- CSRF expectations: all POST/PATCH routes in character creation/feat flow require `_csrf_request_header_mode: TRUE`.
- Input validation: selected cantrip ID must exist in the primal cantrip list; Wellspring tradition override only applied when character has Wellspring Gnome heritage (validated server-side at acquisition); no free-form input accepted.
- PII/logging constraints: no PII logged; log character_id, feat_id, cantrip_selected only.
