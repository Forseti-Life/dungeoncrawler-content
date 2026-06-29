# Feature Brief: Vivacious Conduit (Gnome Ancestry Feat 9)

- Work item id: dc-cr-vivacious-conduit
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6784–7083
- Category: game-mechanic
- Created: 2026-04-09

## Goal

Gnome Feat 9 representing the gnome's deepened connection to the First World and its positive energy. After a 10-minute rest, the character regains HP equal to Constitution modifier × half level. This is additive with Treat Wounds healing. Provides meaningful passive recovery between encounters for gnome characters at mid-levels.

## Source reference

> Your connection to the First World has grown, and its positive energy flows into you rapidly. If you rest for 10 minutes, you regain Hit Points equal to your Constitution modifier × half your level. This is cumulative with any healing you receive from Treat Wounds.

## Implementation hint

Ancestry feat (level 9) that hooks into the short-rest/exploration-mode healing flow. On 10-minute rest trigger, add HP = Con_mod × floor(level / 2) to the character. Must stack correctly with Treat Wounds results (not replace). Requires the rest/downtime system to support multi-source healing accumulation.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: healing is calculated in existing rest/Treat Wounds flows only; server must own all HP adjustments.
- CSRF expectations: all POST/PATCH requests in rest, downtime, and healing flows require `_csrf_request_header_mode: TRUE`.
- Input validation: bonus healing uses the character's Constitution modifier and half level; no client-supplied healing values accepted.
- PII/logging constraints: no PII logged; log character_id, con_mod, level, bonus_healing only.
