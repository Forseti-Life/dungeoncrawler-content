# Feature Brief: Gnome Weapon Expertise (Gnome Ancestry Feat 13)

- Work item id: dc-cr-gnome-weapon-expertise
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 20260412-dungeoncrawler-release-h
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6784–7083
- Category: game-mechanic
- Created: 2026-04-09

## Goal

Gnome Feat 13 (requires Gnome Weapon Familiarity) that synchronizes gnome weapon proficiency with class-granted weapon upgrades. Whenever the character gains expert or greater proficiency in any weapon via a class feature, they automatically gain the same proficiency rank in glaive, kukri, and all gnome weapons they are trained in. Ensures gnome martial identity scales seamlessly with any class.

## Source reference

> Prerequisites Gnome Weapon Familiarity. Your gnome affinity blends with your class training, granting you great skill with gnome weapons. Whenever you gain a class feature that grants you expert or greater proficiency in a given weapon or weapons, you also gain that proficiency in the glaive, kukri, and all gnome weapons in which you are trained.

## Implementation hint

Ancestry feat (level 13) that registers a passive listener on the "class weapon proficiency granted" event. When triggered, it applies the same proficiency rank to glaive, kukri, and all gnome weapons the character is trained in. Requires the proficiency system to support event-driven cascading upgrades triggered by class feature grants.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: proficiency cascades are passive and server-calculated during class-proficiency updates only.
- CSRF expectations: all POST/PATCH requests in level-up and proficiency-update flows require `_csrf_request_header_mode: TRUE`.
- Input validation: cascade applies only to glaive, kukri, and trained gnome weapons when a class feature grants expert-or-higher weapon proficiency.
- PII/logging constraints: no PII logged; log character_id, source_class_feature, affected_weapons, new_proficiency_rank only.
