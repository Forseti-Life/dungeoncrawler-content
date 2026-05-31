# Acceptance Criteria: Multiclass Archetype System
# Feature: dc-cr-multiclass-archetype

## AC-001: Multiclass Archetype Content Type
- Given a multiclass archetype exists, when retrieved, then it has: archetype_id, source_class, dedication_feat_id, available_archetype_feats[], minimum_dedication_level (typically 2)
- Given every core class has a multiclass archetype, when the archetype list is queried, then 12 archetypes are present (one per CRB class)

## AC-002: Dedication Feat Prerequisite
- Given a character selects a multiclass dedication feat, when the selection is validated, then the character must be at least 2nd level and must not already have a dedication feat from the same archetype
- Given the dedication feat is taken, when archetype feats of that class are listed, then they are available for selection at subsequent even-level class feat slots

## AC-003: Class Feat Slot Assignment
- Given a character has a multiclass dedication, when they level to an even level with a class feat, then the feat selection UI includes archetype feats alongside class-native feats
- Given an archetype feat has a level prerequisite, when the character is below that level, then the feat is not selectable

## AC-004: Breadth Limit (Max 1 Dedication per Archetype)
- Given a character already has a dedication feat for an archetype, when they attempt to take another dedication feat for the same archetype, then the attempt is rejected
- Given a character completes 2 archetype feats from one archetype, when they want a second dedication, then a second dedication from a different archetype is allowed

## AC-005: APG Integration
- Given the APG adds additional archetypes (>26 archetypes), when the archetype index is queried, then all CRB + APG archetypes are present and correctly tagged by source
- Given an APG archetype dedication feat, when prerequisites are checked, then APG-specific prerequisites are enforced

## Security acceptance criteria

- Security AC exemption: Archetype content is static rulebook data; no user-generated content for this feature. Character feat selection is user-scoped and protected by standard session auth.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Chapter 3: Classes
