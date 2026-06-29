# Test Plan Design: dc-cr-multiclass-archetype

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T16:28:41+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-multiclass-archetype/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-multiclass-archetype "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
