# Test Plan: dc-cr-multiclass-archetype

## Coverage summary
- AC items: ~14 (content type, dedication prerequisites, class feat slots, breadth limit, APG integration)
- Test cases: 10 (TC-MCA-01–10)
- Suites: playwright (character creation)
- Security: AC exemption granted

---

## TC-MCA-01 — Multiclass archetype content type: all 12 CRB archetypes present
- Description: Archetype list contains one archetype per CRB class (12 total); each has archetype_id, source_class, dedication_feat_id, available_archetype_feats[], minimum_dedication_level (2)
- Suite: playwright/character-creation
- Expected: archetype count = 12 CRB + APG additions; all required fields present
- AC: AC-001

## TC-MCA-02 — Dedication feat: level 2 minimum, no duplicate dedication
- Description: Character must be level 2+ to take dedication feat; cannot take second dedication from same archetype
- Suite: playwright/character-creation
- Expected: dedication feat blocked below L2; duplicate dedication from same archetype blocked with feedback
- AC: AC-002

## TC-MCA-03 — After dedication: archetype feats available at even-level class feat slots
- Description: After taking dedication, archetype feats appear in class feat options at subsequent even levels
- Suite: playwright/character-creation
- Expected: even-level class feat UI shows archetype feats alongside native class feats after dedication taken
- AC: AC-003

## TC-MCA-04 — Archetype feat level prerequisite enforced
- Description: Archetype feats with level prerequisites not selectable below that level
- Suite: playwright/character-creation
- Expected: archetype feat with min_level = 6 hidden/greyed out below L6
- AC: AC-003

## TC-MCA-05 — Breadth limit: second dedication requires 2 archetype feats from first
- Description: Character may take second dedication (different archetype) only after taking 2 archetype feats from first archetype
- Suite: playwright/character-creation
- Expected: second dedication available only when first archetype feat count ≥ 2
- AC: AC-004

## TC-MCA-06 — Same-archetype second dedication: always blocked
- Description: Second dedication from same archetype is always blocked (regardless of feat count)
- Suite: playwright/character-creation
- Expected: same archetype second dedication attempt → blocked regardless of progression
- AC: AC-004

## TC-MCA-07 — APG archetypes present and correctly tagged
- Description: APG adds >26 archetypes; all appear in archetype index with source_tag = "APG"
- Suite: playwright/character-creation
- Expected: total archetype count = 12 CRB + APG count; APG archetypes tagged by source
- AC: AC-005

## TC-MCA-08 — APG archetype prerequisites enforced
- Description: APG-specific dedication prerequisites (stat reqs, feat reqs) enforced correctly
- Suite: playwright/character-creation
- Expected: APG dedication blocked when APG-specific prereq not met; feedback names missing prereq
- AC: AC-005

## TC-MCA-09 — Character feat selection scope: archetype feats at even levels only
- Description: Archetype feats are only selectable in even-level class feat slots (not odd-level or general feat slots)
- Suite: playwright/character-creation
- Expected: odd-level class feat UI does not show archetype feats; general feat UI does not show class archetype feats
- AC: AC-003

## TC-MCA-10 — Archetype feat selection protected by session auth
- Description: Feat selection is user-scoped and cannot be modified for another user's character
- Suite: playwright/character-creation
- Expected: feat selection API requires auth matching character owner; cross-user modification rejected
- AC: Security AC
