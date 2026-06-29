- Status: done
- Completed: 2026-04-11T04:06:10Z

# Suite Activation: dc-cr-multiclass-archetype

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-11T03:31:27+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-multiclass-archetype"`**  
   This links the test to the living requirements doc at `features/dc-cr-multiclass-archetype/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-multiclass-archetype-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-multiclass-archetype",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-multiclass-archetype"`**  
   Example:
   ```json
   {
     "id": "dc-cr-multiclass-archetype-<route-slug>",
     "feature_id": "dc-cr-multiclass-archetype",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-multiclass-archetype",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

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
- Agent: qa-dungeoncrawler
- Status: pending
